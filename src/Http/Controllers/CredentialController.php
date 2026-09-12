<?php

namespace Thevps\Vault\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Redirect;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;
use Thevps\Vault\Events\CredentialAccessGranted;
use Thevps\Vault\Http\Requests\StoreCredentialRequest;
use Thevps\Vault\Http\Requests\UpdateCredentialRequest;
use Thevps\Vault\Models\Credential;
use Thevps\Vault\Models\CredentialGroup;
use Thevps\Vault\Support\FaviconFetcher;
use Thevps\Vault\Vault;

class CredentialController extends Controller
{
    private const SORTABLE_COLUMNS = ['name', 'created_at'];

    public function index(Request $request): Response
    {
        $user = $request->user();

        // One query — the user's access level per group they belong to (map group_id =>
        // access_level). Each credential's level is then resolved in memory (max over its
        // groups), no N+1 per row.
        $myAccess = DB::table('credential_group_members')
            ->where('user_id', $user->getKey())
            ->pluck('access_level', 'credential_group_id');

        // Same one-query trick as group access, for credentials shared with this user directly
        // (no group involved) — see Credential::directUsers()/HasGroupAccess.
        $myDirectAccess = DB::table('credential_user_access')
            ->where('user_id', $user->getKey())
            ->pluck('access_level', 'credential_id');

        $credentials = Credential::query()
            ->where(function ($q) use ($myAccess, $myDirectAccess) {
                $q->whereHas('groups', fn ($g) => $g->whereIn('credential_groups.id', $myAccess->keys()))
                    ->orWhereIn('id', $myDirectAccess->keys())
                    ->orWhere('visibility', 'public');
            })
            ->with(['groups:id,name', 'creator:id,name'])
            ->when(
                $request->filled('search'),
                fn ($query) => $query->where(function ($q) use ($request) {
                    $search = $request->string('search')->toString();
                    $q->where('name', 'like', "%{$search}%")->orWhere('login', 'like', "%{$search}%");
                }),
            )
            ->when(
                $request->filled('sort') && in_array($request->input('sort'), self::SORTABLE_COLUMNS, true),
                fn ($query) => $query->orderBy($request->input('sort'), $request->string('direction')->toString() === 'desc' ? 'desc' : 'asc'),
                fn ($query) => $query->orderBy('name'),
            )
            ->paginate(15)
            ->withQueryString();

        return Inertia::render(Vault::page('passwords/Index'), [
            'credentials' => [
                'data' => $credentials->through(fn (Credential $credential) => $this->presentSummary($credential, $myAccess, $myDirectAccess, $user))->items(),
                'meta' => $this->paginationMeta($credentials),
            ],
            'query' => (object) $request->only(['search', 'sort', 'direction', 'page']),
        ]);
    }

    public function create(Request $request): Response
    {
        return Inertia::render(Vault::page('passwords/Create'), [
            'manageableGroups' => $this->manageableGroups($request->user()),
            // The credential doesn't exist yet, so there's no "already granted" to exclude —
            // every user is a candidate for an initial direct-access grant.
            'availableUsersForAccess' => $this->availableUsersForDirectAccess(),
        ]);
    }

    public function store(StoreCredentialRequest $request): RedirectResponse
    {
        $data = $request->validated();
        $user = $request->user();
        $groupIds = $data['group_ids'] ?? [];

        // "Create" = 'manage' on EACH group the credential is put into (otherwise you could
        // drop a credential into a group where you only have 'view').
        foreach ($groupIds as $groupId) {
            $group = CredentialGroup::findOrFail($groupId);
            abort_unless($group->accessLevelFor($user) === CredentialGroup::ACCESS_MANAGE, 403);
        }

        $credential = Credential::create([
            ...collect($data)->except(['group_ids', 'direct_access'])->all(),
            'created_by_id' => $user->getKey(),
        ]);

        if ($groupIds) {
            $credential->groups()->attach($groupIds);
        }

        // Initial direct-access grants, chosen at creation time (before the credential has its
        // own Show page to manage them from) — same flow/event as CredentialController::addAccess().
        $directAccess = collect($data['direct_access'] ?? [])
            ->filter(fn ($row) => filled($row['user_id'] ?? null))
            ->unique('user_id');

        foreach ($directAccess as $row) {
            $credential->directUsers()->attach($row['user_id'], ['access_level' => $row['access_level']]);

            $grantedUser = Vault::userQuery()->find($row['user_id']);
            if ($grantedUser) {
                event(new CredentialAccessGranted($credential, $grantedUser, $row['access_level']));
            }
        }

        $this->refreshIcon($credential);

        return Redirect::route(Vault::routeName('passwords', 'show'), $credential->id)->with('success', 'Пароль створено.');
    }

    public function show(Request $request, Credential $credential): Response
    {
        $accessLevel = $credential->accessLevelFor($request->user());
        abort_unless($accessLevel !== null, 403);

        return Inertia::render(Vault::page('passwords/Show'), [
            'credential' => $this->presentDetail($credential, $accessLevel),
            'availableUsersForAccess' => $accessLevel === CredentialGroup::ACCESS_MANAGE
                ? $this->availableUsersForDirectAccess($credential)
                : [],
        ]);
    }

    /** Grant one specific user direct access to this credential, without any group. */
    public function addAccess(Request $request, Credential $credential): RedirectResponse
    {
        abort_unless($credential->canManage($request->user()), 403);

        $validated = $request->validate([
            'user_id' => ['required', Rule::exists(Vault::usersTable(), 'id')],
            'access_level' => ['required', Rule::in(CredentialGroup::ACCESS_LEVELS)],
        ]);

        if ($credential->directUsers()->wherePivot('user_id', $validated['user_id'])->exists()) {
            return back()->with('error', 'Цей користувач вже має доступ до цього пароля.');
        }

        $credential->directUsers()->attach($validated['user_id'], ['access_level' => $validated['access_level']]);

        $grantedUser = Vault::userQuery()->find($validated['user_id']);
        if ($grantedUser) {
            event(new CredentialAccessGranted($credential, $grantedUser, $validated['access_level']));
        }

        return back()->with('success', 'Доступ надано.');
    }

    public function updateAccess(Request $request, Credential $credential, $user): RedirectResponse
    {
        abort_unless($credential->canManage($request->user()), 403);

        $validated = $request->validate([
            'access_level' => ['required', Rule::in(CredentialGroup::ACCESS_LEVELS)],
        ]);

        $userId = is_object($user) ? $user->getKey() : $user;
        $credential->directUsers()->updateExistingPivot($userId, ['access_level' => $validated['access_level']]);

        return back()->with('success', 'Рівень доступу оновлено.');
    }

    public function removeAccess(Request $request, Credential $credential, $user): RedirectResponse
    {
        abort_unless($credential->canManage($request->user()), 403);

        $userId = is_object($user) ? $user->getKey() : $user;
        $credential->directUsers()->detach($userId);

        return back()->with('success', 'Доступ прибрано.');
    }

    public function edit(Request $request, Credential $credential): Response
    {
        $accessLevel = $credential->accessLevelFor($request->user());
        abort_unless(CredentialGroup::accessRank($accessLevel) >= CredentialGroup::accessRank(CredentialGroup::ACCESS_EDIT), 403);

        return Inertia::render(Vault::page('passwords/Edit'), [
            'credential' => $this->presentDetail($credential, $accessLevel),
            'manageableGroups' => $this->manageableGroups($request->user()),
        ]);
    }

    public function update(UpdateCredentialRequest $request, Credential $credential): RedirectResponse
    {
        $data = $request->validated();
        $user = $request->user();

        $credential->fill(collect($data)->except('group_ids')->all());
        $credential->save();

        if ($credential->wasChanged('url')) {
            $this->refreshIcon($credential);
        }

        if (array_key_exists('group_ids', $data)) {
            $currentGroupIds = $credential->groups()->pluck('credential_groups.id')->all();
            $incoming = $data['group_ids'] ?? [];
            if (array_diff($incoming, $currentGroupIds) || array_diff($currentGroupIds, $incoming)) {
                // Changing the group set is a "manage" action — needs 'manage' on the credential
                // overall AND on each NEW group it's added to.
                abort_unless($credential->canManage($user), 403);
                foreach ($incoming as $groupId) {
                    $group = CredentialGroup::findOrFail($groupId);
                    abort_unless($group->accessLevelFor($user) === CredentialGroup::ACCESS_MANAGE, 403);
                }
                $credential->groups()->sync($incoming);
            }
        }

        return Redirect::route(Vault::routeName('passwords', 'show'), $credential->id)->with('success', 'Пароль оновлено.');
    }

    public function destroy(Request $request, Credential $credential): RedirectResponse
    {
        abort_unless($credential->canManage($request->user()), 403);

        $credential->delete();

        return Redirect::route(Vault::routeName('passwords', 'index'))->with('success', 'Пароль видалено.');
    }

    /** Attaching files — same trust level as notes/password: 'edit' is enough. */
    public function storeAttachment(Request $request, Credential $credential): RedirectResponse
    {
        abort_unless($credential->canEdit($request->user()), 403);

        $validated = $request->validate([
            'files' => ['required', 'array'],
            'files.*' => ['file', 'max:20480'],
        ]);

        foreach ($validated['files'] as $file) {
            $credential->addMedia($file)->toMediaCollection('attachments');
        }

        return back()->with('success', 'Файли додано.');
    }

    public function destroyAttachment(Request $request, Credential $credential, int $mediaId): RedirectResponse
    {
        abort_unless($credential->canEdit($request->user()), 403);

        $credential->media()->findOrFail($mediaId)->delete();

        return back()->with('success', 'Файл видалено.');
    }

    /** Fetch & cache the service favicon once (on create / url change). Best-effort. */
    private function refreshIcon(Credential $credential): void
    {
        if (! filled($credential->url)) {
            return;
        }

        $binary = FaviconFetcher::fetch($credential->url);
        if ($binary !== null) {
            $credential->addMediaFromString($binary)->usingFileName('icon.png')->toMediaCollection('icon');
        }
    }

    /** Groups the user MAY add credentials to — only where they have 'manage'. */
    private function manageableGroups($user)
    {
        return CredentialGroup::query()
            ->whereHas('members', fn ($q) => $q->where('credential_group_members.user_id', $user->getKey())
                ->where('credential_group_members.access_level', CredentialGroup::ACCESS_MANAGE))
            ->orderBy('name')
            ->get(['id', 'name']);
    }

    private function presentSummary(Credential $credential, Collection $myAccess, Collection $myDirectAccess, $user): array
    {
        $level = null;
        foreach ($credential->groups as $group) {
            $groupLevel = $myAccess->get($group->id);
            if (CredentialGroup::accessRank($groupLevel) > CredentialGroup::accessRank($level)) {
                $level = $groupLevel;
            }
        }

        $directLevel = $myDirectAccess->get($credential->id);
        if (CredentialGroup::accessRank($directLevel) > CredentialGroup::accessRank($level)) {
            $level = $directLevel;
        }

        if ($credential->visibility === 'public' && $level === null) {
            $isCreator = (int) $credential->created_by_id === (int) $user->getKey();
            $level = $isCreator ? CredentialGroup::ACCESS_MANAGE : CredentialGroup::ACCESS_VIEW;
        }

        return [
            'id' => $credential->id,
            'name' => $credential->name,
            'login' => $credential->login,
            'url' => $credential->url,
            'visibility' => $credential->visibility,
            'icon_url' => $credential->iconUrl(),
            'groups' => $credential->groups->map(fn (CredentialGroup $group) => ['id' => $group->id, 'name' => $group->name])->values(),
            'access_level' => $level,
            'created_by' => $credential->creator?->only(['id', 'name']),
            'created_at' => $credential->created_at?->toDateString(),
        ];
    }

    /**
     * Users who may be granted direct access — everyone else, minus those who already have it.
     * `$credential` is omitted on the create form, where there's nothing to exclude yet.
     */
    private function availableUsersForDirectAccess(?Credential $credential = null)
    {
        $existingIds = $credential ? $credential->directUsers->map->getKey()->all() : [];

        return Vault::availableUsersQuery()
            ->whereNotIn(Vault::userQuery()->getModel()->getQualifiedKeyName(), $existingIds ?: [0])
            ->orderBy('name')
            ->get(['id', 'name', 'email']);
    }

    private function presentDetail(Credential $credential, string $accessLevel): array
    {
        $credential->load('groups:id,name', 'creator:id,name', 'directUsers:id,name,email');

        return [
            'id' => $credential->id,
            'name' => $credential->name,
            'login' => $credential->login,
            // 'view' literally means "allowed to see the password" — the page itself already
            // requires canView, so secrets go into props as-is (no extra reveal request).
            'password' => $credential->password,
            'totp_secret' => $credential->totp_secret,
            'url' => $credential->url,
            'visibility' => $credential->visibility,
            'icon_url' => $credential->iconUrl(),
            'notes' => $credential->notes,
            'custom_fields' => $credential->custom_fields ?? [],
            'attachments' => $credential->attachmentsList(),
            'groups' => $credential->groups->map(fn (CredentialGroup $group) => ['id' => $group->id, 'name' => $group->name])->values(),
            'direct_users' => $credential->directUsers->map(fn ($directUser) => [
                'id' => $directUser->getKey(),
                'name' => $directUser->name,
                'email' => $directUser->email,
                'access_level' => $directUser->pivot->access_level,
            ])->values(),
            'access_level' => $accessLevel,
            'created_by' => $credential->creator?->only(['id', 'name']),
            'created_at' => $credential->created_at?->toDateString(),
        ];
    }

    private function paginationMeta($paginator): array
    {
        return [
            'current_page' => $paginator->currentPage(),
            'last_page' => $paginator->lastPage(),
            'per_page' => $paginator->perPage(),
            'total' => $paginator->total(),
            'from' => $paginator->firstItem(),
            'to' => $paginator->lastItem(),
        ];
    }
}
