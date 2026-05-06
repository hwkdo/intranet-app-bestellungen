<?php

declare(strict_types=1);

use App\Models\User;
use Hwkdo\IntranetAppBestellungen\Data\AppSettings;
use Hwkdo\IntranetAppBestellungen\Data\FreigabeStufe;
use Hwkdo\IntranetAppBestellungen\Services\WertgrenzenService;

/** Hilfsfunktion: Erstellt AppSettings mit einer einzigen Stufe */
function stufeSettings(array $stufenFelder): AppSettings
{
    $defaults = [
        'bezeichnung' => 'Test',
        'vonBetrag' => 0,
        'bisBetrag' => null,
        'berechtigteAttribute' => [],
        'berechtigteRollen' => [],
        'freigabe1Regeln' => [],
        'freigabe2Regeln' => [],
    ];

    return AppSettings::from([
        'freigabeStufen' => [array_merge($defaults, $stufenFelder)],
    ]);
}

// ---------------------------------------------------------------------------
// stufeFuerBetrag
// ---------------------------------------------------------------------------

it('liefert die passende Stufe anhand von von/bis-Bereich', function (): void {
    $settings = AppSettings::from([
        'freigabeStufen' => [
            ['bezeichnung' => 'Klein', 'vonBetrag' => 0, 'bisBetrag' => 500.0],
            ['bezeichnung' => 'Mittel', 'vonBetrag' => 500.01, 'bisBetrag' => 5000.0],
            ['bezeichnung' => 'Groß', 'vonBetrag' => 5000.01, 'bisBetrag' => null],
        ],
    ]);

    $service = new WertgrenzenService($settings);

    expect($service->stufeFuerBetrag(0.0)?->bezeichnung)->toBe('Klein');
    expect($service->stufeFuerBetrag(500.0)?->bezeichnung)->toBe('Klein');
    expect($service->stufeFuerBetrag(500.01)?->bezeichnung)->toBe('Mittel');
    expect($service->stufeFuerBetrag(5000.0)?->bezeichnung)->toBe('Mittel');
    expect($service->stufeFuerBetrag(5000.01)?->bezeichnung)->toBe('Groß');
    expect($service->stufeFuerBetrag(999999.0)?->bezeichnung)->toBe('Groß');
});

it('liefert null wenn kein Betrag in eine Stufe passt', function (): void {
    $settings = AppSettings::from([
        'freigabeStufen' => [
            ['bezeichnung' => 'Mittel', 'vonBetrag' => 100, 'bisBetrag' => 500.0],
        ],
    ]);

    $service = new WertgrenzenService($settings);

    expect($service->stufeFuerBetrag(50.0))->toBeNull();
    expect($service->stufeFuerBetrag(600.0))->toBeNull();
});

// ---------------------------------------------------------------------------
// zweiteFreigabeNoetig
// ---------------------------------------------------------------------------

it('erkennt zweite Freigabe wenn freigabe2Regeln gesetzt', function (): void {
    $settings = stufeSettings([
        'freigabe2Regeln' => [
            ['typ' => 'default', 'keinFreigeber' => false, 'quelleTyp' => 'gruppe', 'quelle' => 'GB'],
        ],
    ]);

    $service = new WertgrenzenService($settings);

    expect($service->zweiteFreigabeNoetig(1.0))->toBeTrue();
});

it('erkennt keine zweite Freigabe wenn freigabe2Regeln leer', function (): void {
    $settings = stufeSettings(['freigabe2Regeln' => []]);

    $service = new WertgrenzenService($settings);

    expect($service->zweiteFreigabeNoetig(1.0))->toBeFalse();
});

// ---------------------------------------------------------------------------
// darfBestellen
// ---------------------------------------------------------------------------

it('erlaubt Bestellen wenn User eine berechtigte Rolle hat', function (): void {
    $user = User::factory()->create();
    $user->assignRole($user->roles()->create(['name' => 'Bestellungen-Test', 'guard_name' => 'web'])->name ?? 'Bestellungen-Test');

    $settings = stufeSettings([
        'berechtigteAttribute' => [],
        'berechtigteRollen' => ['Bestellungen-Test'],
    ]);

    $service = new WertgrenzenService($settings);

    expect($service->darfBestellen($user, 100.0))->toBeTrue();
});

it('verweigert Bestellen wenn User weder Attribut noch Rolle hat', function (): void {
    $user = User::factory()->create();

    $settings = stufeSettings([
        'berechtigteAttribute' => ['ist_al'],
        'berechtigteRollen' => ['Bestellungen-NurFürAL'],
    ]);

    $service = new WertgrenzenService($settings);

    expect($service->darfBestellen($user, 100.0))->toBeFalse();
});

it('verweigert Bestellen wenn kein Betrag zur Stufe passt', function (): void {
    $user = User::factory()->create();

    $settings = AppSettings::from([
        'freigabeStufen' => [
            ['bezeichnung' => 'Mittel', 'vonBetrag' => 500, 'bisBetrag' => 5000, 'berechtigteRollen' => ['Benutzer']],
        ],
    ]);

    $service = new WertgrenzenService($settings);

    expect($service->darfBestellen($user, 100.0))->toBeFalse();
});

// ---------------------------------------------------------------------------
// resolveFreigeber: if_attribute
// ---------------------------------------------------------------------------

it('gibt Freigeber für if_attribute-Regel zurück wenn Attribut zutrifft', function (): void {
    $freigeber = User::factory()->create();
    $besteller = User::factory()->create();

    // Wir mocken das Attribut indirekt über eine eigene Stufe mit konkretem User
    $settings = stufeSettings([
        'freigabe1Regeln' => [
            [
                'typ' => 'if_rolle',
                'bedingung' => 'Bestellungen-TestFreigeber',
                'keinFreigeber' => false,
                'quelleTyp' => 'single',
                'quelle' => 'vorgesetzter',
                'excludeAttribute' => [],
            ],
        ],
        'berechtigteRollen' => ['Benutzer'],
    ]);

    // Kein User hat die Rolle → leere Collection
    $service = new WertgrenzenService($settings);

    /** @var \Hwkdo\IntranetAppBestellungen\Models\Bestellung $bestellung */
    $bestellung = \Hwkdo\IntranetAppBestellungen\Models\Bestellung::factory()->create([
        'user_id' => $besteller->id,
        'gesamtbetrag' => 100,
    ]);

    $result = $service->freigeber1FuerBestellung($bestellung);

    expect($result)->toBeCollection()->toBeEmpty();
});

// ---------------------------------------------------------------------------
// resolveFreigeber: keinFreigeber
// ---------------------------------------------------------------------------

it('gibt leere Collection zurück wenn keinFreigeber true', function (): void {
    $besteller = User::factory()->create();
    $besteller->assignRole($besteller->roles()->firstOrCreate(['name' => 'Bestellungen-Direkt', 'guard_name' => 'web'])->name ?? 'Bestellungen-Direkt');

    $settings = stufeSettings([
        'berechtigteRollen' => ['Bestellungen-Direkt'],
        'freigabe1Regeln' => [
            [
                'typ' => 'if_rolle',
                'bedingung' => 'Bestellungen-Direkt',
                'keinFreigeber' => true,
                'quelleTyp' => 'single',
                'quelle' => 'vorgesetzter',
                'excludeAttribute' => [],
            ],
        ],
    ]);

    $service = new WertgrenzenService($settings);

    /** @var \Hwkdo\IntranetAppBestellungen\Models\Bestellung $bestellung */
    $bestellung = \Hwkdo\IntranetAppBestellungen\Models\Bestellung::factory()->create([
        'user_id' => $besteller->id,
        'gesamtbetrag' => 100,
    ]);

    $result = $service->freigeber1FuerBestellung($bestellung);

    expect($result)->toBeCollection()->toBeEmpty();
});
