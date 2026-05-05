<?php

declare(strict_types=1);

use App\Models\User;
use Hwkdo\IntranetAppBestellungen\Models\Bestellung;
use Hwkdo\IntranetAppBestellungen\Services\BenNumberService;

it('erzeugt BEN-Nummern im Legacy-Format <Praefix><HwkdoNummer><JJ><NNN>', function (): void {
    $user = User::factory()->create([
        'username' => 'hwkdo1234',
    ]);

    $next = app(BenNumberService::class)->next($user, 2026);

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

    $next = app(BenNumberService::class)->next($user, 2026);

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

    $next = app(BenNumberService::class)->next($user, 2026);

    expect($next)->toBe('3'.'1234'.'26'.'001');
});

it('zählt nur Bestellungen desselben Users', function (): void {
    $userA = User::factory()->create(['username' => 'hwkdo1111']);
    $userB = User::factory()->create(['username' => 'hwkdo2222']);

    Bestellung::factory()->count(5)->create([
        'haushaltsjahr' => 2026,
        'user_id' => $userA->id,
    ]);

    $next = app(BenNumberService::class)->next($userB, 2026);

    expect($next)->toBe('3'.'2222'.'26'.'001');
});

it('fällt auf personalnr/id zurück, wenn der Username kein hwkdo-Schema hat', function (): void {
    $user = User::factory()->create([
        'username' => 'fremd-user',
        'personalnr' => '7777',
    ]);

    $next = app(BenNumberService::class)->next($user, 2026);

    expect($next)->toBe('3'.'7777'.'26'.'001');
});
