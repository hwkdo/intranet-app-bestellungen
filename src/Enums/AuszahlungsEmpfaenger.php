<?php

declare(strict_types=1);

namespace Hwkdo\IntranetAppBestellungen\Enums;

enum AuszahlungsEmpfaenger: string
{
    case Mitarbeiter = 'mitarbeiter';
    case Lieferant = 'lieferant';

    public function label(): string
    {
        return match ($this) {
            self::Mitarbeiter => 'An mich',
            self::Lieferant => 'An den Lieferanten',
        };
    }
}
