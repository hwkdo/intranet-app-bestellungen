<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('intranet_app_bestellungen_kassenbons', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')
                ->constrained('users', indexName: 'iab_kb_user_fk')
                ->cascadeOnDelete();
            $table->foreignId('bestellung_id')
                ->nullable()
                ->constrained('intranet_app_bestellungen', indexName: 'iab_kb_bestellung_fk')
                ->nullOnDelete();
            $table->string('datei_pfad');
            $table->string('original_name')->nullable();
            $table->string('mime')->nullable();
            $table->string('extraction_status')->default('pending');
            $table->text('extraction_error')->nullable();
            $table->json('extraction_payload')->nullable();
            $table->timestamp('extracted_at')->nullable();
            $table->string('belegnummer')->nullable();
            $table->date('belegdatum')->nullable();
            $table->decimal('nettobetrag', 12, 2)->nullable();
            $table->decimal('steuerbetrag', 12, 2)->nullable();
            $table->decimal('bruttobetrag', 12, 2)->nullable();
            $table->string('zahlungsart')->nullable();
            $table->string('d3id_rechnung')->nullable();
            $table->timestamp('d3_rechnung_pushed_at')->nullable();
            $table->timestamps();

            $table->index('extraction_status', 'iab_kb_extraction_status_idx');
            $table->index('d3id_rechnung', 'iab_kb_d3_rechnung_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('intranet_app_bestellungen_kassenbons');
    }
};
