<?php

declare(strict_types=1);

namespace Hwkdo\IntranetAppBestellungen\Enums;

enum AuftragModus: string
{
    case Tabelle = 'tabelle';
    case Vorlage = 'vorlage';

    public function label(): string
    {
        return match ($this) {
            self::Tabelle => 'Freitext je Zeile',
            self::Vorlage => 'Aus Vorlage generieren',
        };
    }
}
