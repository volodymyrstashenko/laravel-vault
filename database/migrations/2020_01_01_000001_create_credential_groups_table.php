<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Thevps\Vault\Vault;

return new class extends Migration
{
    public function up(): void
    {
        // Guard: on hosts that already had this table before adopting the package (the module
        // was previously copied straight into the project), this migration is a no-op.
        if (Schema::hasTable('credential_groups')) {
            return;
        }

        Schema::create('credential_groups', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('institution_id')->nullable()->index();
            $table->string('name');
            $table->text('description')->nullable();
            $table->foreignId('created_by_id')->nullable()->constrained(Vault::usersTable())->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('credential_groups');
    }
};
