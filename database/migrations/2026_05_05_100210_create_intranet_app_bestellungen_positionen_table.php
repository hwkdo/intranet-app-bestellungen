<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('intranet_app_bestellungen_positionen', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('bestellung_id')
                ->constrained('intranet_app_bestellungen')
                ->cascadeOnDelete();
            $table->foreignId('art_id')
                ->nullable()
                ->constrained('intranet_app_bestellungen_arten')
                ->nullOnDelete();
            $table->unsignedSmallInteger('nr')->default(1);
            $table->decimal('menge', 8, 2)->default(1);
            $table->string('einheit')->nullable();
            $table->string('art_nr')->nullable();
            $table->string('oberbegriff')->nullable();
            $table->string('bezeichnung');
            $table->decimal('preis', 12, 2)->default(0);
            $table->json('anlagen')->nullable();
            $table->string('file')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('intranet_app_bestellungen_positionen');
    }
};
