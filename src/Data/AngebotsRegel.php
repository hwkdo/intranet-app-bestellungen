<?php

declare(strict_types=1);

namespace Hwkdo\IntranetAppBestellungen\Data;

use Spatie\LaravelData\Data;

class AngebotsRegel extends Data
{
    public function __construct(
        public float $abBetrag = 0,
        public int $mindestAngebote = 0,
        public bool $begruendungErlaubt = true,
        public ?string $hinweisText = null,
    ) {}
}
