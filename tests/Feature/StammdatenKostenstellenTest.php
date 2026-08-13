<?php

declare(strict_types=1);

use App\Models\User;
use Hwkdo\IntranetAppBestellungen\Livewire\Apps\Bestellungen\Admin\Stammdaten;
use Hwkdo\IntranetAppBestellungen\Models\KostenstelleCache;
use Livewire\Livewire;

function createStammdatenKostenstelle(string $nummer, bool $aktiv = true): KostenstelleCache
{
    return KostenstelleCache::query()->create([
        'kostenstelle' => $nummer,
        'bezeichnung' => 'Bezeichnung '.$nummer,
        'aktiv' => $aktiv,
        'synced_at' => now(),
    ]);
}

it('filtert Kostenstellen nach aktiv und inaktiv', function (): void {
    $user = User::factory()->create();
    createStammdatenKostenstelle('1010', aktiv: true);
    createStammdatenKostenstelle('1336', aktiv: false);

    Livewire::actingAs($user)
        ->test(Stammdaten::class)
        ->set('aktiveTab', 'kostenstellen')
        ->assertSee('1010')
        ->assertSee('1336')
        ->set('kostenstellenStatus', 'aktiv')
        ->assertSee('1010')
        ->assertDontSee('1336')
        ->set('kostenstellenStatus', 'inaktiv')
        ->assertDontSee('1010')
        ->assertSee('1336');
});

it('zeigt Gesamt- und Filteranzahl der Kostenstellen', function (): void {
    $user = User::factory()->create();
    createStammdatenKostenstelle('1010', aktiv: true);
    createStammdatenKostenstelle('1336', aktiv: false);

    Livewire::actingAs($user)
        ->test(Stammdaten::class)
        ->set('aktiveTab', 'kostenstellen')
        ->assertSee('1–2 von 2 insgesamt')
        ->set('kostenstellenStatus', 'inaktiv')
        ->assertSee('1–1 von 1 gefiltert')
        ->assertSee('2 insgesamt');
});

it('paginiert Kostenstellen und setzt die Seite bei Filterwechsel zurück', function (): void {
    $user = User::factory()->create();

    foreach (range(1, 26) as $i) {
        createStammdatenKostenstelle(sprintf('K%03d', $i), aktiv: true);
    }

    Livewire::actingAs($user)
        ->test(Stammdaten::class)
        ->set('aktiveTab', 'kostenstellen')
        ->assertSee('K001')
        ->assertDontSee('K026')
        ->call('gotoPage', 2, 'kostenstellenPage')
        ->assertSee('K026')
        ->assertDontSee('K001')
        ->set('search', 'K001')
        ->assertSee('K001')
        ->assertDontSee('K026')
        ->assertSee('1–1 von 1 gefiltert')
        ->assertSee('26 insgesamt');
});
