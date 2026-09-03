<?php

namespace Thevps\Vault\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Redirect;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;
use Thevps\Vault\Events\CredentialGroupAccessGranted;
use Thevps\Vault\Http\Requests\StoreCredentialGroupRequest;
use Thevps\Vault\Http\Requests\UpdateCredentialGroupRequest;
use Thevps\Vault\Models\CredentialGroup;
use Thevps\Vault\Vault;

/**
 * Credential groups — the unit of access (not a CRUD directory in itself). Anyone
 * authenticated can create one (like a Kanban board) and becomes its first 'manage' member.
 */
class CredentialGroupController extends Controller
{
    public function index(Request $request): Response
    {
        $groups = CredentialGroup::query()
            ->whereHas('members', fn ($q) => $q->where('credential_group_members.user_id', $request->user()->getKey()))
            ->withCount(['members', 'credentials'])
            ->with(['members' => fn ($q) => $q->where('credential_group_members.user_id', $request->user()->getKey())])
            ->orderBy('name')
            ->get();

        return Inertia::render(Vault::page('password-groups/Index'), [
            'groups' => $groups->map(fn (CredentialGroup $group) => $this->presentSummary($group))->values(),
        ]);
    }

    public function create(): Response
    {
        return Inertia::render(Vault::page('password-groups/Create'));
    }

    public function store(StoreCredentialGroupRequest $request): RedirectResponse
    {
        $group = CredentialGroup::create([
            ...$request->validated(),
            'created_by_id' => $request->user()->getKey(),
        ]);
        $group->members()->attach($request->user()->getKey(), ['access_level' => CredentialGroup::ACCESS_MANAGE]);

        return Redirect::route(Vault::routeName('password_groups', 'show'), $group->id)->with('success', 'Групу створено.');
    }

    public function show(Request $request, CredentialGroup $credential_group): Response
    {
        $accessLevel = $credential_group->accessLevelFor($request->user());
        abort_unless($accessLevel !== null, 403);

        $credential_group->load(['members', 'credentials' => fn ($q) => $q->orderBy('name')]);

        $memberIds = $credential_group->members->map->getKey()->all();

        return Inertia::render(Vault::page('password-groups/Show'), [
            'group' => [
                'id' => $credential_group->id,
                'name' => $credential_group->name,
                'description' => $credential_group->description,
                'access_level' => $accessLevel,
                'members' => $credential_group->members->map(fn ($member) => [
                    'id' => $member->getKey(),
                    'name' => $member->name,
                    'email' => $member->email,
                    'access_level' => $member->pivot->access_level,
                ])->values(),
                'credentials' => $credential_group->credentials->map(fn ($credential) => [
                    'id' => $credential->id,
                    'name' => $credential->name,
                    'login' => $credential->login,
                    'icon_url' => $credential->iconUrl(),
                ])->values(),
            ],
            'availableUsers' => Vault::availableUsersQuery()
                ->whereNotIn(Vault::userQuery()->getModel()->getQualifiedKeyName(), $memberIds ?: [0])
                ->orderBy('name')
                ->get(['id', 'name', 'email']),
        ]);
    }

    public function update(UpdateCredentialGroupRequest $request, CredentialGroup $credential_group): RedirectResponse
    {
        $credential_group->update($request->validated());

        return Redirect::route(Vault::routeName('password_groups', 'show'), $credential_group->id)->with('success', 'Групу збережено.');
    }

    public function destroy(Request $request, CredentialGroup $credential_group): RedirectResponse
    {
        abort_unless($credential_group->accessLevelFor($request->user()) === CredentialGroup::ACCESS_MANAGE, 403);

        // A credential left with no group at all becomes invisible to everyone (no admin bypass
        // in this module — deliberate) — so deletion is blocked until such credentials are
        // moved into another group or deleted themselves.
        $orphaned = $credential_group->credentials()
            ->whereDoesntHave('groups', fn ($q) => $q->where('credential_groups.id', '!=', $credential_group->id))
            ->count();
        if ($orphaned > 0) {
            return Redirect::route(Vault::routeName('password_groups', 'show'), $credential_group->id)
                ->with('error', "У цій групі є {$orphaned} пароль(і/ів), які не входять у жодну іншу групу. Перенесіть або видаліть їх перед видаленням групи.");
        }

        $credential_group->delete();

        return Redirect::route(Vault::routeName('password_groups', 'index'))->with('success', 'Групу видалено.');
    }

    public function addMember(Request $request, CredentialGroup $credential_group): RedirectResponse
    {
        abort_unless($credential_group->accessLevelFor($request->user()) === CredentialGroup::ACCESS_MANAGE, 403);

        $validated = $request->validate([
            'user_id' => ['required', Rule::exists(Vault::usersTable(), 'id')],
            'access_level' => ['required', Rule::in(CredentialGroup::ACCESS_LEVELS)],
        ]);

        if ($credential_group->members()->wherePivot('user_id', $validated['user_id'])->exists()) {
            return back()->with('error', 'Цей користувач вже є учасником групи.');
        }

        $credential_group->members()->attach($validated['user_id'], ['access_level' => $validated['access_level']]);

        $newMember = Vault::userQuery()->find($validated['user_id']);
        if ($newMember) {
            event(new CredentialGroupAccessGranted($credential_group, $newMember, $validated['access_level']));
        }

        return back()->with('success', 'Учасника додано.');
    }

    public function updateMember(Request $request, CredentialGroup $credential_group, $user): RedirectResponse
    {
        abort_unless($credential_group->accessLevelFor($request->user()) === CredentialGroup::ACCESS_MANAGE, 403);

        $validated = $request->validate([
            'access_level' => ['required', Rule::in(CredentialGroup::ACCESS_LEVELS)],
        ]);

        $userId = is_object($user) ? $user->getKey() : $user;
        $this->guardLastManager($credential_group, $userId, $validated['access_level']);

        $credential_group->members()->updateExistingPivot($userId, ['access_level' => $validated['access_level']]);

        return back()->with('success', 'Рівень доступу оновлено.');
    }

    public function removeMember(Request $request, CredentialGroup $credential_group, $user): RedirectResponse
    {
        abort_unless($credential_group->accessLevelFor($request->user()) === CredentialGroup::ACCESS_MANAGE, 403);

        $userId = is_object($user) ? $user->getKey() : $user;
        $this->guardLastManager($credential_group, $userId, null);

        $credential_group->members()->detach($userId);

        return back()->with('success', 'Учасника видалено.');
    }

    /** A group must keep at least one 'manage' member — otherwise nobody can run it. */
    private function guardLastManager(CredentialGroup $group, $targetUserId, ?string $newAccessLevel): void
    {
        $currentLevel = $group->members()->wherePivot('user_id', $targetUserId)->first()?->pivot?->access_level;
        if ($currentLevel !== CredentialGroup::ACCESS_MANAGE || $newAccessLevel === CredentialGroup::ACCESS_MANAGE) {
            return;
        }

        $managersCount = $group->members()->wherePivot('access_level', CredentialGroup::ACCESS_MANAGE)->count();
        abort_if($managersCount <= 1, 422, 'У групі має лишитись хоча б один учасник з правом «Керування».');
    }

    private function presentSummary(CredentialGroup $group): array
    {
        return [
            'id' => $group->id,
            'name' => $group->name,
            'description' => $group->description,
            'members_count' => $group->members_count,
            'credentials_count' => $group->credentials_count,
            'access_level' => $group->members->first()?->pivot?->access_level,
            'created_at' => $group->created_at?->toDateString(),
        ];
    }
}
