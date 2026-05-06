<?php

declare(strict_types=1);

namespace Hwkdo\IntranetAppBestellungen\Data;

use Spatie\LaravelData\Data;

class FreigebeRegel extends Data
{
    /**
     * @param  string       $typ              'if_attribute' | 'if_rolle' | 'default'
     * @param  string|null  $bedingung        Attributname oder Rollenname (für if_attribute/if_rolle)
     * @param  bool         $keinFreigeber    true → leere Collection (wie legacy false)
     * @param  string       $quelleTyp        'single' | 'multi' | 'gruppe'
     * @param  string       $quelle           Methodenname ('vorgesetzter','getVorgesetzte','getAlleVorgesetzte')
     *                                        oder GVP-Kürzel ('GF','HGF') bei quelleTyp='gruppe'
     * @param  array<int, string>  $excludeAttribute  Für typ='default': User mit diesen Attributen überspringen
     */
    public function __construct(
        public string $typ = 'default',
        public ?string $bedingung = null,
        public bool $keinFreigeber = false,
        public string $quelleTyp = 'single',
        public string $quelle = 'vorgesetzter',
        /** @var array<int, string> */
        public array $excludeAttribute = [],
    ) {}
}
