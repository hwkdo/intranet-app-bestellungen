<?php

declare(strict_types=1);

use App\Models\User;
use Hwkdo\IntranetAppBestellungen\Enums\BestellungStatus;
use Hwkdo\IntranetAppBestellungen\Models\Bestellung;
use Hwkdo\IntranetAppBestellungen\Support\PlatzhalterLieferant;
use Hwkdo\IntranetAppBestellungen\Tasks\InterneBestellungAusstehendTaskProvider;

it('liefert freigegebene interne Bestellungen mit Direkt-Anchor bestellen', function (): void {
    $empfaenger = User::factory()->create();
    $besteller = User::factory()->create();

    $bestellung = Bestellung::factory()->intern()->create([
        'status' => BestellungStatus::Freigegeben,
        'user_id' => $besteller->id,
        'interner_empfaenger_user_id' => $empfaenger->id,
        'lieferantennummer' => PlatzhalterLieferant::nummer(),
    ]);

    $provider = new InterneBestellungAusstehendTaskProvider;

    $tasks = $provider->getTasksForUser($empfaenger);

    expect($tasks)->toHaveCount(1);
    expect($tasks->first()->url)->toContain('aktion=bestellen');
    expect($tasks->first()->url)->toContain((string) $bestellung->id);
    expect($tasks->first()->appIdentifier)->toBe('bestellungen');
});

it('liefert keine Tasks für andere Empfänger oder abgeschlossene Bestellungen', function (): void {
    $empfaenger = User::factory()->create();
    $fremder = User::factory()->create();

    Bestellung::factory()->intern()->bestellt()->create([
        'interner_empfaenger_user_id' => $empfaenger->id,
    ]);

    Bestellung::factory()->intern()->create([
        'status' => BestellungStatus::Freigegeben,
        'interner_empfaenger_user_id' => $empfaenger->id,
        'lieferantennummer' => PlatzhalterLieferant::nummer(),
    ]);

    $provider = new InterneBestellungAusstehendTaskProvider;

    expect($provider->getTasksForUser($fremder))->toBeEmpty();
    expect($provider->getTasksForUser($empfaenger))->toHaveCount(1);
});
