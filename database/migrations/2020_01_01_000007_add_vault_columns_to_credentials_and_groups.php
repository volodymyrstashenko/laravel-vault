<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Patches pre-existing host tables (the module was copied straight into the app before adopting
 * the package). Each column is hasColumn-guarded, so on a fresh install — where migration
 * 000003 already created the full schema — this is a no-op.
 *
 *  - credentials.visibility     — default 'group' preserves the current strict-ACL behaviour.
 *  - credentials.institution_id — see config('vault.institution_resolver'); unused single-tenant.
 *  - credential_groups.institution_id — same.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('credentials')) {
            Schema::table('credentials', function (Blueprint $table) {
                if (! Schema::hasColumn('credentials', 'visibility')) {
                    $table->string('visibility')->default('group')->after('id');
                }
                if (! Schema::hasColumn('credentials', 'institution_id')) {
                    $table->unsignedBigInteger('institution_id')->nullable()->index()->after('id');
                }
            });
        }

        if (Schema::hasTable('credential_groups') && ! Schema::hasColumn('credential_groups', 'institution_id')) {
            Schema::table('credential_groups', function (Blueprint $table) {
                $table->unsignedBigInteger('institution_id')->nullable()->index()->after('id');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('credentials')) {
            Schema::table('credentials', function (Blueprint $table) {
                foreach (['visibility', 'institution_id'] as $column) {
                    if (Schema::hasColumn('credentials', $column)) {
                        $table->dropColumn($column);
                    }
                }
            });
        }

        if (Schema::hasTable('credential_groups') && Schema::hasColumn('credential_groups', 'institution_id')) {
            Schema::table('credential_groups', function (Blueprint $table) {
                $table->dropColumn('institution_id');
            });
        }
    }
};
