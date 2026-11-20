<?php

declare(strict_types=1);

namespace Hwkdo\IntranetAppBestellungen\Enums;

enum UnterschriftArt: string
{
    case Digital = 'digital';
    case Scan = 'scan';

    public function label(): string
    {
        return match ($this) {
            self::Digital => 'Digital',
            self::Scan => 'Scan',
        };
    }
}
