<?php

declare(strict_types=1);

use App\Models\User;
use Hwkdo\D3RestLaravel\Enums\DocTypeEnum;
use Hwkdo\D3RestLaravel\models\Angebot as D3Angebot;
use Hwkdo\IntranetAppBestellungen\Models\Bestellung;
use Hwkdo\IntranetAppBestellungen\Services\D3\D3AbteilungResolver;
use Hwkdo\IntranetAppBestellungen\Services\D3\D3BenutzerResolver;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;

uses(RefreshDatabase::class);

it('liefert den LDAP-Displayname im Format Nachname, Vorname', function (): void {
    Cache::flush();

    $user = User::factory()->create([
        'vorname' => 'Max',
        'nachname' => 'Mustermann',
        'username' => 'mustermannm',
    ]);

    expect(app(D3BenutzerResolver::class)->wert($user))->toBe('Mustermann, Max');
});

it('verwendet nicht das name-Attribut im Format Vorname Nachname', function (): void {
    Cache::flush();

    $user = User::factory()->create([
        'vorname' => 'Erika',
        'nachname' => 'Musterfrau',
        'username' => 'musterfrau',
    ]);

    expect($user->name)->toBe('Erika Musterfrau');
    expect(app(D3BenutzerResolver::class)->wert($user))->toBe('Musterfrau, Erika');
});

it('setzt Benutzer im D3-Angebot-Dokument aus dem Resolver', function (): void {
    Cache::flush();

    $user = User::factory()->create([
        'vorname' => 'Max',
        'nachname' => 'Mustermann',
        'username' => 'mustermannm',
    ]);

    $bestellung = Bestellung::factory()->for($user)->create([
        'gruppen' => ['@Rechnungen'],
    ]);

    $benutzer = app(D3BenutzerResolver::class)->resolve($bestellung);

    $dokument = new D3Angebot([
        'betreff' => 'Angebot zur Bestellung '.$bestellung->nummer,
        'Nummer' => (int) preg_replace('/\D+/', '', $bestellung->nummer) ?: 0,
        'Erfassungsdatum' => now()->format('Y-m-d'),
        'Benutzer' => $benutzer,
        'Belegdatum' => now()->format('Y-m-d'),
        'Begründung' => 'Nein',
        'Angebotsnummer' => 'ANG-1',
        'Abteilung' => app(D3AbteilungResolver::class)->resolve($bestellung),
        'doc_type' => DocTypeEnum::Angebote,
        'filename' => 'angebot.pdf',
    ]);

    $property = collect($dokument->getFilledProperties())
        ->firstWhere('d3id', '79');

    expect($property)->not->toBeNull();
    expect($property['value'])->toContain('Mustermann, Max');
});
