<?php

declare(strict_types=1);

namespace Hwkdo\IntranetAppBestellungen\Services;

use Hwkdo\IntranetAppBestellungen\Data\AngebotsRegel;
use Hwkdo\IntranetAppBestellungen\Data\AngebotsregelAuswertung;
use Hwkdo\IntranetAppBestellungen\Data\AppSettings;
use Hwkdo\IntranetAppBestellungen\Models\Bestellung;
use Hwkdo\IntranetAppBestellungen\Models\IntranetAppBestellungenSettings;

class AngebotsregelService
{
    public function __construct(
        private readonly ?AppSettings $settingsOverride = null,
    ) {}

    public function settings(): AppSettings
    {
        return $this->settingsOverride ?? IntranetAppBestellungenSettings::resolvedAppSettings();
    }

    /**
     * @return array<int, AngebotsRegel>
     */
    public function regelnSortiert(): array
    {
        $regeln = $this->settings()->angebotsRegelnObjekte();
        usort($regeln, static fn (AngebotsRegel $a, AngebotsRegel $b): int => $a->abBetrag <=> $b->abBetrag);

        return $regeln;
    }

    public function regelFuerBetrag(float $betrag): ?AngebotsRegel
    {
        $passend = null;
        foreach ($this->regelnSortiert() as $regel) {
            if ($betrag >= $regel->abBetrag) {
                $passend = $regel;
            }
        }

        return $passend;
    }

    public function mindestAngeboteFuer(float $betrag): int
    {
        return $this->regelFuerBetrag($betrag)?->mindestAngebote ?? 0;
    }

    /**
     * Auswertung der Angebotsregeln für UI und Freigabe-Checks (nur lokale Daten).
     */
    public function auswertung(Bestellung $bestellung): AngebotsregelAuswertung
    {
        $regel = $this->regelFuerBetrag((float) $bestellung->gesamtbetrag);
        $bestellung->loadMissing('angebote');

        $vergleichsCount = $bestellung->angebote->where('typ', 'angebot')->count();
        $hatAusnahme = $bestellung->angebote->where('typ', 'begruendung')->isNotEmpty();

        if (! $regel || $regel->mindestAngebote === 0) {
            return new AngebotsregelAuswertung(
                pruefungAktiv: false,
                bereit: true,
                anzahlVergleichsangebote: $vergleichsCount,
                hatAusnahmeBegruendung: $hatAusnahme,
            );
        }

        $bereit = $vergleichsCount >= $regel->mindestAngebote
            || ($regel->begruendungErlaubt && $hatAusnahme);

        return new AngebotsregelAuswertung(
            pruefungAktiv: true,
            bereit: $bereit,
            mindestAngebote: $regel->mindestAngebote,
            anzahlVergleichsangebote: $vergleichsCount,
            hatAusnahmeBegruendung: $hatAusnahme,
            ausnahmeErlaubt: $regel->begruendungErlaubt,
            abBetrag: $regel->abBetrag,
            hinweisText: $regel->hinweisText,
        );
    }

    /**
     * Prüft, ob die Bestellung anhand der Angebotsregeln freigabebereit ist.
     * Ausnahme-Begründung = Angebot mit typ „begruendung“ (nicht die Kopf-Begründung der Bestellung).
     */
    public function istFreigabeReady(Bestellung $bestellung): bool
    {
        return $this->auswertung($bestellung)->bereit;
    }
}
