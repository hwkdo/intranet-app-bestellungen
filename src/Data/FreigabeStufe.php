<?php

declare(strict_types=1);

namespace Hwkdo\IntranetAppBestellungen\Data;

use Spatie\LaravelData\Data;

class FreigabeStufe extends Data
{
    /**
     * @param  string        $bezeichnung          Anzeigename der Stufe
     * @param  float         $vonBetrag            Untergrenze (inklusiv), i.d.R. 0
     * @param  float|null    $bisBetrag            Obergrenze (inklusiv), null = unbegrenzt
     * @param  array<int, string>  $berechtigteAttribute  User-Attribute die berechtigen ('ist_fk','ist_al',…)
     * @param  array<int, string>  $berechtigteRollen     Spatie-Rollen die berechtigen
     * @param  string|null   $textBerechtigt       Informationstext: wer darf bestellen
     * @param  string|null   $textFreigeber1       Informationstext: wer ist Freigeber 1
     * @param  string|null   $textFreigeber2       Informationstext: wer ist Freigeber 2
     * @param  array<int, array<string, mixed>>  $freigabe1Regeln  Regelwerk Freigabe 1
     * @param  array<int, array<string, mixed>>  $freigabe2Regeln  Regelwerk Freigabe 2 (leer = keine zweite Freigabe)
     */
    public function __construct(
        public string $bezeichnung = 'Standard',
        public float $vonBetrag = 0,
        public ?float $bisBetrag = null,
        /** @var array<int, string> */
        public array $berechtigteAttribute = [],
        /** @var array<int, string> */
        public array $berechtigteRollen = [],
        public ?string $textBerechtigt = null,
        public ?string $textFreigeber1 = null,
        public ?string $textFreigeber2 = null,
        /** @var array<int, array<string, mixed>> */
        public array $freigabe1Regeln = [],
        /** @var array<int, array<string, mixed>> */
        public array $freigabe2Regeln = [],
    ) {}

    /**
     * @return array<int, FreigebeRegel>
     */
    public function freigabe1RegelObjekte(): array
    {
        return array_values(array_map(
            static fn (array $row): FreigebeRegel => FreigebeRegel::from($row),
            $this->freigabe1Regeln,
        ));
    }

    /**
     * @return array<int, FreigebeRegel>
     */
    public function freigabe2RegelObjekte(): array
    {
        return array_values(array_map(
            static fn (array $row): FreigebeRegel => FreigebeRegel::from($row),
            $this->freigabe2Regeln,
        ));
    }

    public function hatZweiteFreigabe(): bool
    {
        return ! empty($this->freigabe2Regeln);
    }
}
