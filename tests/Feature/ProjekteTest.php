<?php

declare(strict_types=1);

use App\Models\User;
use App\Services\IntranetLegacyService;
use Hwkdo\IntranetAppBestellungen\Data\AppSettings;
use Hwkdo\IntranetAppBestellungen\Livewire\Apps\Bestellungen\Erstellen;
use Hwkdo\IntranetAppBestellungen\Livewire\Apps\Bestellungen\Projekte\Detail;
use Hwkdo\IntranetAppBestellungen\Livewire\Apps\Bestellungen\Projekte\Index;
use Hwkdo\IntranetAppBestellungen\Models\Bestellung;
use Hwkdo\IntranetAppBestellungen\Models\IntranetAppBestellungenSettings;
use Hwkdo\IntranetAppBestellungen\Models\Projekt;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;

beforeEach(function (): void {
    $legacyMock = Mockery::mock(IntranetLegacyService::class);
    $legacyMock->shouldReceive('getMaxSequenceFromLegacy')->andReturn(0);
    app()->instance(IntranetLegacyService::class, $legacyMock);

    Role::findOrCreate('App-Bestellungen-Admin', 'web');

    IntranetAppBestellungenSettings::create([
        'version' => 1,
        'settings' => AppSettings::from([
            'freigabeStufen' => [
                ['bezeichnung' => 'Standard', 'bisBetrag' => null, 'freigeberRollen' => ['App-Bestellungen-Admin']],
            ],
            'angebotsRegeln' => [
                ['abBetrag' => 0, 'mindestAngebote' => 0, 'begruendungErlaubt' => true],
            ],
        ])->toArray(),
    ]);
});

it('zeigt die Projektliste für den Ersteller', function (): void {
    $user = User::factory()->create();
    $projekt = Projekt::factory()->create(['user_id' => $user->id, 'name' => 'Mein Testprojekt']);

    Livewire::actingAs($user)
        ->test(Index::class)
        ->assertSee('Mein Testprojekt');
});

it('zeigt die Projektliste für eingeladene Mitglieder', function (): void {
    $ersteller = User::factory()->create();
    $mitglied = User::factory()->create();
    $projekt = Projekt::factory()->create(['user_id' => $ersteller->id, 'name' => 'Gemeinsames Projekt']);
    $projekt->mitglieder()->attach($mitglied->id);

    Livewire::actingAs($mitglied)
        ->test(Index::class)
        ->assertSee('Gemeinsames Projekt');
});

it('zeigt keine fremden Projekte', function (): void {
    $ersteller = User::factory()->create();
    $andererUser = User::factory()->create();
    Projekt::factory()->create(['user_id' => $ersteller->id, 'name' => 'Privates Projekt']);

    Livewire::actingAs($andererUser)
        ->test(Index::class)
        ->assertDontSee('Privates Projekt');
});

it('legt ein neues Projekt an', function (): void {
    $user = User::factory()->create();

    Livewire::actingAs($user)
        ->test(Index::class)
        ->set('name', 'Neues Projekt')
        ->set('beschreibung', 'Eine Beschreibung')
        ->set('begruendung', 'Gemeinsame fachliche Begründung für alle Bestellungen in diesem Projekt.')
        ->call('erstellen');

    $projekt = Projekt::query()->where('name', 'Neues Projekt')->first();
    expect($projekt)->not->toBeNull();
    expect($projekt->user_id)->toBe($user->id);
    expect($projekt->beschreibung)->toBe('Eine Beschreibung');
    expect($projekt->begruendung)->toContain('Gemeinsame fachliche Begründung');
});

it('verlangt eine Begründung beim Anlegen eines Projekts', function (): void {
    $user = User::factory()->create();

    Livewire::actingAs($user)
        ->test(Index::class)
        ->set('name', 'Ohne Begründung')
        ->call('erstellen')
        ->assertHasErrors(['begruendung']);
});

it('zeigt die Projektdetails nur für Ersteller und Mitglieder', function (): void {
    $ersteller = User::factory()->create();
    $fremder = User::factory()->create();
    $projekt = Projekt::factory()->create(['user_id' => $ersteller->id]);

    Livewire::actingAs($fremder)
        ->test(Detail::class, ['projekt' => $projekt])
        ->assertForbidden();
});

it('zeigt die Projektdetails mit Bestellungen und Gesamtkosten', function (): void {
    $user = User::factory()->create();
    $projekt = Projekt::factory()->create(['user_id' => $user->id, 'name' => 'IT-Projekt 2026']);

    Bestellung::factory()->create([
        'user_id' => $user->id,
        'projekt_id' => $projekt->id,
        'gesamtbetrag' => 100.00,
        'lieferantenname' => 'Lieferant A',
    ]);
    Bestellung::factory()->create([
        'user_id' => $user->id,
        'projekt_id' => $projekt->id,
        'gesamtbetrag' => 250.50,
        'lieferantenname' => 'Lieferant B',
    ]);

    Livewire::actingAs($user)
        ->test(Detail::class, ['projekt' => $projekt])
        ->assertSee('IT-Projekt 2026')
        ->assertSee('Lieferant A')
        ->assertSee('Lieferant B')
        ->assertSee('350,50');
});

it('fügt ein Mitglied zum Projekt hinzu', function (): void {
    $ersteller = User::factory()->create();
    $neuesMitglied = User::factory()->create();
    $projekt = Projekt::factory()->create(['user_id' => $ersteller->id]);

    Livewire::actingAs($ersteller)
        ->test(Detail::class, ['projekt' => $projekt])
        ->set('mitgliedUserId', $neuesMitglied->id)
        ->call('mitgliedHinzufuegen');

    expect($projekt->mitglieder()->where('user_id', $neuesMitglied->id)->exists())->toBeTrue();
});

it('verbietet Nicht-Erstellern das Hinzufügen von Mitgliedern', function (): void {
    $ersteller = User::factory()->create();
    $mitglied = User::factory()->create();
    $anderesMitglied = User::factory()->create();
    $projekt = Projekt::factory()->create(['user_id' => $ersteller->id]);
    $projekt->mitglieder()->attach($mitglied->id);

    Livewire::actingAs($mitglied)
        ->test(Detail::class, ['projekt' => $projekt])
        ->set('mitgliedUserId', $anderesMitglied->id)
        ->call('mitgliedHinzufuegen')
        ->assertForbidden();
});

it('entfernt ein Mitglied aus dem Projekt', function (): void {
    $ersteller = User::factory()->create();
    $mitglied = User::factory()->create();
    $projekt = Projekt::factory()->create(['user_id' => $ersteller->id]);
    $projekt->mitglieder()->attach($mitglied->id);

    Livewire::actingAs($ersteller)
        ->test(Detail::class, ['projekt' => $projekt])
        ->call('mitgliedEntfernen', $mitglied->id);

    expect($projekt->mitglieder()->where('user_id', $mitglied->id)->exists())->toBeFalse();
});

it('füllt die Begründung beim Erstellen aus der Projekt-Begründung vor', function (): void {
    $user = User::factory()->create();
    $projekt = Projekt::factory()->create([
        'user_id' => $user->id,
        'begruendung' => 'Standardbegründung für das IT-Ausstattungsprojekt 2026.',
    ]);

    Livewire::actingAs($user)
        ->test(Erstellen::class, ['projekt' => $projekt->id])
        ->assertSet('begruendung', 'Standardbegründung für das IT-Ausstattungsprojekt 2026.');
});

it('speichert die Projekt-ID beim Erstellen einer Bestellung', function (): void {
    \Illuminate\Support\Facades\Storage::fake('public');

    $user = User::factory()->create();
    $projekt = Projekt::factory()->create([
        'user_id' => $user->id,
        'begruendung' => 'Begründung für alle Bestellungen in diesem Projekt.',
    ]);

    Livewire::actingAs($user)
        ->test(Erstellen::class, ['projekt' => $projekt->id])
        ->set('lieferantennummer', '12345')
        ->set('lieferantenname', 'Test GmbH')
        ->set('kostenstelle', '4711')
        ->set('haushaltsjahr', 2026)
        ->set('betreff', 'Projektbestellung')
        ->set('positionen', [[
            'nr' => 1,
            'art_id' => null,
            'art_nr' => null,
            'oberbegriff' => null,
            'bezeichnung' => 'Monitor',
            'menge' => 1,
            'einheit' => 'Stk',
            'preis' => 500.00,
        ]])
        ->call('speichern')
        ->assertRedirect();

    $bestellung = Bestellung::query()->where('betreff', 'Projektbestellung')->first();
    expect($bestellung)->not->toBeNull();
    expect($bestellung->projekt_id)->toBe($projekt->id);
    expect($bestellung->begruendung)->toBe('Begründung für alle Bestellungen in diesem Projekt.');
});

it('erlaubt dem Ersteller die Projekt-Begründung zu bearbeiten', function (): void {
    $ersteller = User::factory()->create();
    $projekt = Projekt::factory()->create([
        'user_id' => $ersteller->id,
        'begruendung' => 'Ursprüngliche Projektbegründung für Freigaben.',
    ]);

    Livewire::actingAs($ersteller)
        ->test(Detail::class, ['projekt' => $projekt])
        ->set('begruendung', 'Aktualisierte Projektbegründung für künftige Bestellungen.')
        ->call('begruendungSpeichern');

    expect($projekt->fresh()->begruendung)->toBe('Aktualisierte Projektbegründung für künftige Bestellungen.');
});
