<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('intranet_app_bestellungen_angebote', function (Blueprint $table): void {
            $table->date('angebotsdatum')->nullable()->after('nummer');
            $table->decimal('betrag_netto', 12, 2)->nullable()->after('betrag');
        });
    }

    public function down(): void
    {
        Schema::table('intranet_app_bestellungen_angebote', function (Blueprint $table): void {
            $table->dropColumn(['angebotsdatum', 'betrag_netto']);
        });
    }
};
