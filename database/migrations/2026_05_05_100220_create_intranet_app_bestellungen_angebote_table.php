<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('intranet_app_bestellungen_angebote', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('bestellung_id')
                ->constrained('intranet_app_bestellungen')
                ->cascadeOnDelete();
            $table->foreignId('user_id')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();
            $table->string('typ')->default('angebot');
            $table->string('lieferantenname')->nullable();
            $table->string('nummer')->nullable();
            $table->decimal('betrag', 12, 2)->nullable();
            $table->text('begruendung')->nullable();
            $table->string('pdf_path')->nullable();
            $table->string('d3id')->nullable()->index();
            $table->timestamp('d3_pushed_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('intranet_app_bestellungen_angebote');
    }
};
