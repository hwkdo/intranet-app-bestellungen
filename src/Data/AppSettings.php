<?php

declare(strict_types=1);

namespace Hwkdo\IntranetAppBestellungen\Data;

use Hwkdo\IntranetAppBase\Data\Attributes\Description;
use Hwkdo\IntranetAppBase\Data\BaseAppSettings;

class AppSettings extends BaseAppSettings
{
    /**
     * @param  array<int, array<string, mixed>>  $freigabeStufen
     * @param  array<int, array<string, mixed>>  $angebotsRegeln
     */
    public function __construct(
        #[Description('Maximale Anzahl von Bestellungen pro Seite (Listen)')]
        public int $maxItemsPerPage = 25,

        #[Description('BEN-Präfixziffer für neue Bestellungen (Legacy: "3" Produktion, "1" lokal/Entwicklung)')]
        public string $benNummerPrefix = '3',

        #[Description('Wertgrenzen-Stufen mit Freigebern (JSON-Array von FreigabeStufen)')]
        public array $freigabeStufen = [
            [
                'bezeichnung' => 'Standard',
                'bisBetrag' => null,
                'freigeberUserIds' => [],
                'freigeberRollen' => ['App-Bestellungen-Admin'],
                'zweiteFreigabeErforderlich' => false,
                'zweiteFreigabeAb' => null,
            ],
        ],

        #[Description('Regeln zur Erfordernis von Vergleichsangeboten (JSON-Array von AngebotsRegeln)')]
        public array $angebotsRegeln = [
            [
                'abBetrag' => 0,
                'mindestAngebote' => 0,
                'begruendungErlaubt' => true,
                'hinweisText' => null,
            ],
        ],

        #[Description('D3-Notizen zu Bestellungen automatisch nach D3 synchronisieren')]
        public bool $d3NotizenSyncAktiv = true,

        #[Description('Bestellschein nach Status-Änderung auf "Bestellt" automatisch nach D3 pushen')]
        public bool $autoPushBeiBestellt = true,
    ) {}

    /**
     * @return array<int, FreigabeStufe>
     */
    public function freigabeStufenObjekte(): array
    {
        return array_values(array_map(
            static fn (array $row): FreigabeStufe => FreigabeStufe::from($row),
            $this->freigabeStufen,
        ));
    }

    /**
     * @return array<int, AngebotsRegel>
     */
    public function angebotsRegelnObjekte(): array
    {
        return array_values(array_map(
            static fn (array $row): AngebotsRegel => AngebotsRegel::from($row),
            $this->angebotsRegeln,
        ));
    }
}
