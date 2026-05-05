<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('intranet_app_bestellungen_kostenstellen_cache', function (Blueprint $table): void {
            $table->id();
            $table->string('kostenstelle');
            $table->string('bezeichnung')->nullable();
            $table->boolean('aktiv')->default(true);
            $table->timestamp('synced_at')->nullable();
            $table->timestamps();

            $table->unique('kostenstelle', 'iab_kostenstellen_cache_nr_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('intranet_app_bestellungen_kostenstellen_cache');
    }
};
