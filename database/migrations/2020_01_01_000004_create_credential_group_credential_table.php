<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('credential_group_credential')) {
            return;
        }

        // A credential can be in several groups at once (and vice versa) — many-to-many, no
        // extra columns on the link (access is decided purely through credential_group_members
        // of that group, not this table).
        Schema::create('credential_group_credential', function (Blueprint $table) {
            $table->id();
            $table->foreignId('credential_group_id')->constrained('credential_groups')->cascadeOnDelete();
            $table->foreignId('credential_id')->constrained('credentials')->cascadeOnDelete();
            $table->timestamps();

            // Explicit short name — the auto-generated one exceeds MySQL's 64-char identifier limit.
            $table->unique(['credential_group_id', 'credential_id'], 'cg_credential_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('credential_group_credential');
    }
};
