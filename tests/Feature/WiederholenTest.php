<?php

declare(strict_types=1);

use App\Models\User;
use Hwkdo\IntranetAppBestellungen\Enums\BestellungStatus;
use Hwkdo\IntranetAppBestellungen\Livewire\Apps\Bestellungen\Detail;
use Hwkdo\IntranetAppBestellungen\Models\Bestellung;
use Hwkdo\IntranetAppBestellungen\Models\Position;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;

it('dupliziert eine Bestellung mit neuer BEN-Nummer und frischem Status', function (): void {
    Storage::fake('public');

    $user = User::factory()->create();
    $bestellung = Bestellung::factory()->bestellt()->create([
        'user_id' => $user->id,
        'lieferantenname' => 'Original GmbH',
    ]);
    $position = Position::factory()->for($bestellung)->create();
    $position->addMedia(UploadedFile::fake()->create('anlage.pdf', 64, 'application/pdf')->getRealPath())
        ->usingFileName('anlage.pdf')
        ->toMediaCollection('position_pdf');

    Livewire::actingAs($user)
        ->test(Detail::class, ['bestellung' => $bestellung])
        ->call('wiederholen')
        ->assertRedirect();

    $kopien = Bestellung::query()->where('wiederholt_von_id', $bestellung->id)->get();

    expect($kopien)->toHaveCount(1)
        ->and($kopien->first()->status)->toBe(BestellungStatus::Entwurf)
        ->and($kopien->first()->nummer)->not->toBe($bestellung->nummer)
        ->and($kopien->first()->positionen)->toHaveCount(1)
        ->and($kopien->first()->positionen->first()?->hasPositionPdf())->toBeTrue();
});
