<?php

declare(strict_types=1);

use App\Models\User;
use Hwkdo\IntranetAppBestellungen\Enums\BestellungStatus;
use Hwkdo\IntranetAppBestellungen\Models\Bestellung;
use Hwkdo\IntranetAppBestellungen\Tasks\BestellungAusfuehrenTaskProvider;

it('liefert freigegebene externe Bestellungen des Auftraggebers mit Bestellen-Anker', function (): void {
    $auftraggeber = User::factory()->create();

    $bestellung = Bestellung::factory()->extern()->create([
        'status' => BestellungStatus::Freigegeben,
        'user_id' => $auftraggeber->id,
    ]);

    $provider = new BestellungAusfuehrenTaskProvider;

    $tasks = $provider->getTasksForUser($auftraggeber);

    expect($tasks)->toHaveCount(1);
    expect($tasks->first()->url)->toContain('aktion=bestellen');
    expect($tasks->first()->url)->toContain((string) $bestellung->id);
    expect($tasks->first()->appIdentifier)->toBe('bestellungen');
});

it('liefert keine Tasks für fremde Nutzer, interne oder bereits bestellte Bestellungen', function (): void {
    $auftraggeber = User::factory()->create();
    $fremder = User::factory()->create();

    Bestellung::factory()->extern()->create([
        'status' => BestellungStatus::Freigegeben,
        'user_id' => $auftraggeber->id,
    ]);

    Bestellung::factory()->extern()->bestellt()->create([
        'user_id' => $auftraggeber->id,
    ]);

    Bestellung::factory()->intern()->create([
        'status' => BestellungStatus::Freigegeben,
        'user_id' => $auftraggeber->id,
        'interner_empfaenger_user_id' => User::factory()->create()->id,
    ]);

    $provider = new BestellungAusfuehrenTaskProvider;

    expect($provider->getTasksForUser($fremder))->toBeEmpty();
    expect($provider->getTasksForUser($auftraggeber))->toHaveCount(1);
});
