<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Thevps\Vault\Vault;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('credentials')) {
            return;
        }

        Schema::create('credentials', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('institution_id')->nullable()->index();
            // 'group' — visible only to members of a group this credential is attached to.
            // 'public' — visible (view) to every authenticated user; editing still needs a group.
            $table->string('visibility')->default('group');
            $table->string('name');
            $table->string('login')->nullable();
            // password / totp_secret / notes / custom_fields — Eloquent 'encrypted' casts on the
            // model, so the DB stores only the ciphertext envelope.
            $table->text('password')->nullable();
            $table->text('totp_secret')->nullable();
            $table->string('url')->nullable();
            $table->text('notes')->nullable();
            $table->text('custom_fields')->nullable();
            $table->foreignId('created_by_id')->nullable()->constrained(Vault::usersTable())->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('credentials');
    }
};
