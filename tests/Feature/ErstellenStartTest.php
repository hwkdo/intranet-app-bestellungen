<?php

declare(strict_types=1);

use App\Models\User;
use Hwkdo\IntranetAppBestellungen\Livewire\Apps\Bestellungen\ErstellenStart;
use Livewire\Livewire;

it('zeigt die Auswahl zwischen interner und externer Bestellung', function (): void {
    $user = User::factory()->create();

    Livewire::actingAs($user)
        ->test(ErstellenStart::class)
        ->assertSee('Interne Bestellung')
        ->assertSee('Externe Bestellung')
        ->assertSeeHtml(route('apps.bestellungen.erstellen.form', ['typ' => 'intern']))
        ->assertSeeHtml(route('apps.bestellungen.erstellen.form', ['typ' => 'extern']));
});

it('leitet bei ungültigem typ zum Auswahlbildschirm um', function (): void {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->get(route('apps.bestellungen.erstellen.form', ['typ' => 'ungueltig']))
        ->assertRedirect(route('apps.bestellungen.erstellen'));
});
