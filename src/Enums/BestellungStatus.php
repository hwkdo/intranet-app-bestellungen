<?php

declare(strict_types=1);

namespace Hwkdo\IntranetAppBestellungen\Enums;

enum BestellungStatus: string
{
    case Entwurf = 'Entwurf';
    case ZurFreigabe = 'Zur Freigabe';
    case ZurZweitenFreigabe = 'Zur 2. Freigabe';
    case Freigegeben = 'Freigegeben';
    case Abgelehnt = 'Abgelehnt';
    case Bestellt = 'Bestellt';

    public function label(): string
    {
        return $this->value;
    }

    public function color(): string
    {
        return match ($this) {
            self::Entwurf => 'zinc',
            self::ZurFreigabe, self::ZurZweitenFreigabe => 'amber',
            self::Freigegeben => 'sky',
            self::Bestellt => 'emerald',
            self::Abgelehnt => 'red',
        };
    }

    public function isFreigabePending(): bool
    {
        return in_array($this, [self::ZurFreigabe, self::ZurZweitenFreigabe], true);
    }

    public function isFinal(): bool
    {
        return in_array($this, [self::Bestellt, self::Abgelehnt], true);
    }

    /**
     * @return array<string, string>
     */
    public static function options(): array
    {
        return collect(self::cases())
            ->mapWithKeys(fn (self $case): array => [$case->value => $case->label()])
            ->all();
    }
}
