<?php

declare(strict_types=1);

use App\Models\User;
use Hwkdo\IntranetAppBestellungen\Data\AppSettings;
use Hwkdo\IntranetAppBestellungen\Enums\BestellungStatus;
use Hwkdo\IntranetAppBestellungen\Models\Bestellung;
use Hwkdo\IntranetAppBestellungen\Models\IntranetAppBestellungenSettings;
use Hwkdo\IntranetAppBestellungen\Services\WertgrenzenService;
use Hwkdo\IntranetAppBestellungen\Tasks\FreigabeAusstehendTaskProvider;
use Spatie\Permission\Models\Role;

beforeEach(function (): void {
    Role::findOrCreate('Benutzer', 'web');

    IntranetAppBestellungenSettings::create([
        'version' => 1,
        'settings' => AppSettings::from([
            'freigabeStufen' => [
                [
                    'bezeichnung' => 'Standard',
                    'bisBetrag' => null,
                    'berechtigteRollen' => ['Benutzer'],
                    'freigabe1Regeln' => [
                        [
                            'typ' => 'default',
                            'keinFreigeber' => false,
                            'quelleTyp' => 'single',
                            'quelle' => 'vorgesetzter',
                            'excludeAttribute' => [],
                        ],
                    ],
                ],
            ],
        ])->toArray(),
    ]);
});

it('liefert offene Freigaben für den zugewiesenen Freigeber', function (): void {
    $freigeber = User::factory()->create();
    $besteller = User::factory()->create();

    $bestellung = Bestellung::factory()->create([
        'status' => BestellungStatus::ZurFreigabe,
        'user_id' => $besteller->id,
        'freigeber_id' => $freigeber->id,
        'gesamtbetrag' => 750.00,
    ]);

    $provider = new FreigabeAusstehendTaskProvider(new WertgrenzenService);

    $tasks = $provider->getTasksForUser($freigeber);

    expect($tasks)->toHaveCount(1);
    expect($tasks->first()->url)->toContain('aktion=freigeben');
    expect($tasks->first()->url)->toContain((string) $bestellung->id);
    expect($tasks->first()->appIdentifier)->toBe('bestellungen');
});

it('liefert Tasks auch wenn der zugewiesene Freigeber nicht im Wertgrenzen-Pool ist', function (): void {
    $vertretung = User::factory()->create();
    $besteller = User::factory()->create();

    Bestellung::factory()->create([
        'status' => BestellungStatus::ZurFreigabe,
        'user_id' => $besteller->id,
        'freigeber_id' => $vertretung->id,
        'gesamtbetrag' => 800.00,
    ]);

    $provider = new FreigabeAusstehendTaskProvider(new WertgrenzenService);

    expect($provider->getTasksForUser($vertretung))->toHaveCount(1);
});

it('liefert keine Tasks für andere User', function (): void {
    $freigeber = User::factory()->create();
    $other = User::factory()->create();
    $besteller = User::factory()->create();

    Bestellung::factory()->create([
        'status' => BestellungStatus::ZurFreigabe,
        'user_id' => $besteller->id,
        'freigeber_id' => $freigeber->id,
    ]);

    $provider = new FreigabeAusstehendTaskProvider(new WertgrenzenService);

    expect($provider->getTasksForUser($other))->toBeEmpty();
});
