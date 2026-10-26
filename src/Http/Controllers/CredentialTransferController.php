<?php

namespace Thevps\Vault\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Redirect;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Thevps\Vault\Models\Credential;
use Thevps\Vault\Models\CredentialGroup;
use Thevps\Vault\Support\CredentialCsv;
use Thevps\Vault\Support\FaviconFetcher;
use Thevps\Vault\Vault;

use function Illuminate\Support\defer;

/**
 * Bulk import / export of credentials as CSV (CredentialCsv). Same ACL as one-by-one work:
 * import = 'manage' on every target group (like store()), export = only what the user can view.
 * The uploaded file is read straight from PHP's temp upload and never stored.
 */
class CredentialTransferController extends Controller
{
    public const MAX_ROWS = 1000;

    public function index(Request $request): Response
    {
        $user = $request->user();

        return Inertia::render(Vault::page('passwords/Transfer'), [
            'manageableGroups' => $this->manageableGroups($user)->values(),
            'visibleGroups' => CredentialGroup::query()
                ->whereHas('members', fn ($q) => $q->where('credential_group_members.user_id', $user->getKey()))
                ->orderBy('name')
                ->get(['id', 'name']),
            'columns' => CredentialCsv::COLUMNS,
            'maxRows' => self::MAX_ROWS,
        ]);
    }

    public function template(): StreamedResponse
    {
        return response()->streamDownload(function () {
            echo CredentialCsv::header();
            echo CredentialCsv::line(['Приклад: хостинг', 'admin@example.com', 'змініть-мене', 'https://example.com', '', 'Видаліть цей рядок', '']);
        }, 'passwords-template.csv', ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    public function import(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            // Either a file (.csv / .xlsx) or text pasted straight from Excel.
            'file' => ['nullable', 'required_without:text', 'file', 'max:2048', 'extensions:csv,txt,xlsx'],
            'text' => ['nullable', 'required_without:file', 'string', 'max:2000000'],
            'default_group_id' => ['nullable', 'integer'],
            'skip_duplicates' => ['boolean'],
        ], [
            'file.required_without' => 'Оберіть файл або вставте дані текстом.',
            'text.required_without' => 'Оберіть файл або вставте дані текстом.',
        ], ['file' => 'файл', 'text' => 'дані', 'default_group_id' => 'група за замовчуванням']);

        $user = $request->user();
        $groups = $this->manageableGroups($user);
        $groupsByName = $groups->keyBy(fn (CredentialGroup $group) => mb_strtolower($group->name));

        $defaultGroup = null;
        if (filled($validated['default_group_id'] ?? null)) {
            $defaultGroup = $groups->firstWhere('id', (int) $validated['default_group_id']);
            if (! $defaultGroup) {
                throw ValidationException::withMessages(['default_group_id' => 'У вас немає права «Керування» в цій групі.']);
            }
        }

        $source = isset($validated['file']) ? 'file' : 'text';
        $parsed = $source === 'file'
            ? CredentialCsv::parseFile($validated['file']->getRealPath())
            : CredentialCsv::parseText($validated['text']);
        if ($parsed['error']) {
            throw ValidationException::withMessages([$source => $parsed['error']]);
        }
        if (! $parsed['rows']) {
            throw ValidationException::withMessages([$source => 'Немає жодного рядка з даними під заголовком.']);
        }
        if (count($parsed['rows']) > self::MAX_ROWS) {
            throw ValidationException::withMessages([$source => 'Забагато рядків: максимум '.self::MAX_ROWS.' за раз.']);
        }

        // Validate everything first — all or nothing, so a half-imported file never has to be untangled.
        // Messages name the row and the problem, never the cell values (they may be secrets).
        $errors = [];
        $prepared = [];
        foreach ($parsed['rows'] as $line => $row) {
            $validator = Validator::make($row, [
                'name' => ['required', 'string', 'max:255'],
                'login' => ['nullable', 'string', 'max:255'],
                'password' => ['nullable', 'string'],
                'totp_secret' => ['nullable', 'string', 'max:255'],
                'url' => ['nullable', 'string', 'url', 'max:2048'],
                'notes' => ['nullable', 'string'],
            ], [], collect(CredentialCsv::COLUMNS)->map(fn ($label) => "«{$label}»")->all());

            $rowErrors = $validator->errors()->all();

            $groupIds = [];
            $names = array_filter(preg_split(CredentialCsv::GROUP_SEPARATOR, $row['groups'] ?? '') ?: [], 'strlen');
            foreach ($names as $groupName) {
                $group = $groupsByName->get(mb_strtolower($groupName));
                if ($group) {
                    $groupIds[] = $group->id;
                } else {
                    $rowErrors[] = "Групу «{$groupName}» не знайдено серед груп, де у вас право «Керування».";
                }
            }
            if (! $names && $defaultGroup) {
                $groupIds[] = $defaultGroup->id;
            }
            if (! $names && ! $defaultGroup) {
                $rowErrors[] = 'Не вказано групу — заповніть колонку «Групи» або оберіть групу за замовчуванням.';
            }

            if ($rowErrors) {
                $errors["row_{$line}"] = "Рядок {$line}: ".implode(' ', $rowErrors);

                continue;
            }

            $prepared[$line] = [
                'data' => collect($row)->only(['name', 'login', 'password', 'totp_secret', 'url', 'notes'])
                    ->map(fn ($value) => $value === '' ? null : $value)
                    ->all(),
                'group_ids' => array_values(array_unique($groupIds)),
            ];
        }

        if ($errors) {
            throw ValidationException::withMessages(array_slice($errors, 0, 50, true));
        }

        $existing = ($validated['skip_duplicates'] ?? true) ? $this->visibleKeys($user) : [];
        $created = [];
        $skipped = 0;

        DB::transaction(function () use ($prepared, $user, &$existing, &$created, &$skipped) {
            foreach ($prepared as $item) {
                $key = $this->duplicateKey($item['data']['name'], $item['data']['login'] ?? null);
                if (isset($existing[$key])) {
                    $skipped++;

                    continue;
                }
                $existing[$key] = true;

                $credential = Credential::create([
                    ...$item['data'],
                    'visibility' => 'group',
                    'created_by_id' => $user->getKey(),
                ]);
                $credential->groups()->attach($item['group_ids']);
                $created[] = $credential;
            }
        });

        // Favicons after the response — one HTTP call per credential would otherwise make a big
        // import feel hung. Best-effort, same as CredentialController::refreshIcon().
        $withUrl = array_filter($created, fn (Credential $credential) => filled($credential->url));
        if ($withUrl) {
            defer(function () use ($withUrl) {
                foreach ($withUrl as $credential) {
                    $binary = FaviconFetcher::fetch($credential->url);
                    if ($binary !== null) {
                        $credential->addMediaFromString($binary)->usingFileName('icon.png')->toMediaCollection('icon');
                    }
                }
            });
        }

        Log::info('vault.import', ['user_id' => $user->getKey(), 'created' => count($created), 'skipped' => $skipped]);

        $message = 'Імпортовано паролів: '.count($created).'.';
        if ($skipped) {
            $message .= " Пропущено дублікатів: {$skipped}.";
        }

        return Redirect::route(Vault::routeName('passwords', 'index'))->with('success', $message);
    }

    public function export(Request $request): StreamedResponse
    {
        $user = $request->user();
        $groupId = $request->integer('group_id') ?: null;

        $credentials = $this->visibleQuery($user)
            ->when($groupId, fn ($q) => $q->whereHas('groups', fn ($g) => $g->where('credential_groups.id', $groupId)))
            ->with('groups:id,name')
            ->orderBy('name')
            ->get();

        Log::info('vault.export', ['user_id' => $user->getKey(), 'count' => $credentials->count(), 'group_id' => $groupId]);

        return response()->streamDownload(function () use ($credentials) {
            echo CredentialCsv::header();
            foreach ($credentials as $credential) {
                echo CredentialCsv::line([
                    $credential->name,
                    $credential->login,
                    $credential->password,
                    $credential->url,
                    $credential->totp_secret,
                    $credential->notes,
                    $credential->groups->pluck('name')->implode(', '),
                ]);
            }
        }, 'passwords-'.now()->format('Y-m-d').'.csv', [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Cache-Control' => 'no-store, private',
        ]);
    }

    /** Same visibility rule as CredentialController::index(): any group, direct grant or public. */
    private function visibleQuery($user)
    {
        $groupIds = DB::table('credential_group_members')->where('user_id', $user->getKey())->pluck('credential_group_id');
        $directIds = DB::table('credential_user_access')->where('user_id', $user->getKey())->pluck('credential_id');

        return Credential::query()->where(function ($q) use ($groupIds, $directIds) {
            $q->whereHas('groups', fn ($g) => $g->whereIn('credential_groups.id', $groupIds))
                ->orWhereIn('id', $directIds)
                ->orWhere('visibility', 'public');
        });
    }

    /** name+login of what the user already sees — "duplicate" never reveals credentials they can't. */
    private function visibleKeys($user): array
    {
        return $this->visibleQuery($user)->get(['name', 'login'])
            ->mapWithKeys(fn (Credential $credential) => [$this->duplicateKey($credential->name, $credential->login) => true])
            ->all();
    }

    private function duplicateKey(?string $name, ?string $login): string
    {
        return mb_strtolower(trim((string) $name))."\0".mb_strtolower(trim((string) $login));
    }

    private function manageableGroups($user)
    {
        return CredentialGroup::query()
            ->whereHas('members', fn ($q) => $q->where('credential_group_members.user_id', $user->getKey())
                ->where('credential_group_members.access_level', CredentialGroup::ACCESS_MANAGE))
            ->orderBy('name')
            ->get(['id', 'name']);
    }
}
