<?php

declare(strict_types=1);

use App\Models\User;
use Hwkdo\IntranetAppBestellungen\Enums\BestellungStatus;
use Hwkdo\IntranetAppBestellungen\Models\Bestellung;
use Laravel\Passport\Passport;

it('liefert 401 wenn kein bearer token gesendet wird', function (): void {
    $this->getJson('/api/bestellungen/meine-offenen')
        ->assertUnauthorized();
});

it('liefert nur eigene upload-faehige bestellungen', function (): void {
    $user = User::factory()->create();
    $otherUser = User::factory()->create();
    Passport::actingAs($user);

    $entwurf = Bestellung::factory()->create([
        'user_id' => $user->id,
        'status' => BestellungStatus::Entwurf,
    ]);
    $abgelehnt = Bestellung::factory()->create([
        'user_id' => $user->id,
        'status' => BestellungStatus::Abgelehnt,
    ]);
    Bestellung::factory()->create([
        'user_id' => $user->id,
        'status' => BestellungStatus::ZurFreigabe,
    ]);
    Bestellung::factory()->create([
        'user_id' => $otherUser->id,
        'status' => BestellungStatus::Entwurf,
    ]);

    $response = $this->getJson('/api/bestellungen/meine-offenen')
        ->assertOk()
        ->assertJsonCount(2, 'data');

    $ids = collect($response->json('data'))->pluck('id')->all();
    expect($ids)->toContain($entwurf->id, $abgelehnt->id);
});
