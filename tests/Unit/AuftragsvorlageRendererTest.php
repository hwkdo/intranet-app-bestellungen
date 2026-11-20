<?php

declare(strict_types=1);

use Hwkdo\IntranetAppBestellungen\Enums\AuftragsvorlageVariable;
use Hwkdo\IntranetAppBestellungen\Models\Auftragsvorlage;
use Hwkdo\IntranetAppBestellungen\Services\Pdf\AuftragsvorlageRenderer;

it('ersetzt Platzhalter, erlaubt Fettdruck und entfernt rohes HTML', function (): void {
    $vorlage = new Auftragsvorlage([
        'inhalt' => "Auftrag zu **{betreff}**.\n\n{freitext}\n\n<script>alert(1)</script>",
        'variablen' => [AuftragsvorlageVariable::Freitext->value],
    ]);

    $html = app(AuftragsvorlageRenderer::class)->html($vorlage, [
        'betreff' => 'Dach <b>Sanierung</b>',
        'freitext' => "Sehr geehrte Damen und Herren,\n\n**hier** mein Text.",
    ]);

    expect($html)
        ->toContain('<strong>Dach')
        ->toContain('&lt;b&gt;Sanierung&lt;/b&gt;')
        ->toContain('<strong>hier</strong>')
        ->not->toContain('<script>')
        ->not->toContain('<b>Sanierung</b>');
});

it('meldet unbekannte Platzhalter und fehlende Variablen-Platzhalter', function (): void {
    $vorlage = new Auftragsvorlage([
        'inhalt' => 'Hallo {unbekannt} {betreff}',
        'variablen' => [AuftragsvorlageVariable::Freitext->value],
    ]);

    expect($vorlage->unbekanntePlatzhalter())->toBe(['unbekannt'])
        ->and($vorlage->variablenOhnePlatzhalter())->toBe(['Freitext']);
});
