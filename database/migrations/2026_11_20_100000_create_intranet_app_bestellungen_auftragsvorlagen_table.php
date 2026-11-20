<?php

declare(strict_types=1);

use Hwkdo\IntranetAppBestellungen\Models\Auftragsvorlage;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('intranet_app_bestellungen_auftragsvorlagen', function (Blueprint $table): void {
            $table->id();
            $table->string('name');
            $table->string('schluessel');
            $table->string('beschreibung')->nullable();
            $table->text('inhalt');
            $table->json('variablen');
            $table->boolean('anrede_automatisch')->default(true);
            $table->boolean('aktiv')->default(true);
            $table->unsignedInteger('sort')->default(0);
            $table->timestamps();

            $table->unique('schluessel', 'iab_av_schluessel_uq');
        });

        $now = now();

        foreach (Auftragsvorlage::standardVorlagen() as $vorlage) {
            DB::table('intranet_app_bestellungen_auftragsvorlagen')->insert([
                ...$vorlage,
                'variablen' => json_encode($vorlage['variablen'], JSON_THROW_ON_ERROR),
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }

        Schema::table('intranet_app_bestellungen', function (Blueprint $table): void {
            $table->foreignId('auftragsvorlage_id')
                ->nullable()
                ->after('auftrag_modus')
                ->constrained('intranet_app_bestellungen_auftragsvorlagen', indexName: 'iab_auftragsvorlage_fk')
                ->nullOnDelete();
            $table->json('vorlage_werte')->nullable()->after('auftragsvorlage_id');
            $table->text('auftragsschreiben_inhalt')->nullable()->after('vorlage_werte');
        });

        $hausverwaltungId = DB::table('intranet_app_bestellungen_auftragsvorlagen')
            ->where('schluessel', 'hausverwaltung')
            ->value('id');

        if ($hausverwaltungId !== null) {
            DB::table('intranet_app_bestellungen')
                ->where('auftrag_modus', 'vorlage')
                ->whereNull('auftragsvorlage_id')
                ->update(['auftragsvorlage_id' => $hausverwaltungId]);
        }
    }

    public function down(): void
    {
        Schema::table('intranet_app_bestellungen', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('auftragsvorlage_id');
            $table->dropColumn(['vorlage_werte', 'auftragsschreiben_inhalt']);
        });

        Schema::dropIfExists('intranet_app_bestellungen_auftragsvorlagen');
    }
};
