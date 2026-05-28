<?php

declare(strict_types=1);

use Hwkdo\D3RestLaravel\Enums\DocTypeEnum;
use Hwkdo\D3RestLaravel\models\Angebot as D3Angebot;
use Hwkdo\D3RestLaravel\Facades\D3RestLaravel;
use App\Models\User;
use Hwkdo\IntranetAppBestellungen\Models\Bestellung;
use Hwkdo\IntranetAppBestellungen\Services\D3\D3AbteilungResolver;
use Hwkdo\IntranetAppBestellungen\Services\D3\D3BenutzerResolver;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;

uses(RefreshDatabase::class);

it('liefert die D3-Gruppen der Bestellung für das Pflichtfeld Abteilung', function (): void {
    $bestellung = Bestellung::factory()->create([
        'gruppen' => ['@Rechnungen', '@D3EDV', '@GL_EDV_Schulung'],
    ]);

    $abteilung = app(D3AbteilungResolver::class)->resolve($bestellung);

    expect($abteilung)->toBe(['@Rechnungen', '@D3EDV', '@GL_EDV_Schulung']);
});

it('setzt Abteilung im D3-Angebot-Dokument aus den Bestellungsgruppen', function (): void {
    Cache::flush();

    $user = User::factory()->create([
        'vorname' => 'Max',
        'nachname' => 'Mustermann',
        'username' => 'mustermannm',
    ]);

    $bestellung = Bestellung::factory()->for($user)->create([
        'gruppen' => ['@Rechnungen', '@D3EDV'],
    ]);

    $dokument = new D3Angebot([
        'betreff' => 'Angebot zur Bestellung '.$bestellung->nummer,
        'Nummer' => (int) preg_replace('/\D+/', '', $bestellung->nummer) ?: 0,
        'Erfassungsdatum' => now()->format('Y-m-d'),
        'Benutzer' => app(D3BenutzerResolver::class)->resolve($bestellung),
        'Belegdatum' => now()->format('Y-m-d'),
        'Begründung' => 'Nein',
        'Angebotsnummer' => 'ANG-1',
        'Abteilung' => app(D3AbteilungResolver::class)->resolve($bestellung),
        'doc_type' => DocTypeEnum::Angebote,
        'filename' => 'angebot.pdf',
    ]);

    $property = collect($dokument->getFilledProperties())
        ->firstWhere('d3id', '80');

    expect($property)->not->toBeNull();
    expect($property['value'])->toBe(['@Rechnungen', '@D3EDV']);
});

it('fragt D3-SOAP-Gruppen ab wenn die Bestellung keine gruppen hat', function (): void {
    $bestellung = Bestellung::factory()->create(['gruppen' => null]);

    D3RestLaravel::shouldReceive('getUserInGroupsSoapCached')
        ->once()
        ->andReturn(['@SOAP-Gruppe']);

    $abteilung = app(D3AbteilungResolver::class)->resolve($bestellung);

    expect($abteilung)->toBe(['@SOAP-Gruppe']);
});

it('liefert ein leeres Array wenn weder gruppen noch SOAP noch User-Abteilung verfügbar sind', function (): void {
    $bestellung = Bestellung::factory()->create(['gruppen' => []]);

    D3RestLaravel::shouldReceive('getUserInGroupsSoapCached')
        ->once()
        ->andReturn([]);

    $abteilung = app(D3AbteilungResolver::class)->resolve($bestellung);

    expect($abteilung)->toBe([]);
});
