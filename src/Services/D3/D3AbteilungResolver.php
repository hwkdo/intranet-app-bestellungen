<?php

declare(strict_types=1);

namespace Hwkdo\IntranetAppBestellungen\Services\D3;

use Hwkdo\D3RestLaravel\Facades\D3RestLaravel;
use Hwkdo\IntranetAppBestellungen\Models\Bestellung;
use Hwkdo\IntranetAppBestellungen\Models\IntranetAppBestellungenSettings;
use Illuminate\Support\Facades\Log;

class D3AbteilungResolver
{
    /**
     * D3-Pflichtfeld „Abteilung“ (propertyFieldId 80): Gruppen der Bestellung, sonst D3-SOAP oder User-Fallback.
     *
     * @return array<int, string>
     */
    public function resolve(Bestellung $bestellung): array
    {
        $gruppen = collect($bestellung->gruppen ?? [])
            ->map(fn ($gruppe) => trim((string) $gruppe))
            ->filter()
            ->unique()
            ->values()
            ->all();

        if ($gruppen !== []) {
            return $gruppen;
        }

        $bestellung->loadMissing('user');
        $user = $bestellung->user;
        if (! $user) {
            return [];
        }

        try {
            $ttlSeconds = $this->soapUserGroupsCacheTtlSeconds();
            $soapGruppen = D3RestLaravel::getUserInGroupsSoapCached((string) $user->username, $ttlSeconds);
            if (is_array($soapGruppen) && $soapGruppen !== []) {
                return collect($soapGruppen)
                    ->map(fn ($gruppe) => (string) $gruppe)
                    ->filter()
                    ->unique()
                    ->values()
                    ->all();
            }
        } catch (\Throwable $e) {
            Log::warning('bestellungen.d3_groups_soap.failed', [
                'bestellung_id' => $bestellung->getKey(),
                'user_id' => $user->getKey(),
                'username' => $user->username,
                'error' => $e->getMessage(),
            ]);
        }

        $abteilung = $user->abteilung ?? null;

        return $abteilung ? [(string) $abteilung] : [];
    }

    private function soapUserGroupsCacheTtlSeconds(): int
    {
        $stunden = IntranetAppBestellungenSettings::resolvedAppSettings()->d3SoapUserGroupsCacheTtlStunden;

        return max(1, (int) $stunden) * 3600;
    }
}
