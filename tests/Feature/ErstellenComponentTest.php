<?php

declare(strict_types=1);

use App\Models\User;
use App\Services\IntranetLegacyService;
use Hwkdo\IntranetAppBestellungen\Data\AppSettings;
use Hwkdo\IntranetAppBestellungen\Livewire\Apps\Bestellungen\Erstellen;
use Hwkdo\IntranetAppBestellungen\Models\Bestellung;
use Hwkdo\IntranetAppBestellungen\Models\IntranetAppBestellungenSettings;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
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

it('legt eine Bestellung mit Position an und reicht sie ein', function (): void {
    Storage::fake('public');

    $user = User::factory()->create();
    $positionPdf = UploadedFile::fake()->create('position.pdf', 64, 'application/pdf');

    Livewire::actingAs($user)
        ->test(Erstellen::class, ['typ' => 'extern'])
        ->set('lieferantennummer', '12345')
        ->set('lieferantenname', 'Test GmbH')
        ->set('kostenstelle', '4711')
        ->set('haushaltsjahr', 2026)
        ->set('betreff', 'Testbestellung')
        ->set('begruendung', 'Begründung für die Testbestellung im Rahmen des Bedarfs.')
        ->set('positionen', [[
            'nr' => 1,
            'art_id' => null,
            'art_nr' => null,
            'oberbegriff' => null,
            'bezeichnung' => 'Bleistift',
            'menge' => 5,
            'einheit' => 'Stk',
            'preis' => 1.50,
        ]])
        ->set('positionPdfs.0', $positionPdf)
        ->call('speichern')
        ->assertRedirect();

    $bestellung = Bestellung::query()->latest()->first();

    expect($bestellung)->not->toBeNull();
    expect($bestellung->lieferantenname)->toBe('Test GmbH');
    expect((float) $bestellung->gesamtbetrag)->toEqual(7.50);
    expect($bestellung->positionen)->toHaveCount(1);
    expect($bestellung->positionen->first()?->hasPositionPdf())->toBeTrue();
    expect($bestellung->begruendung)->toContain('Begründung für die Testbestellung');
});

it('verlangt eine Begründung beim Erstellen einer Bestellung', function (): void {
    $user = User::factory()->create();

    Livewire::actingAs($user)
        ->test(Erstellen::class, ['typ' => 'extern'])
        ->set('lieferantennummer', '12345')
        ->set('lieferantenname', 'Test GmbH')
        ->set('kostenstelle', '4711')
        ->set('haushaltsjahr', 2026)
        ->set('positionen', [[
            'nr' => 1,
            'art_id' => null,
            'art_nr' => null,
            'oberbegriff' => null,
            'bezeichnung' => 'Bleistift',
            'menge' => 1,
            'einheit' => 'Stk',
            'preis' => 1.00,
        ]])
        ->call('speichern')
        ->assertHasErrors(['begruendung']);
});
