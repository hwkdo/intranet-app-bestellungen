<?php

declare(strict_types=1);

use App\Models\User;
use Hwkdo\IntranetAppBestellungen\Livewire\Apps\Bestellungen\Admin\WertgrenzenEditor;
use Hwkdo\IntranetAppBestellungen\Models\IntranetAppBestellungenSettings;
use Livewire\Livewire;

it('speichert neue Wertgrenzen-Stufen via Admin-Editor', function (): void {
    $user = User::factory()->create();

    Livewire::actingAs($user)
        ->test(WertgrenzenEditor::class)
        ->set('stufen', [
            ['bezeichnung' => 'Bis 500', 'bisBetrag' => 500, 'freigeberUserIds' => [], 'freigeberRollen' => ['Admin'], 'zweiteFreigabeErforderlich' => false, 'zweiteFreigabeAb' => null],
            ['bezeichnung' => 'Unbegrenzt', 'bisBetrag' => null, 'freigeberUserIds' => [], 'freigeberRollen' => ['Admin'], 'zweiteFreigabeErforderlich' => true, 'zweiteFreigabeAb' => null],
        ])
        ->call('speichern')
        ->assertHasNoErrors();

    $settings = IntranetAppBestellungenSettings::current()?->settings;

    expect($settings)->not->toBeNull();
    expect($settings->freigabeStufen)->toHaveCount(2);
    expect($settings->freigabeStufen[0]['bezeichnung'])->toBe('Bis 500');
    expect($settings->freigabeStufen[1]['zweiteFreigabeErforderlich'])->toBeTrue();
});
