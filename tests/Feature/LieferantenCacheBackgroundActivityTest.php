<?php

declare(strict_types=1);

use Hwkdo\BueLaravel\Facades\BueLaravel;
use Hwkdo\IntranetAppBase\Enums\BackgroundActivityCadence;
use Hwkdo\IntranetAppBase\Enums\BackgroundActivityKind;
use Hwkdo\IntranetAppBase\Services\BackgroundActivityCatalog;
use Hwkdo\IntranetAppBase\Services\BackgroundActivityConfigStore;
use Hwkdo\IntranetAppBestellungen\Background\BestellungenBackgroundActivities;
use Hwkdo\IntranetAppBestellungen\Commands\SyncLieferantenCacheCommand;
use Hwkdo\IntranetAppBestellungen\Models\LieferantCache;

it('bietet den Lieferanten-Cache als geplante Hintergrundaktivität mit wählbarer Kadenz', function (): void {
    $definition = app(BackgroundActivityCatalog::class)->find(BestellungenBackgroundActivities::LIEFERANTEN_CACHE);

    expect($definition)->not->toBeNull()
        ->and($definition->kind)->toBe(BackgroundActivityKind::Scheduled)
        ->and($definition->command)->toBe(SyncLieferantenCacheCommand::COMMAND)
        ->and($definition->defaultFrequency?->toCron())->toBe('15 2 * * *')
        ->and($definition->resolvedAllowedCadences())->toBe(BackgroundActivityCadence::cases());
});

it('übernimmt Lieferanten aus der BÜ in den Cache', function (): void {
    BueLaravel::shouldReceive('getAllLieferanten')
        ->once()
        ->andReturn(collect([
            (object) [
                'lieferantennummer' => '4711',
                'lieferantenname' => 'Nord GmbH',
            ],
        ]));

    $this->artisan(SyncLieferantenCacheCommand::COMMAND)
        ->expectsOutput('1 Lieferanten synchronisiert.')
        ->assertSuccessful();

    expect(LieferantCache::query()->where('lieferantennummer', '4711')->value('lieferantenname'))->toBe('Nord GmbH');
});

it('aktualisiert den Cache nicht, wenn die Hintergrundaktivität ausgeschaltet ist', function (): void {
    $definition = app(BackgroundActivityCatalog::class)->find(BestellungenBackgroundActivities::LIEFERANTEN_CACHE);

    app(BackgroundActivityConfigStore::class)->save($definition, [
        'enabled' => false,
        'cadence' => BackgroundActivityCadence::DailyAt->value,
        'every' => 1,
        'offset' => 0,
        'at' => '02:15',
        'day_of_week' => 1,
        'parameters' => [],
    ]);

    BueLaravel::shouldReceive('getAllLieferanten')->never();

    $this->artisan(SyncLieferantenCacheCommand::COMMAND)
        ->expectsOutput('Die Aktualisierung des Lieferanten-Caches ist deaktiviert.')
        ->assertSuccessful();

    expect(LieferantCache::query()->count())->toBe(0);
});
