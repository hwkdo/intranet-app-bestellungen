<?php

declare(strict_types=1);

use Hwkdo\IntranetAppBestellungen\Support\D3GruppenOptionSort;

it('sortiert ausgewählte D3-Gruppen vor die übrigen Optionen', function (): void {
    $optionen = ['@Alpha', '@Beta', '@Gamma', '@Delta'];
    $auswahl = ['@Gamma', '@Alpha'];

    $sortiert = D3GruppenOptionSort::mitAuswahlZuerst($optionen, $auswahl);

    expect($sortiert)->toBe(['@Alpha', '@Gamma', '@Beta', '@Delta']);
});

it('behält alphabetische Sortierung innerhalb der ausgewählten und nicht ausgewählten Gruppen', function (): void {
    $optionen = ['@Zeta', '@Alpha', '@Beta'];
    $auswahl = ['@Beta'];

    expect(D3GruppenOptionSort::mitAuswahlZuerst($optionen, $auswahl))->toBe(['@Beta', '@Alpha', '@Zeta']);
});
