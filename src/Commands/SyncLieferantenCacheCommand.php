<?php

declare(strict_types=1);

namespace Hwkdo\IntranetAppBestellungen\Commands;

use Hwkdo\IntranetAppBase\Services\BackgroundActivityCatalog;
use Hwkdo\IntranetAppBase\Services\BackgroundActivityConfigStore;
use Hwkdo\IntranetAppBestellungen\Background\BestellungenBackgroundActivities;
use Hwkdo\IntranetAppBestellungen\Services\Stammdaten\StammdatenSyncService;
use Illuminate\Console\Command;

class SyncLieferantenCacheCommand extends Command
{
    public const COMMAND = 'intranet-app-bestellungen:sync-lieferanten-cache';

    protected $signature = self::COMMAND.'
        {--force : Auch ausführen, wenn die Hintergrundaktivität deaktiviert ist}';

    protected $description = 'Aktualisiert den Lieferanten-Cache aus der BÜ.';

    public function handle(StammdatenSyncService $service): int
    {
        $definition = app(BackgroundActivityCatalog::class)->find(BestellungenBackgroundActivities::LIEFERANTEN_CACHE);
        $store = app(BackgroundActivityConfigStore::class);

        if ($definition !== null && ! $store->isEnabled($definition) && ! $this->option('force')) {
            $this->info('Die Aktualisierung des Lieferanten-Caches ist deaktiviert.');

            return self::SUCCESS;
        }

        $result = $service->syncLieferanten();
        $this->info(sprintf('%d Lieferanten synchronisiert.', $result['count']));

        return self::SUCCESS;
    }
}
