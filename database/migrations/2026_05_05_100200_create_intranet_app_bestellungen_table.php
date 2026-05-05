<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('intranet_app_bestellungen', function (Blueprint $table): void {
            $table->id();
            $table->string('nummer')->unique();
            $table->string('status')->default('Zur Freigabe')->index();
            $table->unsignedTinyInteger('freigabe_stufe_aktuell')->default(1);

            $table->string('lieferantennummer')->nullable()->index();
            $table->string('lieferantenname')->nullable();
            $table->json('lieferanschrift')->nullable();

            $table->string('kostenstelle')->index();
            $table->unsignedSmallInteger('haushaltsjahr');
            $table->string('typ')->default('intern');
            $table->string('betreff')->nullable();
            $table->longText('begruendung')->nullable();
            $table->json('kontierung')->nullable();

            $table->decimal('gesamtbetrag', 12, 2)->default(0);

            $table->foreignId('user_id')->constrained('users')->restrictOnDelete();
            $table->foreignId('freigeber_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('besteller_id')->nullable()->constrained('users')->nullOnDelete();

            $table->string('d3id')->nullable()->index();
            $table->timestamp('d3_pushed_at')->nullable();
            $table->timestamp('bestellt_at')->nullable();

            $table->foreignId('wiederholt_von_id')
                ->nullable()
                ->constrained('intranet_app_bestellungen')
                ->nullOnDelete();

            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('intranet_app_bestellungen');
    }
};
