<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Thevps\Vault\Vault;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('wifi_networks')) {
            return;
        }

        Schema::create('wifi_networks', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('institution_id')->nullable()->index();
            // 'public' (default) — visible to every authenticated user, like a "where do I get
            // the Wi-Fi password" board. 'group' — visible only to members of an attached group.
            $table->string('visibility')->default('public');
            $table->string('ssid');
            // 'encrypted' cast on the model — ciphertext at rest even though the app shows it.
            $table->text('password')->nullable();
            // 'WPA' (covers WPA/WPA2/WPA3 — the QR encodes them the same), 'WEP', 'nopass'.
            $table->string('security')->default('WPA');
            // Hidden SSID (not broadcast) — affects the QR payload (H:true/false).
            $table->boolean('is_hidden')->default(false);
            // JSON array of frequency bands the network broadcasts on: any of '2.4', '5', '6'.
            // The list view shows a single icon keyed to the strongest band (6 > 5 > 2.4).
            $table->text('bands')->nullable();
            // Free text — "2nd floor, staff room". Deliberately not an FK: the package doesn't
            // know the host's locations table.
            $table->string('location')->nullable();
            $table->text('notes')->nullable();
            $table->foreignId('created_by_id')->nullable()->constrained(Vault::usersTable())->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('wifi_networks');
    }
};
