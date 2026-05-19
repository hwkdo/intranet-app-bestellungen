<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('intranet_app_bestellungen', function (Blueprint $table): void {
            $table->foreignId('projekt_id')
                ->nullable()
                ->after('user_id')
                ->constrained('intranet_app_bestellungen_projekte')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('intranet_app_bestellungen', function (Blueprint $table): void {
            $table->dropForeignIdFor(\Hwkdo\IntranetAppBestellungen\Models\Projekt::class, 'projekt_id');
            $table->dropColumn('projekt_id');
        });
    }
};
