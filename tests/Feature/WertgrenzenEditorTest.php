<?php

declare(strict_types=1);

use App\Models\User;
use Hwkdo\IntranetAppBestellungen\Livewire\Apps\Bestellungen\Admin\WertgrenzenEditor;
use Hwkdo\IntranetAppBestellungen\Models\IntranetAppBestellungenSettings;
use Livewire\Livewire;

/** @return array<int, array<string, mixed>> */
function minimaleFreigabeStufeFuerTest(): array
{
    return [
        [
            'bezeichnung' => 'Bis 500',
            'vonBetrag' => 0,
            'bisBetrag' => 500,
            'berechtigteAttribute' => ['ist_al'],
            'berechtigteRollen' => [],
            'textBerechtigt' => null,
            'textFreigeber1' => null,
            'textFreigeber2' => null,
            'freigabe1Regeln' => [],
            'freigabe2Regeln' => [],
        ],
    ];
}

it('speichert Freigabe-Stufen via Admin-Editor', function (): void {
    $user = User::factory()->create();

    Livewire::actingAs($user)
        ->test(WertgrenzenEditor::class)
        ->set('stufen', minimaleFreigabeStufeFuerTest())
        ->call('speichern')
        ->assertHasNoErrors();

    $settings = IntranetAppBestellungenSettings::current()?->settings;

    expect($settings)->not->toBeNull();
    expect($settings->freigabeStufen)->toHaveCount(1);
    expect($settings->freigabeStufen[0]['bezeichnung'])->toBe('Bis 500');
});

it('importiert und speichert nur freigabeStufen aus JSON', function (): void {
    $user = User::factory()->create();

    $json = json_encode([
        [
            'bezeichnung' => 'Import-Stufe',
            'vonBetrag' => 0,
            'bisBetrag' => 100,
            'berechtigteAttribute' => [],
            'berechtigteRollen' => [],
            'textBerechtigt' => null,
            'textFreigeber1' => null,
            'textFreigeber2' => null,
            'freigabe1Regeln' => [],
            'freigabe2Regeln' => [],
        ],
    ], JSON_THROW_ON_ERROR);

    Livewire::actingAs($user)
        ->test(WertgrenzenEditor::class)
        ->set('freigabeStufenJsonImport', $json)
        ->call('freigabeStufenAusJsonUebernehmenUndSpeichern')
        ->assertHasNoErrors()
        ->assertSet('freigabeStufenJsonImport', '');

    $settings = IntranetAppBestellungenSettings::current()?->settings;
    expect($settings)->not->toBeNull();
    expect($settings->freigabeStufen)->toHaveCount(1);
    expect($settings->freigabeStufen[0]['bezeichnung'])->toBe('Import-Stufe');
});

it('lehnt ungültiges JSON für Freigabe-Stufen ab', function (): void {
    $user = User::factory()->create();

    Livewire::actingAs($user)
        ->test(WertgrenzenEditor::class)
        ->set('freigabeStufenJsonImport', '{ keine gültige json')
        ->call('freigabeStufenAusJsonUebernehmenUndSpeichern')
        ->assertHasErrors('freigabeStufenJsonImport');
});
