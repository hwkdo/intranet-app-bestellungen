<?php

declare(strict_types=1);

use Hwkdo\D3RestLaravel\Enums\DocTypeEnum;
use Hwkdo\D3RestLaravel\models\Bestellschein;
use Hwkdo\IntranetAppBestellungen\Models\Bestellung;
use Hwkdo\IntranetAppBestellungen\Models\Projekt;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

function bestellscheinFilledProperty(?string $d3id): ?array
{
    $dokument = new Bestellschein([
        'nummer' => 328626001,
        'lieferantenName' => 'Test GmbH',
        'lieferantenSuchfeld' => '12345',
        'kostenstelle' => 4711,
        'haushaltsjahr' => 2026,
        'benutzer' => ['Mustermann, Max'],
        'abteilung' => ['IT'],
        'doc_type' => DocTypeEnum::Bestellschein,
        'filename' => 'bestellschein.pdf',
        'projektId' => $d3id,
    ]);

    return collect($dokument->getFilledProperties())
        ->firstWhere('d3id', '397');
}

it('enthält projektId als D3-Property 397 wenn gesetzt', function (): void {
    $property = bestellscheinFilledProperty('it-ausstattung-2026');

    expect($property)->not->toBeNull();
    expect($property['value'])->toBe('it-ausstattung-2026');
});

it('lässt projektId weg wenn nicht gesetzt', function (): void {
    $property = bestellscheinFilledProperty(null);

    expect($property)->toBeNull();
});

it('verknüpft Projekt und Bestellung für den D3-Push', function (): void {
    $projekt = Projekt::factory()->create(['name' => 'Schulungsraum EDV']);
    $bestellung = Bestellung::factory()->create(['projekt_id' => $projekt->id]);

    $bestellung->load('projekt');

    expect($bestellung->projekt?->d3_projekt_id)->toBe('schulungsraum-edv');

    $property = bestellscheinFilledProperty($bestellung->projekt->d3_projekt_id);

    expect($property['value'])->toBe('schulungsraum-edv');
});
