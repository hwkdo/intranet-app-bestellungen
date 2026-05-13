<?php

declare(strict_types=1);

namespace Hwkdo\IntranetAppBestellungen\Services\Stammdaten;

use Hwkdo\BueLaravel\Facades\BueLaravel;
use Hwkdo\IntranetAppBestellungen\Models\KostenstelleCache;
use Hwkdo\IntranetAppBestellungen\Models\LieferantCache;
use Hwkdo\IntranetAppBestellungen\Support\Utf8MojibakeFixer;
use Illuminate\Support\Facades\Log;

class StammdatenSyncService
{
    /**
     * @return array{count:int}
     */
    public function syncLieferanten(): array
    {
        $count = 0;
        $now = now();

        foreach (BueLaravel::getAllLieferanten() as $row) {
            $row = (array) $row;
            $nummer = (string) ($row['lieferantennummer'] ?? '');
            if ($nummer === '') {
                continue;
            }

            $name = (string) ($row['lieferantenname'] ?? '');
            $strasse = $row['lieferantenstrasse'] ?? null;
            $hausnummer = $row['lieferantenhausnummer'] ?? null;
            $plz = $row['lieferantenplz'] ?? null;
            $ort = $row['lieferantenort'] ?? null;

            LieferantCache::updateOrCreate(
                ['lieferantennummer' => $nummer],
                [
                    'lieferantenname' => Utf8MojibakeFixer::fixIfLikelyMojibake($name) ?? '',
                    'strasse' => $strasse === null ? null : Utf8MojibakeFixer::fixIfLikelyMojibake((string) $strasse),
                    'hausnummer' => $hausnummer === null ? null : Utf8MojibakeFixer::fixIfLikelyMojibake((string) $hausnummer),
                    'plz' => $plz === null ? null : Utf8MojibakeFixer::fixIfLikelyMojibake((string) $plz),
                    'ort' => $ort === null ? null : Utf8MojibakeFixer::fixIfLikelyMojibake((string) $ort),
                    'synced_at' => $now,
                ],
            );
            $count++;
        }

        Log::info('bestellungen.stammdaten_sync.lieferanten', ['count' => $count]);

        return ['count' => $count];
    }

    /**
     * @return array{count:int}
     */
    public function syncKostenstellen(): array
    {
        $count = 0;
        $now = now();

        foreach (BueLaravel::getKostenstellen() as $row) {
            $row = (array) $row;
            $nummer = (string) ($row['kostenstelle'] ?? '');
            if ($nummer === '') {
                continue;
            }

            KostenstelleCache::updateOrCreate(
                ['kostenstelle' => $nummer],
                [
                    'bezeichnung' => $row['kobe'] ?? $row['bezeichnung'] ?? null,
                    'aktiv' => isset($row['aktiv']) ? (bool) $row['aktiv'] : true,
                    'synced_at' => $now,
                ],
            );
            $count++;
        }

        Log::info('bestellungen.stammdaten_sync.kostenstellen', ['count' => $count]);

        return ['count' => $count];
    }

    public function syncAlle(): array
    {
        return [
            'lieferanten' => $this->syncLieferanten(),
            'kostenstellen' => $this->syncKostenstellen(),
        ];
    }

    /**
     * Synchronisiert nur, falls der jeweilige Cache leer ist (Initial-Befüllung).
     */
    public function syncIfEmpty(): array
    {
        $result = ['lieferanten' => null, 'kostenstellen' => null];

        if (LieferantCache::query()->doesntExist()) {
            try {
                $result['lieferanten'] = $this->syncLieferanten();
            } catch (\Throwable $e) {
                Log::warning('bestellungen.stammdaten_sync.lieferanten_failed', ['error' => $e->getMessage()]);
            }
        }

        if (KostenstelleCache::query()->doesntExist()) {
            try {
                $result['kostenstellen'] = $this->syncKostenstellen();
            } catch (\Throwable $e) {
                Log::warning('bestellungen.stammdaten_sync.kostenstellen_failed', ['error' => $e->getMessage()]);
            }
        }

        return $result;
    }
}
