<?php

use Illuminate\Support\Facades\Route;
use Thevps\Vault\Http\Controllers\CredentialController;
use Thevps\Vault\Http\Controllers\CredentialGroupController;
use Thevps\Vault\Http\Controllers\WifiNetworkController;

/*
 | Loaded by VaultServiceProvider inside a group applying config('vault.route_middleware').
 |
 | Three independent areas, each with its own URL prefix AND route-name prefix from
 | config('vault.routes'):
 |   passwords.*        — the password manager (group ACL, optional `public` visibility)
 |   password-groups.*  — access groups (create = anyone authenticated, become 'manage')
 |   wifi.*             — the Wi-Fi directory (default `public`, optional `group` visibility)
 |
 | Access to a specific credential / group / network is decided in the controller
 | (membership + visibility), not by a global permission — mirror the host's auth stack via
 | route_middleware, and gate `public` Wi-Fi management via config('vault.wifi_manage_gate').
*/

$names = config('vault.routes');

Route::prefix($names['passwords'])->name($names['passwords'].'.')->group(function () {
    Route::get('/', [CredentialController::class, 'index'])->name('index');
    Route::get('/create', [CredentialController::class, 'create'])->name('create');
    Route::post('/', [CredentialController::class, 'store'])->name('store');
    Route::get('/{credential}', [CredentialController::class, 'show'])->name('show');
    Route::get('/{credential}/edit', [CredentialController::class, 'edit'])->name('edit');
    Route::put('/{credential}', [CredentialController::class, 'update'])->name('update');
    Route::delete('/{credential}', [CredentialController::class, 'destroy'])->name('destroy');

    Route::post('/{credential}/attachments', [CredentialController::class, 'storeAttachment'])->name('attachments.store');
    Route::delete('/{credential}/attachments/{mediaId}', [CredentialController::class, 'destroyAttachment'])->name('attachments.destroy');
});

Route::prefix($names['password_groups'])->name($names['password_groups'].'.')->group(function () {
    Route::get('/', [CredentialGroupController::class, 'index'])->name('index');
    Route::get('/create', [CredentialGroupController::class, 'create'])->name('create');
    Route::post('/', [CredentialGroupController::class, 'store'])->name('store');
    Route::get('/{credential_group}', [CredentialGroupController::class, 'show'])->name('show');
    Route::put('/{credential_group}', [CredentialGroupController::class, 'update'])->name('update');
    Route::delete('/{credential_group}', [CredentialGroupController::class, 'destroy'])->name('destroy');

    Route::post('/{credential_group}/members', [CredentialGroupController::class, 'addMember'])->name('members.store');
    Route::put('/{credential_group}/members/{user}', [CredentialGroupController::class, 'updateMember'])->name('members.update');
    Route::delete('/{credential_group}/members/{user}', [CredentialGroupController::class, 'removeMember'])->name('members.destroy');
});

Route::prefix($names['wifi'])->name($names['wifi'].'.')->group(function () {
    Route::get('/', [WifiNetworkController::class, 'index'])->name('index');
    Route::get('/quick', [WifiNetworkController::class, 'quick'])->name('quick');
    Route::get('/{wifi_network}/windows-profile', [WifiNetworkController::class, 'windowsProfile'])->name('windows-profile');
    Route::post('/', [WifiNetworkController::class, 'store'])->name('store');
    Route::put('/{wifi_network}', [WifiNetworkController::class, 'update'])->name('update');
    Route::delete('/{wifi_network}', [WifiNetworkController::class, 'destroy'])->name('destroy');
});
