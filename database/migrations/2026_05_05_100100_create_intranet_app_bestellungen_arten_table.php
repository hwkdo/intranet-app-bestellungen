<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('intranet_app_bestellungen_arten', function (Blueprint $table): void {
            $table->id();
            $table->string('bezeichnung');
            $table->string('icon')->nullable();
            $table->boolean('aktiv')->default(true);
            $table->unsignedSmallInteger('sortierung')->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('intranet_app_bestellungen_arten');
    }
};
