<?php

declare(strict_types=1);

use Hwkdo\IntranetAppBestellungen\Models\Projekt;
use Hwkdo\IntranetAppBestellungen\Services\Projekt\ProjektIdGenerator;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;

uses(RefreshDatabase::class);

it('erzeugt eine slug-basierte Projekt-ID aus dem Titel', function (): void {
    $generator = new ProjektIdGenerator;

    expect($generator->generate('Sommerfest 2026'))->toBe('sommerfest-2026');
});

it('begrenzt die Projekt-ID auf 35 Zeichen', function (): void {
    $generator = new ProjektIdGenerator;

    $id = $generator->generate(str_repeat('Wort ', 20));

    expect(strlen($id))->toBeLessThanOrEqual(35);
});

it('vergibt bei Kollision einen numerischen Suffix', function (): void {
    Projekt::factory()->create([
        'name' => 'Sommerfest 2026',
        'd3_projekt_id' => 'sommerfest-2026',
    ]);

    $generator = new ProjektIdGenerator;

    expect($generator->generate('Sommerfest 2026'))->toBe('sommerfest-2026-2');
});

it('nutzt einen Fallback wenn der Titel keinen Slug ergibt', function (): void {
    $generator = new ProjektIdGenerator;

    expect($generator->generate('!!!'))->toBe('projekt');
});

it('setzt beim Anlegen eines Projekts automatisch d3_projekt_id', function (): void {
    $projekt = Projekt::factory()->create(['name' => 'IT-Ausstattung 2026']);

    expect($projekt->d3_projekt_id)->toBe('it-ausstattung-2026');
    expect(Str::length($projekt->d3_projekt_id))->toBeLessThanOrEqual(35);
});
