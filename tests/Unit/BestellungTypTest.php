<?php

declare(strict_types=1);

use Hwkdo\IntranetAppBestellungen\Enums\BestellungTyp;

it('liefert die Bestellschein-PDF-Varianten passend zum Bestelltyp', function (): void {
    $klassisch = BestellungTyp::bestellscheinVariantenFuer(BestellungTyp::Extern);

    expect($klassisch)->toHaveCount(3)
        ->and(array_map(fn (BestellungTyp $t): string => $t->value, $klassisch))
        ->toBe(['intern', 'extern', 'extern_mit_preise'])
        ->and(BestellungTyp::bestellscheinVariantenFuer(BestellungTyp::Intern))->toHaveCount(3)
        ->and(BestellungTyp::Intern->bestellscheinLabel())->toBe('Intern')
        ->and(BestellungTyp::Extern->bestellscheinLabel())->toBe('Zum Versenden (ohne Preise)')
        ->and(BestellungTyp::ExternMitPreise->bestellscheinLabel())->toBe('Zum Versenden (mit Preise)');

    $auftrag = BestellungTyp::bestellscheinVariantenFuer(BestellungTyp::Auftragsvergabe);

    expect($auftrag)->toBe([BestellungTyp::Auftragsvergabe])
        ->and(BestellungTyp::Auftragsvergabe->label())->toBe('Auftragsvergabe mit Angebotsbezug')
        ->and(BestellungTyp::Auftragsvergabe->istAuftragsvergabe())->toBeTrue()
        ->and(BestellungTyp::Auftragsvergabe->bestellscheinLabel())->toBe('Auftrag (zum Unterschreiben)');
});
