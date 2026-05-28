<?php

declare(strict_types=1);

use App\Models\User;
use Hwkdo\IntranetAppBestellungen\Enums\BestellungStatus;
use Hwkdo\IntranetAppBestellungen\Jobs\ExtractAngebotMetadataJob;
use Hwkdo\IntranetAppBestellungen\Models\Angebot;
use Hwkdo\IntranetAppBestellungen\Models\Bestellung;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Laravel\Passport\Passport;

it('liefert 401 wenn kein bearer token gesendet wird', function (): void {
    $bestellung = Bestellung::factory()->create();

    $this->postJson("/api/bestellungen/{$bestellung->id}/angebote", [])
        ->assertUnauthorized();
});

it('speichert ein pdf angebot fuer eigene upload-faehige bestellung', function (): void {
    Storage::fake('local');
    Queue::fake();
    $user = User::factory()->create();
    Passport::actingAs($user);

    $bestellung = Bestellung::factory()->create([
        'user_id' => $user->id,
        'status' => BestellungStatus::Entwurf,
    ]);

    $file = UploadedFile::fake()->create('angebot.pdf', 100, 'application/pdf');

    $this->postJson("/api/bestellungen/{$bestellung->id}/angebote", [
        'file' => $file,
    ])
        ->assertCreated()
        ->assertJsonPath('data.bestellung_id', $bestellung->id)
        ->assertJsonPath('data.typ', 'angebot')
        ->assertJsonPath('data.extraction_status', 'pending');

    $angebot = Angebot::query()->where('bestellung_id', $bestellung->id)->first();
    expect($angebot)->not->toBeNull();
    Storage::disk('local')->assertExists((string) $angebot->pdf_path);
    Queue::assertPushed(ExtractAngebotMetadataJob::class);
});

it('ueberspringt extraction job bei vollstaendigen manuell gesetzten metadaten', function (): void {
    Storage::fake('local');
    Queue::fake();
    $user = User::factory()->create();
    Passport::actingAs($user);

    $bestellung = Bestellung::factory()->create([
        'user_id' => $user->id,
        'status' => BestellungStatus::Entwurf,
    ]);

    $file = UploadedFile::fake()->create('angebot.pdf', 100, 'application/pdf');

    $this->postJson("/api/bestellungen/{$bestellung->id}/angebote", [
        'file' => $file,
        'supplier_name' => 'Muster GmbH',
        'reference_number' => 'ANG-2026-42',
        'amount' => 123.45,
    ])
        ->assertCreated()
        ->assertJsonPath('data.extraction_status', 'done')
        ->assertJsonPath('data.extraction_source', 'manual');

    Queue::assertNotPushed(ExtractAngebotMetadataJob::class);
});

it('liefert 403 fuer fremde oder nicht upload-faehige bestellungen', function (): void {
    $user = User::factory()->create();
    $otherUser = User::factory()->create();
    Passport::actingAs($user);

    $fremdeBestellung = Bestellung::factory()->create([
        'user_id' => $otherUser->id,
        'status' => BestellungStatus::Entwurf,
    ]);
    $eigeneNichtUploadFaehigeBestellung = Bestellung::factory()->create([
        'user_id' => $user->id,
        'status' => BestellungStatus::ZurFreigabe,
    ]);

    $file = UploadedFile::fake()->create('angebot.pdf', 100, 'application/pdf');

    $this->postJson("/api/bestellungen/{$fremdeBestellung->id}/angebote", ['file' => $file])
        ->assertForbidden();

    $this->postJson("/api/bestellungen/{$eigeneNichtUploadFaehigeBestellung->id}/angebote", ['file' => $file])
        ->assertForbidden();
});
