<?php

declare(strict_types=1);

namespace Hwkdo\IntranetAppBestellungen\Data;

use Spatie\LaravelData\Data;

class AngebotsregelAuswertung extends Data
{
    public function __construct(
        public bool $pruefungAktiv = false,
        public bool $bereit = true,
        public int $mindestAngebote = 0,
        public int $anzahlVergleichsangebote = 0,
        public bool $hatAusnahmeBegruendung = false,
        public bool $ausnahmeErlaubt = true,
        public float $abBetrag = 0,
        public ?string $hinweisText = null,
    ) {}

    public function fehlendVergleichsangebote(): int
    {
        return max(0, $this->mindestAngebote - $this->anzahlVergleichsangebote);
    }

    public function zusammenfassung(): string
    {
        if (! $this->pruefungAktiv) {
            return 'Für diesen Betrag sind keine Vergleichsangebote erforderlich.';
        }

        if ($this->bereit) {
            if ($this->hatAusnahmeBegruendung) {
                return 'Ausnahme-Begründung erfasst – zur Freigabe einreichbar.';
            }

            return sprintf(
                '%d von %d Vergleichsangebot(en) erfasst – zur Freigabe einreichbar.',
                $this->anzahlVergleichsangebote,
                $this->mindestAngebote,
            );
        }

        if ($this->ausnahmeErlaubt) {
            return sprintf(
                'Es fehlen noch %d Vergleichsangebot(e) oder eine Ausnahme-Begründung (ab %s €).',
                $this->fehlendVergleichsangebote(),
                number_format($this->abBetrag, 2, ',', '.'),
            );
        }

        return sprintf(
            'Es fehlen noch %d Vergleichsangebot(e) (ab %s €).',
            $this->fehlendVergleichsangebote(),
            number_format($this->abBetrag, 2, ',', '.'),
        );
    }
}
