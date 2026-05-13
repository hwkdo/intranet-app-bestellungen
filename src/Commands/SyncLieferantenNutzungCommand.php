<?php

declare(strict_types=1);

namespace Hwkdo\IntranetAppBestellungen\Commands;

use Hwkdo\IntranetAppBestellungen\Services\Stammdaten\LieferantNutzungSyncService;
use Illuminate\Console\Command;

class SyncLieferantenNutzungCommand extends Command
{
    protected $signature = 'intranet-app-bestellungen:sync-lieferanten-nutzung';

    protected $description = 'Synchronisiert die Lieferanten-Bestellungszähler aus dem Legacy-System in die lokale Nutzungstabelle.';

    public function handle(LieferantNutzungSyncService $service): int
    {
        $this->info('Synchronisiere Lieferanten-Nutzung aus Legacy…');

        $result = $service->syncFromLegacy();

        $this->info(sprintf('%d Lieferanten-Nutzungseinträge aktualisiert.', $result['count']));

        return self::SUCCESS;
    }
}
