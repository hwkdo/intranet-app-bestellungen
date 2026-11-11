<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('intranet_app_bestellungen_auftragszeilen', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('bestellung_id')
                ->constrained('intranet_app_bestellungen', indexName: 'iab_az_bestellung_fk')
                ->cascadeOnDelete();
            $table->foreignId('angebot_id')
                ->nullable()
                ->constrained('intranet_app_bestellungen_angebote', indexName: 'iab_az_angebot_fk')
                ->nullOnDelete();
            $table->unsignedInteger('nr');
            $table->date('angebotsdatum')->nullable();
            $table->string('angebotsnummer')->nullable();
            $table->decimal('betrag_netto', 12, 2)->nullable();
            $table->decimal('betrag_brutto', 12, 2)->nullable();
            $table->text('freitext')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('intranet_app_bestellungen_auftragszeilen');
    }
};
