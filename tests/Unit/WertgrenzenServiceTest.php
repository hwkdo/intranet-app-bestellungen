<?php

declare(strict_types=1);

use Hwkdo\IntranetAppBestellungen\Data\AppSettings;
use Hwkdo\IntranetAppBestellungen\Services\WertgrenzenService;

it('liefert die kleinste passende Stufe nach Betrag', function (): void {
    $settings = AppSettings::from([
        'freigabeStufen' => [
            ['bezeichnung' => 'Klein', 'bisBetrag' => 500.0, 'freigeberRollen' => ['A']],
            ['bezeichnung' => 'Mittel', 'bisBetrag' => 5000.0, 'freigeberRollen' => ['B']],
            ['bezeichnung' => 'Groß', 'bisBetrag' => null, 'freigeberRollen' => ['C']],
        ],
    ]);

    $service = new WertgrenzenService($settings);

    expect($service->stufeFuerBetrag(100.0)?->bezeichnung)->toBe('Klein');
    expect($service->stufeFuerBetrag(1000.0)?->bezeichnung)->toBe('Mittel');
    expect($service->stufeFuerBetrag(99999.0)?->bezeichnung)->toBe('Groß');
});

it('erkennt zweite Freigabe ab definiertem Schwellbetrag', function (): void {
    $settings = AppSettings::from([
        'freigabeStufen' => [
            [
                'bezeichnung' => 'Standard',
                'bisBetrag' => null,
                'freigeberRollen' => ['Admin'],
                'zweiteFreigabeAb' => 10000,
            ],
        ],
    ]);

    $service = new WertgrenzenService($settings);

    expect($service->zweiteFreigabeNoetig(5000.0))->toBeFalse();
    expect($service->zweiteFreigabeNoetig(15000.0))->toBeTrue();
});

it('erkennt zweite Freigabe wenn immer erforderlich', function (): void {
    $settings = AppSettings::from([
        'freigabeStufen' => [
            [
                'bezeichnung' => 'Standard',
                'bisBetrag' => null,
                'freigeberRollen' => ['Admin'],
                'zweiteFreigabeErforderlich' => true,
            ],
        ],
    ]);

    $service = new WertgrenzenService($settings);

    expect($service->zweiteFreigabeNoetig(1.0))->toBeTrue();
});
