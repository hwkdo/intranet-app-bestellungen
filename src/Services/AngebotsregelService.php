<?php

declare(strict_types=1);

namespace Hwkdo\IntranetAppBestellungen\Services;

use Hwkdo\IntranetAppBestellungen\Data\AngebotsRegel;
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
     * Prüft, ob die Bestellung anhand der Angebotsregeln freigabebereit ist.
     * Eine Begründung kann fehlende Angebote ersetzen, falls die Regel das erlaubt.
     */
    public function istFreigabeReady(Bestellung $bestellung): bool
    {
        $regel = $this->regelFuerBetrag((float) $bestellung->gesamtbetrag);
        if (! $regel) {
            return true;
        }

        $bestellung->loadMissing('angebote');
        $angebotsCount = $bestellung->angebote->where('typ', 'angebot')->count();
        if ($angebotsCount >= $regel->mindestAngebote) {
            return true;
        }

        if ($regel->begruendungErlaubt) {
            $hatBegruendung = $bestellung->angebote->where('typ', 'begruendung')->isNotEmpty()
                || ! empty(trim((string) $bestellung->begruendung));

            return $hatBegruendung;
        }

        return false;
    }
}
