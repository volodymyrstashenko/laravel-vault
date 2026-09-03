<?php

namespace Thevps\Vault;

use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;

class VaultServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../config/vault.php', 'vault');
    }

    public function boot(): void
    {
        $this->loadMigrationsFrom(__DIR__.'/../database/migrations');
        $this->registerRoutes();

        if ($this->app->runningInConsole()) {
            $this->publishes([
                __DIR__.'/../config/vault.php' => config_path('vault.php'),
            ], 'laravel-vault-config');

            $this->publishes([
                __DIR__.'/../database/migrations' => database_path('migrations'),
            ], 'laravel-vault-migrations');

            // Vue/TS published straight into the host's resources/js tree — the host owns the
            // copies and adapts UI-kit imports to its stack. See README "Adaptation points".
            $this->publishes([
                __DIR__.'/../resources/js/components/credentials' => resource_path('js/components/credentials'),
                __DIR__.'/../resources/js/components/wifi' => resource_path('js/components/wifi'),
                __DIR__.'/../resources/js/pages/passwords' => resource_path('js/pages/passwords'),
                __DIR__.'/../resources/js/pages/password-groups' => resource_path('js/pages/password-groups'),
                __DIR__.'/../resources/js/pages/wifi' => resource_path('js/pages/wifi'),
                __DIR__.'/../resources/js/lib/credentials.ts' => resource_path('js/lib/credentials.ts'),
                __DIR__.'/../resources/js/lib/wifi.ts' => resource_path('js/lib/wifi.ts'),
                __DIR__.'/../resources/js/lib/totp.ts' => resource_path('js/lib/totp.ts'),
                __DIR__.'/../resources/js/lib/passwordGenerator.ts' => resource_path('js/lib/passwordGenerator.ts'),
                __DIR__.'/../resources/js/types/vault.ts' => resource_path('js/types/vault.ts'),
            ], 'laravel-vault-frontend');
        }
    }

    protected function registerRoutes(): void
    {
        Route::group([
            'middleware' => config('vault.route_middleware', ['web', 'auth']),
        ], function () {
            $this->loadRoutesFrom(__DIR__.'/../routes/vault.php');
        });
    }
}
