<?php

declare(strict_types=1);

namespace Hwkdo\IntranetAppBestellungen\Data;

use Spatie\LaravelData\Data;

class FreigabeStufe extends Data
{
    public function __construct(
        public string $bezeichnung = 'Standard',
        public ?float $bisBetrag = null,
        /** @var array<int, int> User-IDs */
        public array $freigeberUserIds = [],
        /** @var array<int, string> Spatie-Rollennamen */
        public array $freigeberRollen = [],
        public bool $zweiteFreigabeErforderlich = false,
        public ?float $zweiteFreigabeAb = null,
    ) {}
}
