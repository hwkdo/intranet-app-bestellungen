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
    Role::findOrCreate('App-Bestellungen-Admin', 'web');
});

it('liefert offene Freigaben mit korrektem Direkt-Anchor', function (): void {
    $admin = User::factory()->create();
    $admin->assignRole('App-Bestellungen-Admin');

    $besteller = User::factory()->create();

    IntranetAppBestellungenSettings::create([
        'version' => 1,
        'settings' => AppSettings::from([
            'freigabeStufen' => [
                ['bezeichnung' => 'Standard', 'bisBetrag' => null, 'freigeberRollen' => ['App-Bestellungen-Admin']],
            ],
        ])->toArray(),
    ]);

    $bestellung = Bestellung::factory()->create([
        'status' => BestellungStatus::ZurFreigabe,
        'user_id' => $besteller->id,
        'freigeber_id' => null,
        'gesamtbetrag' => 750.00,
    ]);

    $provider = new FreigabeAusstehendTaskProvider(new WertgrenzenService);

    $tasks = $provider->getTasksForUser($admin);

    expect($tasks)->toHaveCount(1);
    expect($tasks->first()->url)->toContain('aktion=freigeben');
    expect($tasks->first()->url)->toContain((string) $bestellung->id);
    expect($tasks->first()->appIdentifier)->toBe('bestellungen');
});

it('liefert keine Tasks für andere User', function (): void {
    $other = User::factory()->create();
    $besteller = User::factory()->create();

    IntranetAppBestellungenSettings::create([
        'version' => 1,
        'settings' => AppSettings::from([
            'freigabeStufen' => [
                ['bezeichnung' => 'Standard', 'bisBetrag' => null, 'freigeberRollen' => ['App-Bestellungen-Admin']],
            ],
        ])->toArray(),
    ]);

    Bestellung::factory()->create([
        'status' => BestellungStatus::ZurFreigabe,
        'user_id' => $besteller->id,
        'freigeber_id' => null,
    ]);

    $provider = new FreigabeAusstehendTaskProvider(new WertgrenzenService);

    expect($provider->getTasksForUser($other))->toBeEmpty();
});
