<?php

declare(strict_types=1);

use App\Models\User;
use Hwkdo\IntranetAppBestellungen\Data\AppSettings;
use Hwkdo\IntranetAppBestellungen\Enums\BestellungStatus;
use Hwkdo\IntranetAppBestellungen\IntranetAppBestellungen;
use Hwkdo\IntranetAppBestellungen\Models\Bestellung;
use Hwkdo\IntranetAppBestellungen\Models\IntranetAppBestellungenSettings;
use Hwkdo\IntranetAppBestellungen\Notifications\BestellungAbgelehntNotification;
use Hwkdo\IntranetAppBestellungen\Notifications\BestellungBestelltNotification;
use Hwkdo\IntranetAppBestellungen\Notifications\BestellungFreigegebenNotification;
use Hwkdo\IntranetAppBestellungen\Notifications\BestellungZurBestellungNotification;
use Hwkdo\IntranetAppBestellungen\Notifications\BestellungZurFreigabeNotification;
use Hwkdo\IntranetAppBestellungen\Services\AngebotsregelService;
use Hwkdo\IntranetAppBestellungen\Services\BestellungWorkflow;
use Hwkdo\IntranetAppBestellungen\Services\D3\AngebotD3Service;
use Hwkdo\IntranetAppBestellungen\Services\D3\BestellscheinD3Service;
use Hwkdo\IntranetAppBestellungen\Services\WertgrenzenService;
use Illuminate\Support\Facades\Notification;
use Spatie\Permission\Models\Role;

beforeEach(function (): void {
    Notification::fake();
    Role::findOrCreate('Benutzer', 'web');

    IntranetAppBestellungenSettings::query()->delete();
    IntranetAppBestellungenSettings::create([
        'version' => 1,
        'settings' => AppSettings::from([
            'autoPushBeiBestellt' => false,
            'freigabeStufen' => [
                [
                    'bezeichnung' => 'Standard',
                    'vonBetrag' => 0,
                    'bisBetrag' => null,
                    'berechtigteRollen' => ['Benutzer'],
                    'freigabe1Regeln' => [
                        ['typ' => 'default', 'keinFreigeber' => false, 'quelleTyp' => 'single', 'quelle' => 'vorgesetzter', 'excludeAttribute' => []],
                    ],
                    'freigabe2Regeln' => [],
                ],
            ],
            'angebotsRegeln' => [
                ['abBetrag' => 0, 'mindestAngebote' => 0, 'begruendungErlaubt' => true],
            ],
        ])->toArray(),
    ]);
});

function makeNotificationWorkflow(?AppSettings $settings = null): BestellungWorkflow
{
    $settings ??= AppSettings::from([
        'freigabeStufen' => [
            [
                'bezeichnung' => 'Standard',
                'vonBetrag' => 0,
                'bisBetrag' => null,
                'berechtigteRollen' => ['Benutzer'],
                'freigabe1Regeln' => [
                    ['typ' => 'default', 'keinFreigeber' => false, 'quelleTyp' => 'single', 'quelle' => 'vorgesetzter', 'excludeAttribute' => []],
                ],
                'freigabe2Regeln' => [],
            ],
        ],
        'angebotsRegeln' => [
            ['abBetrag' => 0, 'mindestAngebote' => 0, 'begruendungErlaubt' => true],
        ],
        'autoPushBeiBestellt' => false,
    ]);

    return new BestellungWorkflow(
        new WertgrenzenService($settings),
        new AngebotsregelService($settings),
        app(BestellscheinD3Service::class),
        app(AngebotD3Service::class),
    );
}

it('registriert die bestellungen-benachrichtigungstypen', function (): void {
    $types = collect(IntranetAppBestellungen::notificationTypes())->keyBy('key');

    expect($types->keys()->all())->toContain(
        'bestellungen.order_approved',
        'bestellungen.order_rejected',
        'bestellungen.pending_approval',
        'bestellungen.ordered',
        'bestellungen.ready_to_order',
    )
        ->and($types['bestellungen.order_rejected']->mandatory)->toBeFalse()
        ->and($types['bestellungen.order_rejected']->defaultChannels)->toBe(['inbox'])
        ->and($types['bestellungen.pending_approval']->mandatory)->toBeTrue()
        ->and($types['bestellungen.pending_approval']->defaultChannels)->toBe(['inbox', 'mail'])
        ->and($types['bestellungen.ordered']->mandatory)->toBeFalse()
        ->and($types['bestellungen.ordered']->defaultChannels)->toBe(['inbox'])
        ->and($types['bestellungen.ready_to_order']->mandatory)->toBeTrue()
        ->and($types['bestellungen.ready_to_order']->defaultChannels)->toBe(['inbox', 'mail']);
});

it('benachrichtigt den freigeber beim einreichen', function (): void {
    $anforderer = User::factory()->create();
    $anforderer->assignRole('Benutzer');
    $freigeber = User::factory()->create();

    $bestellung = Bestellung::factory()->extern()->create([
        'status' => BestellungStatus::Entwurf,
        'gesamtbetrag' => 100.0,
        'user_id' => $anforderer->id,
    ]);

    makeNotificationWorkflow()->einreichen($bestellung, $anforderer, $freigeber->id);

    Notification::assertSentTo($freigeber, BestellungZurFreigabeNotification::class);
    Notification::assertNotSentTo($anforderer, BestellungZurFreigabeNotification::class);
});

it('benachrichtigt den neuen freigeber beim weiterleiten', function (): void {
    $anforderer = User::factory()->create();
    $alt = User::factory()->create();
    $neu = User::factory()->create();

    $bestellung = Bestellung::factory()->extern()->create([
        'status' => BestellungStatus::ZurFreigabe,
        'user_id' => $anforderer->id,
        'freigeber_id' => $alt->id,
        'gesamtbetrag' => 100.0,
    ]);

    makeNotificationWorkflow()->weiterleiten($bestellung, $alt, $neu->id, 'Bitte prüfen');

    Notification::assertSentTo($neu, BestellungZurFreigabeNotification::class);
    Notification::assertNotSentTo($alt, BestellungZurFreigabeNotification::class);
});

it('benachrichtigt den anforderer bei ablehnung', function (): void {
    $anforderer = User::factory()->create();
    $freigeber = User::factory()->create();

    $bestellung = Bestellung::factory()->extern()->create([
        'status' => BestellungStatus::ZurFreigabe,
        'user_id' => $anforderer->id,
        'freigeber_id' => $freigeber->id,
        'gesamtbetrag' => 100.0,
    ]);

    makeNotificationWorkflow()->ablehnen($bestellung, $freigeber, 'Budget überschritten');

    Notification::assertSentTo(
        $anforderer,
        BestellungAbgelehntNotification::class,
        fn (BestellungAbgelehntNotification $n): bool => $n->grund === 'Budget überschritten'
    );
});

it('benachrichtigt anforderer und internen empfaenger bei freigabe einer internen bestellung', function (): void {
    $anforderer = User::factory()->create();
    $freigeber = User::factory()->create();
    $empfaenger = User::factory()->create();

    $bestellung = Bestellung::factory()->intern()->create([
        'status' => BestellungStatus::ZurFreigabe,
        'user_id' => $anforderer->id,
        'freigeber_id' => $freigeber->id,
        'interner_empfaenger_user_id' => $empfaenger->id,
        'gesamtbetrag' => 100.0,
    ]);

    makeNotificationWorkflow()->freigeben($bestellung, $freigeber);

    Notification::assertSentTo($anforderer, BestellungFreigegebenNotification::class);
    Notification::assertSentTo($empfaenger, BestellungZurBestellungNotification::class);
    Notification::assertNotSentTo($anforderer, BestellungZurBestellungNotification::class);
});

it('benachrichtigt den anforderer wenn eine interne bestellung bestellt wurde', function (): void {
    $anforderer = User::factory()->create();
    $empfaenger = User::factory()->create();

    $bestellung = Bestellung::factory()->intern()->create([
        'status' => BestellungStatus::Freigegeben,
        'user_id' => $anforderer->id,
        'interner_empfaenger_user_id' => $empfaenger->id,
        'gesamtbetrag' => 100.0,
        'lieferantennummer' => '99999',
        'lieferantenname' => 'Test Lieferant',
    ]);

    makeNotificationWorkflow()->bestellen($bestellung, $empfaenger);

    Notification::assertSentTo($anforderer, BestellungBestelltNotification::class);
    Notification::assertNotSentTo($empfaenger, BestellungBestelltNotification::class);
});

it('sendet keine bestellt-benachrichtigung bei externer bestellung', function (): void {
    $anforderer = User::factory()->create();
    $anforderer->assignRole('Benutzer');

    $bestellung = Bestellung::factory()->extern()->create([
        'status' => BestellungStatus::Freigegeben,
        'user_id' => $anforderer->id,
        'gesamtbetrag' => 100.0,
    ]);

    makeNotificationWorkflow()->bestellen($bestellung, $anforderer);

    Notification::assertNotSentTo($anforderer, BestellungBestelltNotification::class);
});
