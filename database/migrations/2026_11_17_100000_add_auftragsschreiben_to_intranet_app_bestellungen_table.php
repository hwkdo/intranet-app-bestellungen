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
            $table->string('auftrag_modus')->default('tabelle')->after('typ');
            $table->date('ausfuehrungsfrist')->nullable()->after('auftrag_modus');
            $table->string('auftragnehmer_anrede')->nullable()->after('ausfuehrungsfrist');
            $table->string('auftragnehmer_ansprechpartner')->nullable()->after('auftragnehmer_anrede');
            $table->string('auftragnehmer_email')->nullable()->after('auftragnehmer_ansprechpartner');
        });
    }

    public function down(): void
    {
        Schema::table('intranet_app_bestellungen', function (Blueprint $table): void {
            $table->dropColumn([
                'auftrag_modus',
                'ausfuehrungsfrist',
                'auftragnehmer_anrede',
                'auftragnehmer_ansprechpartner',
                'auftragnehmer_email',
            ]);
        });
    }
};
