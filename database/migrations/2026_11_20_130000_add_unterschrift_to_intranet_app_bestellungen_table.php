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
            $table->foreignId('unterzeichner_id')
                ->nullable()
                ->after('auftragsschreiben_inhalt')
                ->constrained('users', indexName: 'iab_unterzeichner_fk')
                ->nullOnDelete();
            $table->string('unterschrift_art')->nullable()->after('unterzeichner_id');
            $table->timestamp('unterschrieben_at')->nullable()->after('unterschrift_art');
            $table->string('unterschrift_bild_pfad')->nullable()->after('unterschrieben_at');
            $table->string('unterschrift_scan_pfad')->nullable()->after('unterschrift_bild_pfad');
            $table->foreignId('unterschrift_scan_von_id')
                ->nullable()
                ->after('unterschrift_scan_pfad')
                ->constrained('users', indexName: 'iab_scan_von_fk')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('intranet_app_bestellungen', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('unterzeichner_id');
            $table->dropConstrainedForeignId('unterschrift_scan_von_id');
            $table->dropColumn([
                'unterschrift_art',
                'unterschrieben_at',
                'unterschrift_bild_pfad',
                'unterschrift_scan_pfad',
            ]);
        });
    }
};
