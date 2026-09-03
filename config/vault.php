<?php

return [
    /*
     |--------------------------------------------------------------------------
     | User model
     |--------------------------------------------------------------------------
     | Eloquent model used for group members, credential/network creators. Must
     | expose `id`, `name` and (optionally) `email`. Set this to your app's user
     | model after publishing the config.
     */
    'user_model' => 'App\Models\User',

    /*
     |--------------------------------------------------------------------------
     | Users table
     |--------------------------------------------------------------------------
     | Table name the package's own migrations `->constrained()` against for
     | created_by_id / user_id columns. Default `users`; set before running
     | `php artisan migrate` on a fresh install if yours differs (e.g. a
     | prefixed `core_users`).
     */
    'users_table' => 'users',

    /*
     |--------------------------------------------------------------------------
     | Routing
     |--------------------------------------------------------------------------
     | `route_middleware` — middleware group every Vault route is wrapped in.
     |                      Needs `web` (session/CSRF/Inertia) + the host's auth
     |                      stack, + whatever resolves any route-parameter models
     |                      referenced by the path prefixes below.
     | `routes`           — URL path prefix AND route-name prefix for each of the
     |                      three areas. Route names are `<prefix>.<action>`
     |                      (e.g. `passwords.index`, `wifi.quick`).
     */
    'route_middleware' => ['web', 'auth'],

    'routes' => [
        'passwords' => 'passwords',
        'password_groups' => 'password-groups',
        'wifi' => 'wifi',
    ],

    /*
     |--------------------------------------------------------------------------
     | Inertia page prefix
     |--------------------------------------------------------------------------
     | Controllers render `{prefix}passwords/Index`, `{prefix}wifi/Index` etc.
     | Published Vue pages live at `resources/js/pages/{passwords,password-groups,
     | wifi}/*.vue`.
     */
    'inertia_page_prefix' => '',

    /*
     |--------------------------------------------------------------------------
     | Wi-Fi management gate (optional)
     |--------------------------------------------------------------------------
     | A `public`-visibility Wi-Fi network is visible to every authenticated
     | user; who may CREATE / EDIT / DELETE such a network is decided here.
     | Default: any authenticated user (like creating a credential group).
     | `group`-visibility networks are always managed by their group's `manage`
     | members and ignore this gate.
     |
     | Signature: callable(\Illuminate\Contracts\Auth\Authenticatable $user): bool
     | Example:   'wifi_manage_gate' => fn ($user) => $user->can('wifi.manage'),
     */
    'wifi_manage_gate' => null,

    /*
     |--------------------------------------------------------------------------
     | Multi-tenancy (optional)
     |--------------------------------------------------------------------------
     | Single-tenant by default — leave `institution_resolver` null and the
     | nullable `institution_id` column present on every table simply sits
     | unused. A multi-tenant host sets this to a callable returning the current
     | tenant id (or null — e.g. a console command); Credential / CredentialGroup
     | / WifiNetwork then apply a global scope filtering on it and stamp new rows.
     |
     | Example: 'institution_resolver' => fn () => request()->route('institution')?->id,
     */
    'institution_resolver' => null,

    /*
     |--------------------------------------------------------------------------
     | Available users (optional)
     |--------------------------------------------------------------------------
     | The group Show page passes an `availableUsers` list (member-add picker)
     | sourced from `Vault::availableUsersQuery()`, defaulting to every user in
     | `user_model`. A host scoping users per-tenant can override just this.
     |
     | Signature: callable(): \Illuminate\Database\Eloquent\Builder
     */
    'available_users_resolver' => null,

    /*
     |--------------------------------------------------------------------------
     | Favicon source
     |--------------------------------------------------------------------------
     | A credential with a `url` gets a service icon fetched ONCE (on create /
     | url change) and cached via media library — not from each viewer's browser.
     | `{domain}` and `{size}` are substituted. The default is Google's public
     | favicon aggregator (more reliable than a site's own /favicon.ico).
     */
    'favicon_endpoint' => 'https://www.google.com/s2/favicons?domain={domain}&sz={size}',
    'favicon_size' => 128,
];
