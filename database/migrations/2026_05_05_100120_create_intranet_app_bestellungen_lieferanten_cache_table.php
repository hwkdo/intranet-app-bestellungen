<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('intranet_app_bestellungen_lieferanten_cache', function (Blueprint $table): void {
            $table->id();
            $table->string('lieferantennummer');
            $table->string('lieferantenname');
            $table->string('strasse')->nullable();
            $table->string('hausnummer')->nullable();
            $table->string('plz')->nullable();
            $table->string('ort')->nullable();
            $table->timestamp('synced_at')->nullable();
            $table->timestamps();

            $table->unique('lieferantennummer', 'iab_lieferanten_cache_nr_unique');
            $table->index('lieferantenname', 'iab_lieferanten_cache_name_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('intranet_app_bestellungen_lieferanten_cache');
    }
};
