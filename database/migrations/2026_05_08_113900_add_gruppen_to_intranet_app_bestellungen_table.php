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
            $table->json('gruppen')->nullable()->after('kontierung');
        });
    }

    public function down(): void
    {
        Schema::table('intranet_app_bestellungen', function (Blueprint $table): void {
            $table->dropColumn('gruppen');
        });
    }
};
