<?php

declare(strict_types=1);

use App\Models\User;
use Hwkdo\IntranetAppBestellungen\Models\Bestellung;
use Spatie\Permission\Models\Permission;

it('lehnt ungültige Bestellschein-PDF-Varianten ab', function (): void {
    Permission::findOrCreate('see-app-bestellungen', 'web');

    $user = User::factory()->create();
    $user->givePermissionTo('see-app-bestellungen');

    $bestellung = Bestellung::factory()->create(['user_id' => $user->id]);

    $this->actingAs($user)
        ->get(route('apps.bestellungen.pdf.inline', ['bestellung' => $bestellung, 'typ' => 'ungueltig']))
        ->assertNotFound();
});
