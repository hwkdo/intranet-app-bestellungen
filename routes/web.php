<?php

declare(strict_types=1);

use Hwkdo\IntranetAppBestellungen\Http\Controllers\PdfController;
use Illuminate\Support\Facades\Route;

Route::middleware(['web', 'auth', 'can:see-app-bestellungen'])->group(function (): void {
    Route::get('apps/bestellungen', \Hwkdo\IntranetAppBestellungen\Livewire\Apps\Bestellungen\Index::class)
        ->name('apps.bestellungen.index');

    Route::get('apps/bestellungen/erstellen', \Hwkdo\IntranetAppBestellungen\Livewire\Apps\Bestellungen\Erstellen::class)
        ->name('apps.bestellungen.erstellen');

    Route::get('apps/bestellungen/meine', \Hwkdo\IntranetAppBestellungen\Livewire\Apps\Bestellungen\Meine::class)
        ->name('apps.bestellungen.meine');

    Route::get('apps/bestellungen/freigaben', \Hwkdo\IntranetAppBestellungen\Livewire\Apps\Bestellungen\Freigaben::class)
        ->name('apps.bestellungen.freigaben');

    Route::get('apps/bestellungen/settings/user', \Hwkdo\IntranetAppBestellungen\Livewire\Apps\Bestellungen\Settings\User::class)
        ->name('apps.bestellungen.settings.user');

    Route::get('apps/bestellungen/projekte', \Hwkdo\IntranetAppBestellungen\Livewire\Apps\Bestellungen\Projekte\Index::class)
        ->name('apps.bestellungen.projekte.index');

    Route::get('apps/bestellungen/projekte/{projekt}', \Hwkdo\IntranetAppBestellungen\Livewire\Apps\Bestellungen\Projekte\Detail::class)
        ->name('apps.bestellungen.projekte.detail');
});

Route::middleware(['web', 'auth', 'can:manage-app-bestellungen'])->group(function (): void {
    Route::get('apps/bestellungen/admin', \Hwkdo\IntranetAppBestellungen\Livewire\Apps\Bestellungen\Admin\Index::class)
        ->name('apps.bestellungen.admin.index');
});

Route::middleware(['web', 'auth', 'can:see-app-bestellungen'])
    ->whereNumber('bestellung')
    ->group(function (): void {
        Route::get('apps/bestellungen/{bestellung}', \Hwkdo\IntranetAppBestellungen\Livewire\Apps\Bestellungen\Detail::class)
            ->name('apps.bestellungen.detail');

        Route::get('apps/bestellungen/{bestellung}/pdf/inline', [PdfController::class, 'inline'])
            ->name('apps.bestellungen.pdf.inline');

        Route::get('apps/bestellungen/{bestellung}/pdf/download', [PdfController::class, 'download'])
            ->name('apps.bestellungen.pdf.download');
    });
