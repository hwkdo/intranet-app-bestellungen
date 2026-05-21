<?php

declare(strict_types=1);

use App\Models\User;
use Hwkdo\IntranetAppBestellungen\Enums\BestellungStatus;
use Hwkdo\IntranetAppBestellungen\Livewire\Apps\Bestellungen\InterneBestellungen;
use Hwkdo\IntranetAppBestellungen\Models\Bestellung;
use Hwkdo\IntranetAppBestellungen\Support\PlatzhalterLieferant;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;

beforeEach(function (): void {
    Role::findOrCreate('Benutzer', 'web');
    Role::findOrCreate('App-Bestellungen-InterneBesteller', 'web');
});

it('zeigt interne Bestellungen des eingeloggten Empfängers', function (): void {
    $empfaenger = User::factory()->create();
    $empfaenger->assignRole(['Benutzer', 'App-Bestellungen-InterneBesteller']);

    $andererEmpfaenger = User::factory()->create();
    $andererEmpfaenger->assignRole('App-Bestellungen-InterneBesteller');

    $offen = Bestellung::factory()->intern()->create([
        'status' => BestellungStatus::Freigegeben,
        'interner_empfaenger_user_id' => $empfaenger->id,
        'lieferantennummer' => PlatzhalterLieferant::nummer(),
        'nummer' => '3000000001',
    ]);

    Bestellung::factory()->intern()->create([
        'status' => BestellungStatus::Freigegeben,
        'interner_empfaenger_user_id' => $andererEmpfaenger->id,
        'nummer' => '3000000002',
    ]);

    Livewire::actingAs($empfaenger)
        ->test(InterneBestellungen::class)
        ->assertSee('3000000001')
        ->assertDontSee('3000000002');
});

it('verweigert den Zugriff ohne Rolle Interne Besteller', function (): void {
    $user = User::factory()->create();
    $user->assignRole('Benutzer');

    Livewire::actingAs($user)
        ->test(InterneBestellungen::class)
        ->assertForbidden();
});
