<?php

declare(strict_types=1);

use App\Models\User;
use App\Services\IntranetLegacyService;
use Hwkdo\IntranetAppBestellungen\Data\AppSettings;
use Hwkdo\IntranetAppBestellungen\Enums\BestellungStatus;
use Hwkdo\IntranetAppBestellungen\Enums\BestellungTyp;
use Hwkdo\IntranetAppBestellungen\Livewire\Apps\Bestellungen\Detail;
use Hwkdo\IntranetAppBestellungen\Livewire\Apps\Bestellungen\Erstellen;
use Hwkdo\IntranetAppBestellungen\Models\Bestellung;
use Hwkdo\IntranetAppBestellungen\Models\IntranetAppBestellungenSettings;
use Hwkdo\IntranetAppBestellungen\Models\LieferantCache;
use Hwkdo\IntranetAppBestellungen\Support\PlatzhalterLieferant;
use Illuminate\Support\Facades\Queue;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;

beforeEach(function (): void {
    Queue::fake();

    $legacyMock = Mockery::mock(IntranetLegacyService::class);
    $legacyMock->shouldReceive('getMaxSequenceFromLegacy')->andReturn(0);
    app()->instance(IntranetLegacyService::class, $legacyMock);

    Role::findOrCreate('Benutzer', 'web');
    Role::findOrCreate('App-Bestellungen-InterneBesteller', 'web');

    IntranetAppBestellungenSettings::create([
        'version' => 1,
        'settings' => AppSettings::from([
            'unbekannterLieferantennummer' => '7000720',
            'interneBestellerGruppe' => 'App-Bestellungen-InterneBesteller',
            'freigabeStufen' => [
                ['bezeichnung' => 'Standard', 'bisBetrag' => null, 'freigeberRollen' => ['Benutzer']],
            ],
            'angebotsRegeln' => [
                ['abBetrag' => 0, 'mindestAngebote' => 0, 'begruendungErlaubt' => true],
            ],
        ])->toArray(),
    ]);

    LieferantCache::query()->create([
        'lieferantennummer' => '7000720',
        'lieferantenname' => 'Platzhalter',
        'synced_at' => now(),
    ]);

    LieferantCache::query()->create([
        'lieferantennummer' => '99999',
        'lieferantenname' => 'Media Markt GmbH',
        'synced_at' => now(),
    ]);
});

it('legt eine interne Bestellung mit Platzhalter-Lieferant und internem Empfänger an', function (): void {
    $antragsteller = User::factory()->create();
    $antragsteller->assignRole('Benutzer');
    $empfaenger = User::factory()->create();
    $empfaenger->assignRole('App-Bestellungen-InterneBesteller');

    Livewire::actingAs($antragsteller)
        ->test(Erstellen::class, ['typ' => 'intern'])
        ->set('internerEmpfaengerUserId', $empfaenger->id)
        ->set('kostenstelle', '4711')
        ->set('haushaltsjahr', 2026)
        ->set('d3GruppenAuswahl', ['@Rechnungen'])
        ->set('begruendung', 'Neuer Monitor für den Arbeitsplatz im Büro.')
        ->set('positionen', [[
            'nr' => 1,
            'art_id' => null,
            'art_nr' => null,
            'oberbegriff' => null,
            'bezeichnung' => 'Monitor 27 Zoll',
            'menge' => 1,
            'einheit' => 'Stk',
            'preis' => 350.00,
        ]])
        ->call('speichern')
        ->assertRedirect();

    $bestellung = Bestellung::query()->latest()->first();

    expect($bestellung)->not->toBeNull();
    expect($bestellung->typ)->toBe(BestellungTyp::Intern);
    expect($bestellung->interner_empfaenger_user_id)->toBe($empfaenger->id);
    expect($bestellung->lieferantennummer)->toBe('7000720');
});

it('erlaubt nur dem internen Empfänger das Bestellen mit finalem Lieferanten', function (): void {
    IntranetAppBestellungenSettings::query()->delete();
    IntranetAppBestellungenSettings::create([
        'version' => 1,
        'settings' => AppSettings::from([
            'unbekannterLieferantennummer' => '7000720',
            'interneBestellerGruppe' => 'App-Bestellungen-InterneBesteller',
            'autoPushBeiBestellt' => false,
            'freigabeStufen' => [
                ['bezeichnung' => 'Standard', 'bisBetrag' => null, 'freigeberRollen' => ['Benutzer']],
            ],
            'angebotsRegeln' => [
                ['abBetrag' => 0, 'mindestAngebote' => 0, 'begruendungErlaubt' => true],
            ],
        ])->toArray(),
    ]);

    $empfaenger = User::factory()->create();
    $empfaenger->assignRole('App-Bestellungen-InterneBesteller');

    $fremder = User::factory()->create();

    $bestellung = Bestellung::factory()->intern()->create([
        'status' => BestellungStatus::Freigegeben,
        'interner_empfaenger_user_id' => $empfaenger->id,
        'lieferantennummer' => PlatzhalterLieferant::nummer(),
        'lieferantenname' => 'Platzhalter',
    ]);

    Livewire::actingAs($fremder)
        ->test(Detail::class, ['bestellung' => $bestellung])
        ->tap(fn ($livewire) => expect($livewire->instance()->kannBestellen())->toBeFalse());

    Livewire::actingAs($empfaenger)
        ->test(Detail::class, ['bestellung' => $bestellung])
        ->tap(fn ($livewire) => expect($livewire->instance()->kannBestellen())->toBeTrue())
        ->call('bestellenMitLieferant')
        ->assertHasErrors(['bestellenLieferantennummer']);

    Livewire::actingAs($empfaenger)
        ->test(Detail::class, ['bestellung' => $bestellung])
        ->set('bestellenLieferantennummer', '99999')
        ->call('bestellenMitLieferant');

    $bestellung->refresh();

    expect($bestellung->lieferantennummer)->toBe('99999');
    expect($bestellung->lieferantenname)->toBe('Media Markt GmbH');
    expect($bestellung->status)->toBe(BestellungStatus::Bestellt);
});
