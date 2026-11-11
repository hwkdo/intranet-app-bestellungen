<?php

declare(strict_types=1);

namespace Hwkdo\IntranetAppBestellungen\Services\Lieferant;

use Hwkdo\IntranetAppBestellungen\Models\LieferantCache;
use Illuminate\Support\Collection;

class LieferantErkennungService
{
    /**
     * @var Collection<string, Collection<int, LieferantCache>>|null
     */
    private ?Collection $nachName = null;

    public function zuordnen(string $erkannterName): ?LieferantCache
    {
        $ziel = $this->normalisieren($erkannterName);
        if ($ziel === '') {
            return null;
        }

        $treffer = $this->nachName()->get($ziel, collect());

        return $treffer->count() === 1 ? $treffer->first() : null;
    }

    public function normalisieren(string $name): string
    {
        $name = mb_strtolower(trim($name));
        $name = str_replace(['&', '+'], ' ', $name);
        $name = preg_replace('/[^\p{L}\p{N}]+/u', ' ', $name) ?? $name;
        $name = preg_replace('/\b(gmbh|mbh|ag|kg|ohg|gbr|ug|ek|e k|co)\b/u', ' ', $name) ?? $name;
        $name = preg_replace('/\s+/u', ' ', $name) ?? $name;

        return trim($name);
    }

    /**
     * @return Collection<string, Collection<int, LieferantCache>>
     */
    private function nachName(): Collection
    {
        if ($this->nachName instanceof Collection) {
            return $this->nachName;
        }

        $this->nachName = LieferantCache::query()
            ->get(['id', 'lieferantennummer', 'lieferantenname', 'strasse', 'hausnummer', 'plz', 'ort'])
            ->groupBy(fn (LieferantCache $lieferant): string => $this->normalisieren((string) $lieferant->lieferantenname));

        return $this->nachName;
    }
}
