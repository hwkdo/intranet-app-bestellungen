<?php

declare(strict_types=1);

use App\Models\User;
use App\Services\IntranetLegacyService;
use Hwkdo\D3RestLaravel\Client as D3Client;
use Hwkdo\IntranetAppBestellungen\Data\AppSettings;
use Hwkdo\IntranetAppBestellungen\Enums\BestellungStatus;
use Hwkdo\IntranetAppBestellungen\Livewire\Apps\Bestellungen\Detail;
use Hwkdo\IntranetAppBestellungen\Models\Bestellung;
use Hwkdo\IntranetAppBestellungen\Models\IntranetAppBestellungenSettings;
use Hwkdo\IntranetAppBestellungen\Services\WertgrenzenService;
use Livewire\Livewire;

beforeEach(function (): void {
    $legacyMock = Mockery::mock(IntranetLegacyService::class);
    $legacyMock->shouldReceive('getMaxSequenceFromLegacy')->andReturn(0);
    app()->instance(IntranetLegacyService::class, $legacyMock);

    IntranetAppBestellungenSettings::create([
        'version' => 1,
        'settings' => AppSettings::from([
            'freigabeStufen' => [
                ['bezeichnung' => 'Standard', 'bisBetrag' => null, 'freigeberRollen' => ['Benutzer']],
            ],
            'angebotsRegeln' => [
                ['abBetrag' => 0, 'mindestAngebote' => 0, 'begruendungErlaubt' => true],
            ],
        ])->toArray(),
    ]);
});

it('zeigt Vertretung im Freigeber-Label und bündelt Abwesenheitshinweise', function (): void {
    $besteller = User::factory()->create([
        'vorname' => 'Leon',
        'nachname' => 'Seelbach',
        'username' => 'hwkdo-test-besteller',
    ]);
    $anwesend = User::factory()->create([
        'vorname' => 'Sebastian',
        'nachname' => 'Kopec',
        'username' => 'hwkdo-test-anwesend',
    ]);
    $abwesend = User::factory()->create([
        'vorname' => 'Dominik',
        'nachname' => 'Schmidt',
        'username' => 'hwkdo-test-abwesend',
    ]);
    $deputy = User::factory()->create([
        'vorname' => 'Tanja',
        'nachname' => 'Kopowski',
        'username' => 'hwkdo-test-deputy',
    ]);

    $bestellung = Bestellung::factory()->create([
        'user_id' => $besteller->id,
        'status' => BestellungStatus::Entwurf,
        'gesamtbetrag' => 179.00,
    ]);

    $wertgrenzen = Mockery::mock(WertgrenzenService::class);
    $wertgrenzen->shouldReceive('istFreigeber1NichtNoetig')->andReturn(false);
    $wertgrenzen->shouldReceive('freigeber1FuerBestellung')->andReturn(collect([$anwesend, $abwesend]));
    app()->instance(WertgrenzenService::class, $wertgrenzen);

    $absence = new class($deputy)
    {
        public bool $abwesend = true;

        public function __construct(public User $vertreter) {}
    };

    $d3 = Mockery::mock(D3Client::class);
    $d3->shouldReceive('getUserIdByUsername')
        ->with($anwesend->username)
        ->andReturn(null);
    $d3->shouldReceive('getUserIdByUsername')
        ->with($abwesend->username)
        ->andReturn('d3-absent-1');
    $d3->shouldReceive('getUserAbsence')
        ->with('d3-absent-1')
        ->andReturn($absence);
    app()->instance(D3Client::class, $d3);

    Livewire::actingAs($besteller)
        ->test(Detail::class, ['bestellung' => $bestellung])
        ->call('einreichenModalOeffnen')
        ->assertSet('einreichFreigeberOptionen', [
            $anwesend->id => 'Sebastian Kopec',
            $deputy->id => 'Tanja Kopowski (Vertretung für Dominik Schmidt)',
        ])
        ->assertSet('einreichFreigeberHinweise', [
            'Dominik Schmidt ist in D3 abwesend. Vertretung: Tanja Kopowski.',
        ])
        ->assertSee('Abwesenheiten / Hinweise')
        ->assertSee('Dominik Schmidt ist in D3 abwesend. Vertretung: Tanja Kopowski.');
});
