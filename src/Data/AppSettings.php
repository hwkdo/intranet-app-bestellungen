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

        #[Description('Wertgrenzen-Stufen mit Freigeber-Regelwerken (JSON-Array von FreigabeStufen)')]
        public array $freigabeStufen = [
            // Stufe a: 0–500 € – AL, GL, FK, Dozent oder Sachbearbeiter
            [
                'bezeichnung' => 'Bis 500 €',
                'vonBetrag' => 0,
                'bisBetrag' => 500,
                'berechtigteAttribute' => ['ist_fk', 'ist_gl', 'ist_al', 'ist_gf', 'ist_dozent'],
                'berechtigteRollen' => [
                    'Benutzer',
                    'Bestellungen-0-Bis-500',
                    'Bestellungen-500-Bis-5000',
                    'Bestellungen-5000-Bis-25000',
                ],
                'textBerechtigt' => 'AL, GL, FK, Ausbilder/in oder SB',
                'textFreigeber1' => 'entfällt bzw. FK/GL, soweit Ausbilder/in oder SB bestellt',
                'textFreigeber2' => null,
                'freigabe1Regeln' => [
                    // Rolle Bestellungen-InAuftrag → alle Vorgesetzten
                    ['typ' => 'if_rolle', 'bedingung' => 'Bestellungen-InAuftrag', 'keinFreigeber' => false, 'quelleTyp' => 'multi', 'quelle' => 'getAlleVorgesetzte', 'excludeAttribute' => []],
                    // Rollen die direkt bestellen dürfen → kein Freigeber
                    ['typ' => 'if_rolle', 'bedingung' => 'Bestellungen-0-Bis-500', 'keinFreigeber' => true, 'quelleTyp' => 'single', 'quelle' => 'vorgesetzter', 'excludeAttribute' => []],
                    ['typ' => 'if_rolle', 'bedingung' => 'Bestellungen-500-Bis-5000', 'keinFreigeber' => true, 'quelleTyp' => 'single', 'quelle' => 'vorgesetzter', 'excludeAttribute' => []],
                    ['typ' => 'if_rolle', 'bedingung' => 'Bestellungen-5000-Bis-25000', 'keinFreigeber' => true, 'quelleTyp' => 'single', 'quelle' => 'vorgesetzter', 'excludeAttribute' => []],
                    // Dozent ohne FBK → Vorgesetztenkette
                    ['typ' => 'if_attribute', 'bedingung' => 'ist_dozent_aber_kein_fbk', 'keinFreigeber' => false, 'quelleTyp' => 'multi', 'quelle' => 'getVorgesetzte', 'excludeAttribute' => []],
                    // SB → Vorgesetztenkette
                    ['typ' => 'if_attribute', 'bedingung' => 'ist_sb', 'keinFreigeber' => false, 'quelleTyp' => 'multi', 'quelle' => 'getVorgesetzte', 'excludeAttribute' => []],
                    // default: kein Freigeber
                    ['typ' => 'default', 'bedingung' => null, 'keinFreigeber' => true, 'quelleTyp' => 'single', 'quelle' => 'vorgesetzter', 'excludeAttribute' => []],
                ],
                'freigabe2Regeln' => [],
            ],
            // Stufe b: 500,01–5.000 €
            [
                'bezeichnung' => '500,01 € – 5.000 €',
                'vonBetrag' => 500.01,
                'bisBetrag' => 5000,
                'berechtigteAttribute' => ['ist_fk', 'ist_gl', 'ist_al', 'ist_gf', 'ist_dozent'],
                'berechtigteRollen' => ['Bestellungen-500-Bis-5000', 'Bestellungen-5000-Bis-25000'],
                'textBerechtigt' => 'AL, GL, FK, Ausbilder/in oder bevollmächt. SB',
                'textFreigeber1' => 'Vorgesetzte/r des/r Besteller/in',
                'textFreigeber2' => null,
                'freigabe1Regeln' => [
                    // Rolle Bestellungen-InAuftrag → alle Vorgesetzten
                    ['typ' => 'if_rolle', 'bedingung' => 'Bestellungen-InAuftrag', 'keinFreigeber' => false, 'quelleTyp' => 'multi', 'quelle' => 'getAlleVorgesetzte', 'excludeAttribute' => []],
                    // Dozent → Vorgesetztenkette
                    ['typ' => 'if_attribute', 'bedingung' => 'ist_dozent', 'keinFreigeber' => false, 'quelleTyp' => 'multi', 'quelle' => 'getVorgesetzte', 'excludeAttribute' => []],
                    // default: direkter Vorgesetzter (außer GF)
                    ['typ' => 'default', 'bedingung' => null, 'keinFreigeber' => false, 'quelleTyp' => 'single', 'quelle' => 'vorgesetzter', 'excludeAttribute' => ['ist_gf']],
                ],
                'freigabe2Regeln' => [],
            ],
            // Stufe c: 5.000,01–25.000 €
            [
                'bezeichnung' => '5.000,01 € – 25.000 €',
                'vonBetrag' => 5000.01,
                'bisBetrag' => 25000,
                'berechtigteAttribute' => ['ist_al', 'ist_gf'],
                'berechtigteRollen' => ['Bestellungen-5000-Bis-25000'],
                'textBerechtigt' => 'AL oder bevollmächt. GL, FK + SB',
                'textFreigeber1' => 'Vorgesetzte/r des/r Besteller/in',
                'textFreigeber2' => 'GF',
                'freigabe1Regeln' => [
                    // default: direkter Vorgesetzter (außer GF)
                    ['typ' => 'default', 'bedingung' => null, 'keinFreigeber' => false, 'quelleTyp' => 'single', 'quelle' => 'vorgesetzter', 'excludeAttribute' => ['ist_gf']],
                ],
                'freigabe2Regeln' => [
                    // Rolle Bestellungen-5000-Bis-25000 → GF-Gruppe
                    ['typ' => 'if_rolle', 'bedingung' => 'Bestellungen-5000-Bis-25000', 'keinFreigeber' => false, 'quelleTyp' => 'gruppe', 'quelle' => 'GB', 'excludeAttribute' => []],
                    // default: kein Freigeber 2
                    ['typ' => 'default', 'bedingung' => null, 'keinFreigeber' => true, 'quelleTyp' => 'single', 'quelle' => 'vorgesetzter', 'excludeAttribute' => []],
                ],
            ],
            // Stufe d: 25.000,01–100.000 €
            [
                'bezeichnung' => '25.000,01 € – 100.000 €',
                'vonBetrag' => 25000.01,
                'bisBetrag' => 100000,
                'berechtigteAttribute' => ['ist_al', 'ist_gf'],
                'berechtigteRollen' => [],
                'textBerechtigt' => 'AL',
                'textFreigeber1' => 'Vorgesetzte/r des/r Besteller/in',
                'textFreigeber2' => 'HGF',
                'freigabe1Regeln' => [
                    // AL → direkter Vorgesetzter
                    ['typ' => 'if_attribute', 'bedingung' => 'ist_al', 'keinFreigeber' => false, 'quelleTyp' => 'single', 'quelle' => 'vorgesetzter', 'excludeAttribute' => []],
                    // default: kein Freigeber
                    ['typ' => 'default', 'bedingung' => null, 'keinFreigeber' => true, 'quelleTyp' => 'single', 'quelle' => 'vorgesetzter', 'excludeAttribute' => []],
                ],
                'freigabe2Regeln' => [
                    // default: HGF-Gruppe
                    ['typ' => 'default', 'bedingung' => null, 'keinFreigeber' => false, 'quelleTyp' => 'gruppe', 'quelle' => 'HGF', 'excludeAttribute' => []],
                ],
            ],
            // Stufe e: ab 100.000,01 €
            [
                'bezeichnung' => 'Ab 100.000,01 €',
                'vonBetrag' => 100000.01,
                'bisBetrag' => null,
                'berechtigteAttribute' => ['ist_al', 'ist_gf'],
                'berechtigteRollen' => [],
                'textBerechtigt' => 'AL',
                'textFreigeber1' => 'Vorgesetzte/r des/r Besteller/in',
                'textFreigeber2' => 'HGF',
                'freigabe1Regeln' => [
                    // AL → direkter Vorgesetzter
                    ['typ' => 'if_attribute', 'bedingung' => 'ist_al', 'keinFreigeber' => false, 'quelleTyp' => 'single', 'quelle' => 'vorgesetzter', 'excludeAttribute' => []],
                    // default: kein Freigeber
                    ['typ' => 'default', 'bedingung' => null, 'keinFreigeber' => true, 'quelleTyp' => 'single', 'quelle' => 'vorgesetzter', 'excludeAttribute' => []],
                ],
                'freigabe2Regeln' => [
                    // default: HGF-Gruppe
                    ['typ' => 'default', 'bedingung' => null, 'keinFreigeber' => false, 'quelleTyp' => 'gruppe', 'quelle' => 'HGF', 'excludeAttribute' => []],
                ],
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

        #[Description('BEN-Nummern-Abgleich mit Legacy-Intranet aktiv (temporär – deaktivieren, sobald alle Bestellungen nur noch im neuen Intranet erstellt werden)')]
        public bool $legacyBenPruefungAktiv = true,

        #[Description('D3-Notizen zu Bestellungen automatisch nach D3 synchronisieren')]
        public bool $d3NotizenSyncAktiv = true,

        #[Description('Bestellschein nach Status-Änderung auf "Bestellt" automatisch nach D3 pushen')]
        public bool $autoPushBeiBestellt = true,

        #[Description('Cache-TTL in Stunden für D3 SOAP Abruf von Benutzer-Gruppen (UI-Performance)')]
        public int $d3SoapUserGroupsCacheTtlStunden = 24,

        #[Description('Cache-TTL in Stunden für D3 SOAP Abruf aller D3-Gruppen (UI-Performance)')]
        public int $d3SoapAllGroupsCacheTtlStunden = 24,

        #[Description('Lieferantennummer des Platzhalter-Lieferanten nach Meldung „Lieferant fehlt“ (Legacy: 7000720)')]
        public string $unbekannterLieferantennummer = '7000720',

        #[Description('E-Mail-Empfänger für Meldungen „Lieferant fehlt“ (Legacy: Rechnungswesen / ticketkategorien.email)')]
        public string $fehlenderLieferantEmpfaengerEmail = 'rechnungswesen@hwk-do.de',

        #[Description('Spatie-Rolle für wählbare interne Empfänger (Mitglieder = interne Fachabteilung, z. B. IT)')]
        public string $interneBestellerGruppe = 'App-Bestellungen-InterneBesteller',
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
