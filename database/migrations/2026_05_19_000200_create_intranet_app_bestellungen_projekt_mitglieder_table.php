<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('intranet_app_bestellungen_projekt_mitglieder', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('projekt_id')
                ->constrained('intranet_app_bestellungen_projekte')
                ->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['projekt_id', 'user_id'], 'iab_projekt_mitglieder_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('intranet_app_bestellungen_projekt_mitglieder');
    }
};
