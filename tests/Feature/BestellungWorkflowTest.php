<?php

declare(strict_types=1);

use App\Models\User;
use Hwkdo\IntranetAppBestellungen\Data\AppSettings;
use Hwkdo\IntranetAppBestellungen\Enums\BestellungStatus;
use Hwkdo\IntranetAppBestellungen\Exceptions\WorkflowException;
use Hwkdo\IntranetAppBestellungen\Models\Bestellung;
use Hwkdo\IntranetAppBestellungen\Services\AngebotsregelService;
use Hwkdo\IntranetAppBestellungen\Services\BestellungWorkflow;
use Hwkdo\IntranetAppBestellungen\Services\WertgrenzenService;
use Illuminate\Support\Facades\Queue;

beforeEach(function (): void {
    Queue::fake();
});

function makeWorkflow(?AppSettings $settings = null): BestellungWorkflow
{
    $settings ??= AppSettings::from([
        'freigabeStufen' => [
            ['bezeichnung' => 'Standard', 'bisBetrag' => null, 'freigeberRollen' => ['Admin']],
        ],
        'angebotsRegeln' => [
            ['abBetrag' => 0, 'mindestAngebote' => 0, 'begruendungErlaubt' => true],
        ],
    ]);

    return new BestellungWorkflow(new WertgrenzenService($settings), new AngebotsregelService($settings));
}

it('führt eine Bestellung von Entwurf über Freigabe bis Bestellt', function (): void {
    $user = User::factory()->create();
    $bestellung = Bestellung::factory()->create([
        'status' => BestellungStatus::Entwurf,
        'gesamtbetrag' => 100.0,
        'user_id' => $user->id,
    ]);

    $workflow = makeWorkflow();

    $workflow->einreichen($bestellung, $user);
    expect($bestellung->fresh()->status)->toBe(BestellungStatus::ZurFreigabe);

    $workflow->freigeben($bestellung->fresh(), $user);
    expect($bestellung->fresh()->status)->toBe(BestellungStatus::Freigegeben);

    $workflow->bestellen($bestellung->fresh(), $user);
    expect($bestellung->fresh()->status)->toBe(BestellungStatus::Bestellt);
});

it('verlangt zwei Freigaben oberhalb des Schwellbetrags', function (): void {
    $settings = AppSettings::from([
        'freigabeStufen' => [
            ['bezeichnung' => 'Standard', 'bisBetrag' => null, 'freigeberRollen' => ['Admin'], 'zweiteFreigabeAb' => 1000],
        ],
        'angebotsRegeln' => [
            ['abBetrag' => 0, 'mindestAngebote' => 0, 'begruendungErlaubt' => true],
        ],
    ]);

    $user = User::factory()->create();
    $bestellung = Bestellung::factory()->create([
        'status' => BestellungStatus::ZurFreigabe,
        'gesamtbetrag' => 5000,
        'user_id' => $user->id,
    ]);

    $workflow = makeWorkflow($settings);
    $workflow->freigeben($bestellung, $user);

    expect($bestellung->fresh()->status)->toBe(BestellungStatus::ZurZweitenFreigabe);

    $workflow->freigeben($bestellung->fresh(), $user);

    expect($bestellung->fresh()->status)->toBe(BestellungStatus::Freigegeben);
});

it('verhindert Einreichen ohne erforderliches Angebot ohne Begründung', function (): void {
    $settings = AppSettings::from([
        'freigabeStufen' => [
            ['bezeichnung' => 'Standard', 'bisBetrag' => null, 'freigeberRollen' => ['Admin']],
        ],
        'angebotsRegeln' => [
            ['abBetrag' => 0, 'mindestAngebote' => 1, 'begruendungErlaubt' => false],
        ],
    ]);

    $user = User::factory()->create();
    $bestellung = Bestellung::factory()->create([
        'status' => BestellungStatus::Entwurf,
        'gesamtbetrag' => 500,
        'user_id' => $user->id,
        'begruendung' => null,
    ]);

    expect(fn () => makeWorkflow($settings)->einreichen($bestellung, $user))
        ->toThrow(WorkflowException::class);
});
