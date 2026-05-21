<?php

declare(strict_types=1);

use App\Models\User;
use App\Services\IntranetLegacyService;
use Hwkdo\IntranetAppBestellungen\Data\AppSettings;
use Hwkdo\IntranetAppBestellungen\Livewire\Apps\Bestellungen\Erstellen;
use Hwkdo\IntranetAppBestellungen\Mail\FehlenderLieferantGemeldetMail;
use Hwkdo\IntranetAppBestellungen\Models\IntranetAppBestellungenSettings;
use Hwkdo\IntranetAppBestellungen\Models\LieferantCache;
use Illuminate\Support\Facades\Mail;
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
            'unbekannterLieferantennummer' => '7000720',
            'fehlenderLieferantEmpfaengerEmail' => 'buchhaltung@example.test',
            'freigabeStufen' => [
                ['bezeichnung' => 'Standard', 'bisBetrag' => null, 'freigeberRollen' => ['App-Bestellungen-Admin']],
            ],
            'angebotsRegeln' => [
                ['abBetrag' => 0, 'mindestAngebote' => 0, 'begruendungErlaubt' => true],
            ],
        ])->toArray(),
    ]);

    LieferantCache::query()->create([
        'lieferantennummer' => '7000720',
        'lieferantenname' => 'Unbekannter Lieferant (Platzhalter)',
        'synced_at' => now(),
    ]);
});

it('meldet einen fehlenden Lieferanten und setzt den Platzhalter', function (): void {
    Mail::fake();

    $user = User::factory()->create();

    Livewire::actingAs($user)
        ->test(Erstellen::class)
        ->set('fehlenderLieferantName', 'Muster Lieferant AG')
        ->set('fehlenderLieferantAdresse', 'Hauptstraße 10')
        ->set('fehlenderLieferantIban', 'DE001234567890')
        ->set('fehlenderLieferantWebseite', 'https://muster.example')
        ->call('meldeFehlendenLieferant')
        ->assertSet('lieferantennummer', '7000720')
        ->assertSet('lieferantenname', 'Unbekannter Lieferant (Platzhalter)')
        ->assertSet('fehlenderLieferantName', '');

    Mail::assertQueued(FehlenderLieferantGemeldetMail::class, function (FehlenderLieferantGemeldetMail $mail): bool {
        return $mail->hasTo('buchhaltung@example.test')
            && $mail->lieferantName === 'Muster Lieferant AG'
            && $mail->adresse === 'Hauptstraße 10';
    });
});

it('validiert den Namen beim Melden eines fehlenden Lieferanten', function (): void {
    Mail::fake();

    $user = User::factory()->create();

    Livewire::actingAs($user)
        ->test(Erstellen::class)
        ->set('fehlenderLieferantName', '')
        ->call('meldeFehlendenLieferant')
        ->assertHasErrors(['fehlenderLieferantName']);

    Mail::assertNothingQueued();
});
