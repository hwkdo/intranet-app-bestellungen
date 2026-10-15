<?php

declare(strict_types=1);

use Hwkdo\IntranetAppBestellungen\Http\Controllers\AngebotPdfController;
use Hwkdo\IntranetAppBestellungen\Http\Controllers\PdfController;
use Hwkdo\IntranetAppBestellungen\Livewire\Apps\Bestellungen\Detail;
use Hwkdo\IntranetAppBestellungen\Livewire\Apps\Bestellungen\Erstellen;
use Hwkdo\IntranetAppBestellungen\Livewire\Apps\Bestellungen\ErstellenStart;
use Hwkdo\IntranetAppBestellungen\Livewire\Apps\Bestellungen\Freigaben;
use Hwkdo\IntranetAppBestellungen\Livewire\Apps\Bestellungen\Index;
use Hwkdo\IntranetAppBestellungen\Livewire\Apps\Bestellungen\Info;
use Hwkdo\IntranetAppBestellungen\Livewire\Apps\Bestellungen\InterneBestellungen;
use Hwkdo\IntranetAppBestellungen\Livewire\Apps\Bestellungen\Meine;
use Hwkdo\IntranetAppBestellungen\Livewire\Apps\Bestellungen\Search;
use Hwkdo\IntranetAppBestellungen\Livewire\Apps\Bestellungen\Settings\Notifications;
use Illuminate\Support\Facades\Route;

Route::middleware(['web', 'auth', 'can:see-app-bestellungen'])->group(function (): void {
    Route::get('apps/bestellungen', Index::class)
        ->name('apps.bestellungen.index');

    Route::get('apps/bestellungen/erstellen', ErstellenStart::class)
        ->name('apps.bestellungen.erstellen');

    Route::get('apps/bestellungen/erstellen/{typ}', Erstellen::class)
        ->whereIn('typ', ['intern', 'extern'])
        ->name('apps.bestellungen.erstellen.form');

    Route::get('apps/bestellungen/meine', Meine::class)
        ->name('apps.bestellungen.meine');

    Route::get('apps/bestellungen/suche', Search::class)
        ->name('apps.bestellungen.search');

    Route::get('apps/bestellungen/freigaben', Freigaben::class)
        ->name('apps.bestellungen.freigaben');

    Route::get('apps/bestellungen/interne', InterneBestellungen::class)
        ->name('apps.bestellungen.interne');

    Route::get('apps/bestellungen/info', Info::class)
        ->name('apps.bestellungen.info');

    Route::get('apps/bestellungen/settings/notifications', Notifications::class)
        ->name('apps.bestellungen.settings.notifications');

    Route::get('apps/bestellungen/projekte', Hwkdo\IntranetAppBestellungen\Livewire\Apps\Bestellungen\Projekte\Index::class)
        ->name('apps.bestellungen.projekte.index');

    Route::get('apps/bestellungen/projekte/{projekt}', Hwkdo\IntranetAppBestellungen\Livewire\Apps\Bestellungen\Projekte\Detail::class)
        ->name('apps.bestellungen.projekte.detail');
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

        Route::get('apps/bestellungen/{bestellung}/pdf/{typ}/inline', [PdfController::class, 'inline'])
            ->whereIn('typ', ['intern', 'extern', 'extern_mit_preise'])
            ->name('apps.bestellungen.pdf.inline');

        Route::get('apps/bestellungen/{bestellung}/pdf/{typ}/download', [PdfController::class, 'download'])
            ->whereIn('typ', ['intern', 'extern', 'extern_mit_preise'])
            ->name('apps.bestellungen.pdf.download');

        Route::get('apps/bestellungen/{bestellung}/angebote/{angebot}/pdf/inline', [AngebotPdfController::class, 'inline'])
            ->name('apps.bestellungen.angebot.pdf.inline');
    });
