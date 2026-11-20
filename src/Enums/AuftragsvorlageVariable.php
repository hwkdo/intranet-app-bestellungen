<?php

declare(strict_types=1);

namespace Hwkdo\IntranetAppBestellungen\Enums;

enum AuftragsvorlageVariable: string
{
    case Ansprechpartner = 'ansprechpartner';
    case Ausfuehrungsfrist = 'ausfuehrungsfrist';
    case Freitext = 'freitext';

    public function label(): string
    {
        return match ($this) {
            self::Ansprechpartner => 'Ansprechpartner',
            self::Ausfuehrungsfrist => 'Ausführungsfrist',
            self::Freitext => 'Freitext',
        };
    }

    /**
     * Platzhalter, die der Brieftext dieser Variable verwenden kann.
     *
     * @return list<string>
     */
    public function platzhalter(): array
    {
        return match ($this) {
            self::Ansprechpartner => [],
            self::Ausfuehrungsfrist => ['frist'],
            self::Freitext => ['freitext'],
        };
    }

    public function pflichtLabel(): ?string
    {
        return match ($this) {
            self::Ausfuehrungsfrist => 'Ausführungsfrist',
            self::Freitext => 'Brieftext',
            self::Ansprechpartner => null,
        };
    }
}
