<?php

declare(strict_types=1);

namespace Hwkdo\IntranetAppBestellungen\Services\Lieferant;

use Hwkdo\IntranetAppBestellungen\Models\LieferantCache;
use Illuminate\Support\Collection;

class LieferantSuggestionsService
{
    private const SUGGEST_LIMIT = 30;

    /**
     * @return Collection<int, LieferantCache>
     */
    public function suche(string $search, ?string $ausgewaehlteNummer = null, int $limit = self::SUGGEST_LIMIT): Collection
    {
        $term = trim($search);

        $results = LieferantCache::query()
            ->leftJoin(
                'intranet_app_bestellungen_lieferant_nutzung as ln',
                'ln.lieferantennummer',
                '=',
                'intranet_app_bestellungen_lieferanten_cache.lieferantennummer'
            )
            ->select('intranet_app_bestellungen_lieferanten_cache.*')
            ->when($term !== '', function ($q) use ($term): void {
                $like = '%'.$term.'%';
                $q->where(function ($inner) use ($like): void {
                    $inner->where('lieferantenname', 'like', $like)
                        ->orWhere('intranet_app_bestellungen_lieferanten_cache.lieferantennummer', 'like', $like);
                });
            })
            ->orderByRaw('(COALESCE(ln.legacy_bestellungen_count, 0) + COALESCE(ln.v3_bestellungen_count, 0)) DESC')
            ->orderBy('lieferantenname')
            ->limit($limit)
            ->get();

        if ($ausgewaehlteNummer && ! $results->firstWhere('lieferantennummer', $ausgewaehlteNummer)) {
            $row = LieferantCache::query()->where('lieferantennummer', $ausgewaehlteNummer)->first();
            if ($row) {
                $results->prepend($row);
            }
        }

        return $results;
    }
}
