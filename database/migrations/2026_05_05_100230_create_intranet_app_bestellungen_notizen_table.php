<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('intranet_app_bestellungen_notizen', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('bestellung_id')
                ->constrained('intranet_app_bestellungen')
                ->cascadeOnDelete();
            $table->foreignId('user_id')
                ->constrained('users')
                ->restrictOnDelete();
            $table->text('text');
            $table->boolean('an_d3_gesendet')->default(false);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('intranet_app_bestellungen_notizen');
    }
};
