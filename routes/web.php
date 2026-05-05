<?php

declare(strict_types=1);

use Hwkdo\IntranetAppBestellungen\Http\Controllers\PdfController;
use Hwkdo\IntranetAppBestellungen\Livewire\Apps\Bestellungen\Detail;
use Hwkdo\IntranetAppBestellungen\Livewire\Apps\Bestellungen\Erstellen;
use Hwkdo\IntranetAppBestellungen\Livewire\Apps\Bestellungen\Freigaben;
use Hwkdo\IntranetAppBestellungen\Livewire\Apps\Bestellungen\Index;
use Hwkdo\IntranetAppBestellungen\Livewire\Apps\Bestellungen\Meine;
use Hwkdo\IntranetAppBestellungen\Livewire\Apps\Bestellungen\Settings\User;
use Illuminate\Support\Facades\Route;

Route::middleware(['web', 'auth', 'can:see-app-bestellungen'])->group(function (): void {
    Route::get('apps/bestellungen', Index::class)
        ->name('apps.bestellungen.index');

    Route::get('apps/bestellungen/erstellen', Erstellen::class)
        ->name('apps.bestellungen.erstellen');

    Route::get('apps/bestellungen/meine', Meine::class)
        ->name('apps.bestellungen.meine');

    Route::get('apps/bestellungen/freigaben', Freigaben::class)
        ->name('apps.bestellungen.freigaben');

    Route::get('apps/bestellungen/settings/user', User::class)
        ->name('apps.bestellungen.settings.user');
});

Route::middleware(['web', 'auth', 'can:manage-app-bestellungen'])->group(function (): void {
    Route::get('apps/bestellungen/admin', Hwkdo\IntranetAppBestellungen\Livewire\Apps\Bestellungen\Admin\Index::class)
        ->name('apps.bestellungen.admin.index');
});

Route::middleware(['web', 'auth', 'can:see-app-bestellungen'])
    ->whereNumber('bestellung')
    ->group(function (): void {
        Route::get('apps/bestellungen/{bestellung}', Detail::class)
            ->name('apps.bestellungen.detail');

        Route::get('apps/bestellungen/{bestellung}/pdf/inline', [PdfController::class, 'inline'])
            ->name('apps.bestellungen.pdf.inline');

        Route::get('apps/bestellungen/{bestellung}/pdf/download', [PdfController::class, 'download'])
            ->name('apps.bestellungen.pdf.download');
    });
