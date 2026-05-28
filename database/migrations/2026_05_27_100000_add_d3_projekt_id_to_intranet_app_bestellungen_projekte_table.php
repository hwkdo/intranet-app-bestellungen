<?php

declare(strict_types=1);

use Hwkdo\IntranetAppBestellungen\Models\Projekt;
use Hwkdo\IntranetAppBestellungen\Services\Projekt\ProjektIdGenerator;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('intranet_app_bestellungen_projekte', function (Blueprint $table): void {
            $table->string('d3_projekt_id', 35)->nullable()->unique()->after('name');
        });

        $generator = app(ProjektIdGenerator::class);

        Projekt::query()
            ->whereNull('d3_projekt_id')
            ->orderBy('id')
            ->each(function (Projekt $projekt) use ($generator): void {
                $projekt->forceFill([
                    'd3_projekt_id' => $generator->generate($projekt->name, $projekt->getKey()),
                ])->saveQuietly();
            });
    }

    public function down(): void
    {
        Schema::table('intranet_app_bestellungen_projekte', function (Blueprint $table): void {
            $table->dropUnique(['d3_projekt_id']);
            $table->dropColumn('d3_projekt_id');
        });
    }
};
