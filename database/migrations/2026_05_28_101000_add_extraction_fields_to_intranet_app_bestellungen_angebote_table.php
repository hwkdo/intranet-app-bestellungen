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
            $table->string('extraction_status')->nullable()->index();
            $table->string('extraction_source')->nullable();
            $table->json('extraction_payload')->nullable();
            $table->text('extraction_error')->nullable();
            $table->timestamp('extracted_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('intranet_app_bestellungen_angebote', function (Blueprint $table): void {
            $table->dropColumn([
                'extraction_status',
                'extraction_source',
                'extraction_payload',
                'extraction_error',
                'extracted_at',
            ]);
        });
    }
};
