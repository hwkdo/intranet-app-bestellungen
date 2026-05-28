<?php

declare(strict_types=1);

use Hwkdo\IntranetAppBestellungen\Http\Controllers\Api\BestellungAngebotController;
use Hwkdo\IntranetAppBestellungen\Http\Controllers\Api\BestellungController;
use Hwkdo\IntranetAppBestellungen\Http\Controllers\Api\MeController;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Support\Facades\Route;

Route::prefix('api/bestellungen')
    ->middleware(['auth:api', 'throttle:60,1', SubstituteBindings::class])
    ->group(function (): void {
        Route::get('/me', MeController::class)->name('api.bestellungen.me');
        Route::get('/meine-offenen', [BestellungController::class, 'index'])->name('api.bestellungen.meine-offenen');
        Route::post('/{bestellung}/angebote', [BestellungAngebotController::class, 'store'])->name('api.bestellungen.angebote.store');
    });
