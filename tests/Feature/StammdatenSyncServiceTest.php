<?php

declare(strict_types=1);

use Hwkdo\BueLaravel\Facades\BueLaravel;
use Hwkdo\IntranetAppBestellungen\Models\KostenstelleCache;
use Hwkdo\IntranetAppBestellungen\Services\Stammdaten\StammdatenSyncService;
use Illuminate\Support\Facades\Log;

function seedKostenstelleCache(string $nummer, bool $aktiv = true): KostenstelleCache
{
    return KostenstelleCache::query()->create([
        'kostenstelle' => $nummer,
        'bezeichnung' => $nummer,
        'aktiv' => $aktiv,
        'synced_at' => now()->subMonth(),
    ]);
}

it('markiert Kostenstellen als inaktiv die in der Quelle nicht mehr vorkommen', function (): void {
    seedKostenstelleCache('1010');
    seedKostenstelleCache('1336');

    BueLaravel::shouldReceive('getKostenstellen')
        ->once()
        ->andReturn(collect([
            (object) ['kostenstelle' => '1010', 'kobe' => 'AdA BZA Allg.'],
        ]));

    $result = app(StammdatenSyncService::class)->syncKostenstellen();

    expect($result['count'])->toBe(1)
        ->and($result['deactivated'])->toBe(1);

    $aktive = KostenstelleCache::query()->where('kostenstelle', '1010')->first();
    $entfallene = KostenstelleCache::query()->where('kostenstelle', '1336')->first();

    expect($aktive)->not->toBeNull()
        ->and($aktive->aktiv)->toBeTrue()
        ->and($aktive->bezeichnung)->toBe('AdA BZA Allg.');

    expect($entfallene)->not->toBeNull()
        ->and($entfallene->aktiv)->toBeFalse();
});

it('aktiviert zuvor deaktivierte Kostenstellen wieder wenn sie in der Quelle sind', function (): void {
    seedKostenstelleCache('1336', aktiv: false);

    BueLaravel::shouldReceive('getKostenstellen')
        ->once()
        ->andReturn(collect([
            (object) ['kostenstelle' => '1336', 'kobe' => 'Wieder da'],
        ]));

    $result = app(StammdatenSyncService::class)->syncKostenstellen();

    expect($result['count'])->toBe(1)
        ->and($result['deactivated'])->toBe(0);

    $kostenstelle = KostenstelleCache::query()->where('kostenstelle', '1336')->first();

    expect($kostenstelle)->not->toBeNull()
        ->and($kostenstelle->aktiv)->toBeTrue()
        ->and($kostenstelle->bezeichnung)->toBe('Wieder da');
});

it('deaktiviert keine Kostenstellen wenn die Quelle leer ist', function (): void {
    seedKostenstelleCache('1336');

    BueLaravel::shouldReceive('getKostenstellen')
        ->once()
        ->andReturn(collect());

    Log::fake();

    $result = app(StammdatenSyncService::class)->syncKostenstellen();

    expect($result['count'])->toBe(0)
        ->and($result['deactivated'])->toBe(0);

    expect(KostenstelleCache::query()->where('kostenstelle', '1336')->value('aktiv'))->toBeTrue();
});

it('zählt bereits inaktive Kostenstellen nicht erneut als deaktiviert', function (): void {
    seedKostenstelleCache('1010');
    seedKostenstelleCache('1336', aktiv: false);

    BueLaravel::shouldReceive('getKostenstellen')
        ->once()
        ->andReturn(collect([
            (object) ['kostenstelle' => '1010', 'kobe' => 'AdA BZA Allg.'],
        ]));

    $result = app(StammdatenSyncService::class)->syncKostenstellen();

    expect($result['deactivated'])->toBe(0);

    expect(KostenstelleCache::query()->where('kostenstelle', '1336')->value('aktiv'))->toBeFalse();
});
