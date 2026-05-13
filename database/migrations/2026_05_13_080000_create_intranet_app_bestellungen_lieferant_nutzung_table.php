<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('intranet_app_bestellungen_lieferant_nutzung', function (Blueprint $table): void {
            $table->string('lieferantennummer')->primary();
            $table->unsignedInteger('legacy_bestellungen_count')->default(0);
            $table->unsignedInteger('v3_bestellungen_count')->default(0);
            $table->timestamp('legacy_synced_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('intranet_app_bestellungen_lieferant_nutzung');
    }
};
