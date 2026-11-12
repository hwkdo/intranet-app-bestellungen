<?php

declare(strict_types=1);

namespace Hwkdo\IntranetAppBestellungen\Enums;

enum Auszahlungsart: string
{
    case Ueberweisung = 'ueberweisung';
    case Barauszahlung = 'bar';

    public function belegtypZb(): string
    {
        return match ($this) {
            self::Ueberweisung => 'Rechnung',
            self::Barauszahlung => 'Barauszahlung',
        };
    }

    public function label(): string
    {
        return match ($this) {
            self::Ueberweisung => 'Überweisung',
            self::Barauszahlung => 'Barauszahlung',
        };
    }
}
