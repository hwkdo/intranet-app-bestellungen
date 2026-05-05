<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('intranet_app_bestellungen_aktionen', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('bestellung_id')
                ->constrained('intranet_app_bestellungen')
                ->cascadeOnDelete();
            $table->foreignId('user_id')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();
            $table->string('typ');
            $table->string('von_status')->nullable();
            $table->string('nach_status')->nullable();
            $table->text('nachricht')->nullable();
            $table->json('payload')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('intranet_app_bestellungen_aktionen');
    }
};
