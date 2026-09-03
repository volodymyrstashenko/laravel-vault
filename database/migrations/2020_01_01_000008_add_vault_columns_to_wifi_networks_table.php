<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Thevps\Vault\Vault;

/**
 * Patches a pre-existing host `wifi_networks` table. hasColumn-guarded → no-op on a fresh
 * install (migration 000005 built the full schema).
 *
 * A host that had the strogaz-style table before adopting the package has: ssid, password,
 * security, is_hidden, description, location_id. This adds: visibility ('public' default —
 * current "visible to everyone" behaviour), institution_id, bands, notes (backfilled from
 * `description` if present), location (free text — the old `location_id` FK is left in place
 * as an orphan column the package never reads), created_by_id.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('wifi_networks')) {
            return;
        }

        Schema::table('wifi_networks', function (Blueprint $table) {
            if (! Schema::hasColumn('wifi_networks', 'visibility')) {
                $table->string('visibility')->default('public')->after('id');
            }
            if (! Schema::hasColumn('wifi_networks', 'institution_id')) {
                $table->unsignedBigInteger('institution_id')->nullable()->index()->after('id');
            }
            if (! Schema::hasColumn('wifi_networks', 'bands')) {
                $table->text('bands')->nullable()->after('is_hidden');
            }
            if (! Schema::hasColumn('wifi_networks', 'notes')) {
                $table->text('notes')->nullable();
            }
            if (! Schema::hasColumn('wifi_networks', 'location')) {
                $table->string('location')->nullable();
            }
            if (! Schema::hasColumn('wifi_networks', 'created_by_id')) {
                $table->foreignId('created_by_id')->nullable()->after('id')->constrained(Vault::usersTable())->nullOnDelete();
            }
        });

        // One-time backfill: carry the old free-text `description` into `notes`.
        if (Schema::hasColumn('wifi_networks', 'description') && Schema::hasColumn('wifi_networks', 'notes')) {
            DB::table('wifi_networks')->whereNull('notes')->whereNotNull('description')
                ->update(['notes' => DB::raw('description')]);
        }
    }

    public function down(): void
    {
        if (! Schema::hasTable('wifi_networks')) {
            return;
        }

        Schema::table('wifi_networks', function (Blueprint $table) {
            foreach (['visibility', 'institution_id', 'bands', 'notes', 'location'] as $column) {
                if (Schema::hasColumn('wifi_networks', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
