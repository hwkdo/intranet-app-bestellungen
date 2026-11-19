<?php

declare(strict_types=1);

use App\Models\User;
use Hwkdo\IntranetAppBestellungen\Enums\AktionTyp;
use Hwkdo\IntranetAppBestellungen\Enums\BestellungStatus;
use Hwkdo\IntranetAppBestellungen\Livewire\Apps\Bestellungen\Detail;
use Hwkdo\IntranetAppBestellungen\Models\Bestellung;
use Hwkdo\IntranetAppBestellungen\Models\Kassenbon;
use Livewire\Livewire;
use Spatie\Permission\Models\Permission;

it('löscht einen eigenen Entwurf und löst den Kassenbon', function (): void {
    $user = User::factory()->create();
    $bestellung = Bestellung::factory()->create([
        'user_id' => $user->id,
        'status' => BestellungStatus::Entwurf,
        'nummer' => '3000000001',
    ]);
    $kassenbon = Kassenbon::query()->create([
        'user_id' => $user->id,
        'bestellung_id' => $bestellung->id,
        'datei_pfad' => 'bestellungen/kassenbons/1/beleg.pdf',
    ]);

    Livewire::actingAs($user)
        ->test(Detail::class, ['bestellung' => $bestellung])
        ->assertSee('wire:click="loeschen"', false)
        ->call('loeschen')
        ->assertRedirect(route('apps.bestellungen.meine'));

    expect(Bestellung::query()->find($bestellung->id))->toBeNull()
        ->and(Bestellung::withTrashed()->find($bestellung->id)?->trashed())->toBeTrue()
        ->and($kassenbon->fresh()?->bestellung_id)->toBeNull()
        ->and($bestellung->aktionen()->where('typ', AktionTyp::Geloescht)->exists())->toBeTrue();
});

it('lässt fremde und weitergeschaltete Bestellungen stehen', function (BestellungStatus $status, bool $fremd): void {
    Permission::findOrCreate('manage-app-bestellungen', 'web');

    $owner = User::factory()->create();
    $actor = $fremd ? User::factory()->create() : $owner;
    if ($fremd) {
        $actor->givePermissionTo('manage-app-bestellungen');
    }

    $bestellung = Bestellung::factory()->create([
        'user_id' => $owner->id,
        'status' => $status,
    ]);

    Livewire::actingAs($actor)
        ->test(Detail::class, ['bestellung' => $bestellung])
        ->assertDontSee('wire:click="loeschen"', false)
        ->call('loeschen')
        ->assertNoRedirect();

    expect(Bestellung::query()->find($bestellung->id))->not->toBeNull()
        ->and($bestellung->fresh()?->trashed())->toBeFalse();
})->with([
    'fremder Entwurf' => [BestellungStatus::Entwurf, true],
    'eigene Freigabe' => [BestellungStatus::ZurFreigabe, false],
    'eigene Ablehnung' => [BestellungStatus::Abgelehnt, false],
    'eigene Bestellung' => [BestellungStatus::Bestellt, false],
]);
