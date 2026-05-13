<?php

declare(strict_types=1);

namespace Hwkdo\IntranetAppBestellungen\Services\Stammdaten;

use App\Services\IntranetLegacyService;
use Hwkdo\IntranetAppBestellungen\Models\LieferantNutzung;
use Illuminate\Support\Facades\Log;

class LieferantNutzungSyncService
{
    public function __construct(
        private readonly IntranetLegacyService $legacyService,
    ) {}

    /**
     * Synchronisiert die Bestellungszähler aus dem Legacy-System in die lokale Nutzungstabelle.
     * Nur legacy_bestellungen_count und legacy_synced_at werden überschrieben;
     * v3_bestellungen_count bleibt unberührt.
     *
     * @return array{count: int}
     */
    public function syncFromLegacy(): array
    {
        $counts = $this->legacyService->getLieferantenBestellungsCounts();

        if ($counts === []) {
            Log::warning('bestellungen.lieferant_nutzung_sync.leer', [
                'hinweis' => 'Legacy hat keine Daten geliefert oder ist nicht erreichbar.',
            ]);

            return ['count' => 0];
        }

        $now = now();
        $updated = 0;

        foreach ($counts as $lieferantennummer => $anzahl) {
            $nummer = (string) $lieferantennummer;
            if ($nummer === '') {
                continue;
            }

            LieferantNutzung::upsert(
                [
                    'lieferantennummer' => $nummer,
                    'legacy_bestellungen_count' => $anzahl,
                    'legacy_synced_at' => $now,
                ],
                uniqueBy: ['lieferantennummer'],
                update: ['legacy_bestellungen_count', 'legacy_synced_at'],
            );

            $updated++;
        }

        Log::info('bestellungen.lieferant_nutzung_sync.abgeschlossen', ['count' => $updated]);

        return ['count' => $updated];
    }
}
