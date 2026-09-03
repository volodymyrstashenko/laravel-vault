<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Thevps\Vault\Vault;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('credential_group_members')) {
            return;
        }

        Schema::create('credential_group_members', function (Blueprint $table) {
            $table->id();
            $table->foreignId('credential_group_id')->constrained('credential_groups')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained(Vault::usersTable())->cascadeOnDelete();
            // view | edit | manage (CredentialGroup::ACCESS_*) — this user's level within THIS
            // group. One level per (group, user).
            $table->string('access_level');
            $table->timestamps();

            $table->unique(['credential_group_id', 'user_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('credential_group_members');
    }
};
