<?php

declare(strict_types=1);

use Hwkdo\IntranetAppBestellungen\Data\AppSettings;
use Hwkdo\IntranetAppBestellungen\Models\Angebot;
use Hwkdo\IntranetAppBestellungen\Models\Bestellung;
use Hwkdo\IntranetAppBestellungen\Services\AngebotsregelService;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

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

it('zählt nur Ausnahme-Begründungen als Angebot typ begruendung nicht die Kopf-Begründung', function (): void {
    $settings = AppSettings::from([
        'angebotsRegeln' => [
            ['abBetrag' => 0, 'mindestAngebote' => 2, 'begruendungErlaubt' => true],
        ],
    ]);

    $bestellung = Bestellung::factory()->create([
        'gesamtbetrag' => 1500,
        'begruendung' => 'Allgemeine fachliche Begründung der Bestellung mit ausreichend Text.',
    ]);

    $service = new AngebotsregelService($settings);

    expect($service->istFreigabeReady($bestellung))->toBeFalse();

    Angebot::create([
        'bestellung_id' => $bestellung->getKey(),
        'user_id' => $bestellung->user_id,
        'typ' => 'begruendung',
        'begruendung' => 'Marktenges Angebot, daher keine drei Vergleichsangebote möglich.',
    ]);

    expect($service->istFreigabeReady($bestellung->fresh()))->toBeTrue();
});

it('nimmt Auftragsvergaben von den Vergleichsangebotsregeln aus', function (): void {
    $settings = AppSettings::from([
        'angebotsRegeln' => [
            ['abBetrag' => 0, 'mindestAngebote' => 3, 'begruendungErlaubt' => false],
        ],
    ]);

    $bestellung = Bestellung::factory()->auftragsvergabe()->create([
        'gesamtbetrag' => 25000,
    ]);

    $auswertung = (new AngebotsregelService($settings))->auswertung($bestellung);

    expect($auswertung->anwendbar)->toBeFalse()
        ->and($auswertung->pruefungAktiv)->toBeFalse()
        ->and($auswertung->bereit)->toBeTrue()
        ->and($auswertung->zusammenfassung())->toBe('');
});
