<?php

declare(strict_types=1);

namespace Hwkdo\IntranetAppBestellungen\Enums;

enum BestellungTyp: string
{
    case Intern = 'intern';
    case Extern = 'extern';
    case ExternMitPreise = 'extern_mit_preise';

    public function isIntern(): bool
    {
        return $this === self::Intern;
    }

    public function label(): string
    {
        return match ($this) {
            self::Intern => 'Interne Bestellung',
            self::Extern => 'Externe Bestellung',
            self::ExternMitPreise => 'Externe Bestellung (mit Preisen)',
        };
    }

    public function bestellscheinLabel(): string
    {
        return match ($this) {
            self::Intern => 'Intern',
            self::Extern => 'Zum Versenden (ohne Preise)',
            self::ExternMitPreise => 'Zum Versenden (mit Preise)',
        };
    }

    /**
     * @return array<int, self>
     */
    public static function bestellscheinVarianten(): array
    {
        return [
            self::Intern,
            self::Extern,
            self::ExternMitPreise,
        ];
    }
}
