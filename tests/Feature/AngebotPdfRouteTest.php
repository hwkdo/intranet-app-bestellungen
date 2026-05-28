<?php

declare(strict_types=1);

use App\Models\User;
use Hwkdo\IntranetAppBestellungen\Models\Angebot;
use Hwkdo\IntranetAppBestellungen\Models\Bestellung;
use Illuminate\Support\Facades\Storage;
use Spatie\Permission\Models\Permission;

it('zeigt ein hinterlegtes Angebots-PDF inline an', function (): void {
    Permission::findOrCreate('see-app-bestellungen', 'web');

    $user = User::factory()->create();
    $user->givePermissionTo('see-app-bestellungen');

    $bestellung = Bestellung::factory()->create(['user_id' => $user->id]);

    Storage::fake('local');
    $relPath = 'bestellungen/angebote/'.$bestellung->getKey().'/angebot.pdf';
    Storage::disk('local')->put($relPath, '%PDF-1.4 test');

    $angebot = Angebot::create([
        'bestellung_id' => $bestellung->getKey(),
        'user_id' => $user->id,
        'typ' => 'angebot',
        'pdf_path' => $relPath,
    ]);

    $this->actingAs($user)
        ->get(route('apps.bestellungen.angebot.pdf.inline', [
            'bestellung' => $bestellung,
            'angebot' => $angebot,
        ]))
        ->assertOk()
        ->assertHeader('content-type', 'application/pdf');
});

it('zeigt eine Ausnahme-Begründung als PDF inline an wenn pdf_path gesetzt ist', function (): void {
    Permission::findOrCreate('see-app-bestellungen', 'web');

    $user = User::factory()->create();
    $user->givePermissionTo('see-app-bestellungen');

    $bestellung = Bestellung::factory()->create(['user_id' => $user->id]);

    Storage::fake('local');
    $relPath = 'bestellungen/angebote/'.$bestellung->getKey().'/begruendung.pdf';
    Storage::disk('local')->put($relPath, '%PDF-1.4 begruendung');

    $angebot = Angebot::create([
        'bestellung_id' => $bestellung->getKey(),
        'user_id' => $user->id,
        'typ' => 'begruendung',
        'begruendung' => 'Ausnahme wegen Marktmonopol beim Lieferanten.',
        'pdf_path' => $relPath,
    ]);

    $this->actingAs($user)
        ->get(route('apps.bestellungen.angebot.pdf.inline', [
            'bestellung' => $bestellung,
            'angebot' => $angebot,
        ]))
        ->assertOk()
        ->assertHeader('content-type', 'application/pdf');
});

it('lehnt Angebote ohne PDF ab', function (): void {
    Permission::findOrCreate('see-app-bestellungen', 'web');

    $user = User::factory()->create();
    $user->givePermissionTo('see-app-bestellungen');

    $bestellung = Bestellung::factory()->create(['user_id' => $user->id]);

    $angebot = Angebot::create([
        'bestellung_id' => $bestellung->getKey(),
        'user_id' => $user->id,
        'typ' => 'angebot',
        'pdf_path' => null,
    ]);

    $this->actingAs($user)
        ->get(route('apps.bestellungen.angebot.pdf.inline', [
            'bestellung' => $bestellung,
            'angebot' => $angebot,
        ]))
        ->assertNotFound();
});

it('findet Begründungs-PDFs mit doppelter pdf-Endung aus Gotenberg', function (): void {
    Permission::findOrCreate('see-app-bestellungen', 'web');

    $user = User::factory()->create();
    $user->givePermissionTo('see-app-bestellungen');

    $bestellung = Bestellung::factory()->create(['user_id' => $user->id]);

    Storage::fake('local');
    $dbPath = 'bestellungen/angebote/'.$bestellung->getKey().'/begruendung-1.pdf';
    $actualPath = $dbPath.'.pdf';
    Storage::disk('local')->put($actualPath, '%PDF-1.4 begruendung');

    $angebot = Angebot::create([
        'bestellung_id' => $bestellung->getKey(),
        'user_id' => $user->id,
        'typ' => 'begruendung',
        'begruendung' => 'Ausnahme wegen Marktmonopol beim Lieferanten.',
        'pdf_path' => $dbPath,
    ]);

    $this->actingAs($user)
        ->get(route('apps.bestellungen.angebot.pdf.inline', [
            'bestellung' => $bestellung,
            'angebot' => $angebot,
        ]))
        ->assertOk();

    expect($angebot->fresh()->pdf_path)->toBe($actualPath);
});

it('lehnt Angebote ab die nicht zur Bestellung gehören', function (): void {
    Permission::findOrCreate('see-app-bestellungen', 'web');

    $user = User::factory()->create();
    $user->givePermissionTo('see-app-bestellungen');

    $bestellung = Bestellung::factory()->create(['user_id' => $user->id]);
    $andereBestellung = Bestellung::factory()->create(['user_id' => $user->id]);

    Storage::fake('local');
    $relPath = 'bestellungen/angebote/'.$andereBestellung->getKey().'/angebot.pdf';
    Storage::disk('local')->put($relPath, '%PDF-1.4 test');

    $angebot = Angebot::create([
        'bestellung_id' => $andereBestellung->getKey(),
        'user_id' => $user->id,
        'typ' => 'angebot',
        'pdf_path' => $relPath,
    ]);

    $this->actingAs($user)
        ->get(route('apps.bestellungen.angebot.pdf.inline', [
            'bestellung' => $bestellung,
            'angebot' => $angebot,
        ]))
        ->assertNotFound();
});
