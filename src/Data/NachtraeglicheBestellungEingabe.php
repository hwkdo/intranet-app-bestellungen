<?php

declare(strict_types=1);

namespace Hwkdo\IntranetAppBestellungen\Data;

final class NachtraeglicheBestellungEingabe
{
    /**
     * @param  array<int, string>  $gruppen
     * @param  array<int, array<string, mixed>>  $kontierung
     * @param  array<int, array<string, mixed>>  $positionen
     */
    public function __construct(
        public string $lieferantennummer,
        public string $lieferantenname,
        public string $belegnummer,
        public string $belegdatum,
        public float $netto,
        public float $steuer,
        public float $brutto,
        public ?string $zahlungsart,
        public ?string $betreff,
        public string $begruendung,
        public string $kostenstelle,
        public int $haushaltsjahr,
        public ?int $projektId,
        public array $gruppen,
        public array $kontierung,
        public array $positionen,
        public ?int $lieferanschriftUserId,
        public ?string $auszahlungsEmpfaenger = null,
        public ?string $auszahlungsArt = null,
    ) {}
}
