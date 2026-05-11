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
            $table->foreignId('lieferanschrift_user_id')
                ->nullable()
                ->after('besteller_id')
                ->constrained('users')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('intranet_app_bestellungen', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('lieferanschrift_user_id');
        });
    }
};
