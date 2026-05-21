<?php

declare(strict_types=1);

use Hwkdo\IntranetAppBestellungen\Enums\BestellungTyp;

it('liefert alle Bestellschein-PDF-Varianten mit Labels', function (): void {
    $varianten = BestellungTyp::bestellscheinVarianten();

    expect($varianten)->toHaveCount(3)
        ->and(array_map(fn (BestellungTyp $t): string => $t->value, $varianten))
        ->toBe(['intern', 'extern', 'extern_mit_preise'])
        ->and(BestellungTyp::Intern->bestellscheinLabel())->toBe('Intern')
        ->and(BestellungTyp::Extern->bestellscheinLabel())->toBe('Zum Versenden (ohne Preise)')
        ->and(BestellungTyp::ExternMitPreise->bestellscheinLabel())->toBe('Zum Versenden (mit Preise)');
});
