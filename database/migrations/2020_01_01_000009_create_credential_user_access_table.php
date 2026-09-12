<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Thevps\Vault\Vault;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('credential_user_access')) {
            return;
        }

        // Direct, per-credential access grants — alongside group membership, not instead of it.
        // Lets a single credential be shared with one specific person without having to create
        // (or reuse) a whole group just for them.
        Schema::create('credential_user_access', function (Blueprint $table) {
            $table->id();
            $table->foreignId('credential_id')->constrained('credentials')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained(Vault::usersTable())->cascadeOnDelete();
            // view | edit | manage (CredentialGroup::ACCESS_*) — same vocabulary as group membership.
            $table->string('access_level');
            $table->timestamps();

            $table->unique(['credential_id', 'user_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('credential_user_access');
    }
};
