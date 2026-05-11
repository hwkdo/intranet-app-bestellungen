<?php

declare(strict_types=1);

use App\Models\User;
use App\Services\IntranetLegacyService;
use Hwkdo\IntranetAppBestellungen\Models\Bestellung;
use Hwkdo\IntranetAppBestellungen\Services\BenNumberService;

/**
 * Gibt einen BenNumberService zurück, bei dem IntranetLegacyService
 * einen konfigurierbaren Max-Sequenzwert aus dem Legacy-System zurückliefert.
 */
function makeBenService(int $legacyMax = 0): BenNumberService
{
    $mock = Mockery::mock(IntranetLegacyService::class);
    $mock->shouldReceive('getMaxSequenceFromLegacy')->andReturn($legacyMax);

    return new BenNumberService($mock);
}

it('erzeugt BEN-Nummern im Legacy-Format <Praefix><HwkdoNummer><JJ><NNN>', function (): void {
    $user = User::factory()->create([
        'username' => 'hwkdo1234',
    ]);

    $next = makeBenService()->next($user, 2026);

    expect($next)->toBe('3'.'1234'.'26'.'001');
});

it('erhöht laufende Nummer pro User und Haushaltsjahr', function (): void {
    $user = User::factory()->create([
        'username' => 'hwkdo1234',
    ]);

    Bestellung::factory()->create([
        'nummer' => '3123426007',
        'haushaltsjahr' => 2026,
        'user_id' => $user->id,
    ]);

    $next = makeBenService()->next($user, 2026);

    expect($next)->toBe('3'.'1234'.'26'.'008');
});

it('startet pro Jahr neu bei 001', function (): void {
    $user = User::factory()->create([
        'username' => 'hwkdo1234',
    ]);

    Bestellung::factory()->create([
        'nummer' => '3'.'1234'.'25'.'050',
        'haushaltsjahr' => 2025,
        'user_id' => $user->id,
    ]);

    $next = makeBenService()->next($user, 2026);

    expect($next)->toBe('3'.'1234'.'26'.'001');
});

it('zählt nur Bestellungen desselben Users', function (): void {
    $userA = User::factory()->create(['username' => 'hwkdo1111']);
    $userB = User::factory()->create(['username' => 'hwkdo2222']);

    Bestellung::factory()->count(5)->create([
        'haushaltsjahr' => 2026,
        'user_id' => $userA->id,
    ]);

    $next = makeBenService()->next($userB, 2026);

    expect($next)->toBe('3'.'2222'.'26'.'001');
});

it('fällt auf personalnr/id zurück, wenn der Username kein hwkdo-Schema hat', function (): void {
    $user = User::factory()->create([
        'username' => 'fremd-user',
        'personalnr' => '7777',
    ]);

    $next = makeBenService()->next($user, 2026);

    expect($next)->toBe('3'.'7777'.'26'.'001');
});

it('verwendet die Legacy-Sequenz wenn sie höher ist als die lokale', function (): void {
    $user = User::factory()->create([
        'username' => 'hwkdo1234',
    ]);

    // Lokal: 3 Bestellungen → lokaler Max = 3
    Bestellung::factory()->count(3)->sequence(
        ['nummer' => '3123426001', 'haushaltsjahr' => 2026],
        ['nummer' => '3123426002', 'haushaltsjahr' => 2026],
        ['nummer' => '3123426003', 'haushaltsjahr' => 2026],
    )->create(['user_id' => $user->id]);

    // Legacy-Seite hat bereits Sequenz 10 → nächste muss 11 sein
    $next = makeBenService(legacyMax: 10)->next($user, 2026);

    expect($next)->toBe('3'.'1234'.'26'.'011');
});

it('verwendet die lokale Sequenz wenn sie höher ist als die Legacy-Sequenz', function (): void {
    $user = User::factory()->create([
        'username' => 'hwkdo1234',
    ]);

    // Lokal: Max = 8
    Bestellung::factory()->create([
        'nummer' => '3123426008',
        'haushaltsjahr' => 2026,
        'user_id' => $user->id,
    ]);

    // Legacy gibt nur 3 zurück → lokaler Wert gewinnt
    $next = makeBenService(legacyMax: 3)->next($user, 2026);

    expect($next)->toBe('3'.'1234'.'26'.'009');
});

it('fällt auf lokale Sequenz zurück wenn Legacy nicht erreichbar ist (legacyMax=0)', function (): void {
    $user = User::factory()->create([
        'username' => 'hwkdo1234',
    ]);

    Bestellung::factory()->create([
        'nummer' => '3123426005',
        'haushaltsjahr' => 2026,
        'user_id' => $user->id,
    ]);

    // Legacy gibt 0 zurück (Fehlerfall / nicht erreichbar)
    $next = makeBenService(legacyMax: 0)->next($user, 2026);

    expect($next)->toBe('3'.'1234'.'26'.'006');
});
