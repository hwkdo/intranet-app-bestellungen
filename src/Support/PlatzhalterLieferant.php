<?php

declare(strict_types=1);

namespace Hwkdo\IntranetAppBestellungen\Support;

use Hwkdo\IntranetAppBestellungen\Models\IntranetAppBestellungenSettings;

final class PlatzhalterLieferant
{
    public static function nummer(): string
    {
        return IntranetAppBestellungenSettings::resolvedAppSettings()->unbekannterLieferantennummer;
    }

    public static function istPlatzhalter(?string $lieferantennummer): bool
    {
        return filled($lieferantennummer)
            && $lieferantennummer === self::nummer();
    }
}
