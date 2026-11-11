<?php

declare(strict_types=1);

namespace Hwkdo\IntranetAppBestellungen\Enums;

enum BestellungTyp: string
{
    case Intern = 'intern';
    case Extern = 'extern';
    case ExternMitPreise = 'extern_mit_preise';
    case Auftragsvergabe = 'auftragsvergabe';

    public function isIntern(): bool
    {
        return $this === self::Intern;
    }

    public function istAuftragsvergabe(): bool
    {
        return $this === self::Auftragsvergabe;
    }

    public function label(): string
    {
        return match ($this) {
            self::Intern => 'Interne Bestellung',
            self::Extern => 'Externe Bestellung',
            self::ExternMitPreise => 'Externe Bestellung (mit Preisen)',
            self::Auftragsvergabe => 'Auftragsvergabe mit Angebotsbezug',
        };
    }

    public function bestellscheinLabel(): string
    {
        return match ($this) {
            self::Intern => 'Intern',
            self::Extern => 'Zum Versenden (ohne Preise)',
            self::ExternMitPreise => 'Zum Versenden (mit Preise)',
            self::Auftragsvergabe => 'Auftrag (zum Unterschreiben)',
        };
    }

    /**
     * Bestellschein-Varianten, die für den gespeicherten Bestelltyp erzeugt werden dürfen.
     *
     * @return array<int, self>
     */
    public static function bestellscheinVariantenFuer(self $bestellungTyp): array
    {
        if ($bestellungTyp->istAuftragsvergabe()) {
            return [self::Auftragsvergabe];
        }

        return [
            self::Intern,
            self::Extern,
            self::ExternMitPreise,
        ];
    }
}
