<?php

declare(strict_types=1);

use Hwkdo\IntranetAppBestellungen\Data\AppSettings;
use Hwkdo\IntranetAppBestellungen\Services\AngebotsregelService;

it('liefert die passende Regel anhand des Betrags', function (): void {
    $settings = AppSettings::from([
        'angebotsRegeln' => [
            ['abBetrag' => 0, 'mindestAngebote' => 0, 'begruendungErlaubt' => true],
            ['abBetrag' => 1000, 'mindestAngebote' => 1, 'begruendungErlaubt' => true],
            ['abBetrag' => 5000, 'mindestAngebote' => 3, 'begruendungErlaubt' => false],
        ],
    ]);

    $service = new AngebotsregelService($settings);

    expect($service->mindestAngeboteFuer(50))->toBe(0);
    expect($service->mindestAngeboteFuer(2000))->toBe(1);
    expect($service->mindestAngeboteFuer(10000))->toBe(3);
});
