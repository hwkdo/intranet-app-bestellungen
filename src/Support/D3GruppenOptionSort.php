<?php

declare(strict_types=1);

namespace Hwkdo\IntranetAppBestellungen\Support;

final class D3GruppenOptionSort
{
    /**
     * Ausgewählte Gruppen zuerst, innerhalb jeder Gruppe alphabetisch.
     *
     * @param  array<int, string>  $optionen
     * @param  array<int, string>  $auswahl
     * @return array<int, string>
     */
    public static function mitAuswahlZuerst(array $optionen, array $auswahl): array
    {
        $ausgewaehlt = collect($auswahl)
            ->map(fn (mixed $group): string => trim((string) $group))
            ->filter(fn (string $group): bool => $group !== '')
            ->flip();

        return collect($optionen)
            ->sortBy(fn (string $group): array => [
                $ausgewaehlt->has($group) ? 0 : 1,
                $group,
            ])
            ->values()
            ->all();
    }
}
