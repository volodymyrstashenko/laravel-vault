<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('credential_group_wifi_network')) {
            return;
        }

        // For `visibility='group'` Wi-Fi networks — same access-group mechanism as credentials.
        Schema::create('credential_group_wifi_network', function (Blueprint $table) {
            $table->id();
            $table->foreignId('credential_group_id')->constrained('credential_groups')->cascadeOnDelete();
            $table->foreignId('wifi_network_id')->constrained('wifi_networks')->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['credential_group_id', 'wifi_network_id'], 'cg_wifi_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('credential_group_wifi_network');
    }
};
