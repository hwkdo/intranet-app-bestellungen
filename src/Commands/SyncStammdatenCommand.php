<?php

declare(strict_types=1);

namespace Hwkdo\IntranetAppBestellungen\Commands;

use Hwkdo\IntranetAppBestellungen\Services\Stammdaten\StammdatenSyncService;
use Illuminate\Console\Command;

class SyncStammdatenCommand extends Command
{
    protected $signature = 'intranet-app-bestellungen:sync-stammdaten
        {--lieferanten : Nur Lieferanten synchronisieren}
        {--kostenstellen : Nur Kostenstellen synchronisieren}';

    protected $description = 'Synchronisiert Lieferanten und Kostenstellen aus der bue-laravel-Datenquelle in die lokalen Cache-Tabellen.';

    public function handle(StammdatenSyncService $service): int
    {
        $onlyLieferanten = (bool) $this->option('lieferanten');
        $onlyKostenstellen = (bool) $this->option('kostenstellen');

        if ($onlyLieferanten || (! $onlyLieferanten && ! $onlyKostenstellen)) {
            $result = $service->syncLieferanten();
            $this->info(sprintf('%d Lieferanten synchronisiert.', $result['count']));
        }

        if ($onlyKostenstellen || (! $onlyLieferanten && ! $onlyKostenstellen)) {
            $result = $service->syncKostenstellen();
            $this->info(sprintf('%d Kostenstellen synchronisiert.', $result['count']));
        }

        return self::SUCCESS;
    }
}
