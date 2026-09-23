<?php

namespace Thevps\Vault;

use Illuminate\Database\Eloquent\Model;
use Thevps\Vault\Models\Credential;

class Vault
{
    /**
     * FQCN of the host application's user model, from `config('vault.user_model')`.
     *
     * The fallback is a string literal on purpose — the package must never `use` or hard-
     * reference `App\Models\User` (not guaranteed to exist; that coupling is what this package
     * removes). Hosts always publish config/vault.php with a real class.
     */
    public static function userModel(): string
    {
        return config('vault.user_model') ?: 'App\Models\User';
    }

    /** A fresh query builder on the host user model. */
    public static function userQuery()
    {
        /** @var class-string<Model> $class */
        $class = static::userModel();

        return $class::query();
    }

    /** Table name the package's own migrations constrain their user FKs against. */
    public static function usersTable(): string
    {
        return config('vault.users_table') ?: 'users';
    }

    /**
     * Current tenant id from `config('vault.institution_resolver')`, or null when the host
     * hasn't configured one (single-tenant — every query then runs unscoped) or the resolver
     * itself returns null (e.g. a console command with no request/route to read).
     */
    public static function currentInstitutionId(): ?int
    {
        $resolver = config('vault.institution_resolver');

        return $resolver ? $resolver() : null;
    }

    /**
     * Query for the "available users" list handed to the group Show page (member-add picker) —
     * `config('vault.available_users_resolver')` when the host set one, else every user in
     * `user_model`.
     */
    public static function availableUsersQuery()
    {
        $resolver = config('vault.available_users_resolver');

        return $resolver ? $resolver() : static::userQuery();
    }

    /**
     * Host-specific records that point AT this credential (e.g. an asset's `credential_id`) —
     * `config('vault.linked_assets_resolver')`, or `[]` when the host hasn't configured one.
     * The package itself has no `Asset`/device model to query.
     */
    public static function linkedAssetsFor(Credential $credential): array
    {
        $resolver = config('vault.linked_assets_resolver');

        return $resolver ? $resolver($credential) : [];
    }

    /** Whether $user may manage `public`-visibility Wi-Fi networks (create/edit/delete). */
    public static function userCanManagePublicWifi($user): bool
    {
        $gate = config('vault.wifi_manage_gate');

        return $gate ? (bool) $gate($user) : true;
    }

    /** Resolve the Inertia page name for one of the package's pages. */
    public static function page(string $name): string
    {
        return config('vault.inertia_page_prefix', '').$name;
    }

    /**
     * Route name for an action in one of the three areas ('passwords' | 'password_groups' |
     * 'wifi'), honouring the configurable name prefixes in config('vault.routes').
     * e.g. routeName('passwords', 'show') => 'passwords.show'.
     */
    public static function routeName(string $area, string $action): string
    {
        $prefixes = config('vault.routes', []);

        return ($prefixes[$area] ?? $area).'.'.$action;
    }
}
