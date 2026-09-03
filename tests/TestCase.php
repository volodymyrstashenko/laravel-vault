<?php

namespace Thevps\Vault\Tests;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Support\Facades\Schema;
use Inertia\ServiceProvider as InertiaServiceProvider;
use Orchestra\Testbench\TestCase as Orchestra;
use Spatie\MediaLibrary\MediaLibraryServiceProvider;
use Thevps\Vault\VaultServiceProvider;

abstract class TestCase extends Orchestra
{
    protected function getPackageProviders($app): array
    {
        return [
            MediaLibraryServiceProvider::class,
            InertiaServiceProvider::class,
            VaultServiceProvider::class,
        ];
    }

    protected function defineEnvironment($app): void
    {
        // Generated fresh per run — never a committed/static value (secret scanners).
        $app['config']->set('app.key', 'base64:'.base64_encode(random_bytes(32)));
        $app['config']->set('database.default', 'testing');
        $app['config']->set('vault.user_model', TestUser::class);
        $app['config']->set('vault.route_middleware', ['web']);
        $app['config']->set('inertia.testing.ensure_pages_exist', false);
        // Minimal root view so Inertia::render() can produce an initial HTML response.
        $app['config']->set('view.paths', array_merge($app['config']->get('view.paths', []), [__DIR__.'/stubs']));
    }

    protected function defineDatabaseMigrations(): void
    {
        Schema::create('users', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('email')->nullable();
            $table->timestamps();
        });

        // spatie/laravel-medialibrary ships its migration as a publishable .stub — recreate the
        // `media` table here so HasMedia models work under the package test suite.
        Schema::create('media', function (Blueprint $table) {
            $table->id();
            $table->morphs('model');
            $table->uuid()->nullable()->unique();
            $table->string('collection_name');
            $table->string('name');
            $table->string('file_name');
            $table->string('mime_type')->nullable();
            $table->string('disk');
            $table->string('conversions_disk')->nullable();
            $table->unsignedBigInteger('size');
            $table->json('manipulations');
            $table->json('custom_properties');
            $table->json('generated_conversions');
            $table->json('responsive_images');
            $table->unsignedInteger('order_column')->nullable()->index();
            $table->nullableTimestamps();
        });

        $this->loadMigrationsFrom(__DIR__.'/../database/migrations');
    }
}

class TestUser extends Authenticatable
{
    protected $table = 'users';

    protected $guarded = [];

    public $timestamps = true;
}
