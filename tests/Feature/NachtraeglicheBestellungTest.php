<?php

declare(strict_types=1);

use App\Models\User;
use App\Services\IntranetLegacyService;
use App\Services\PdfService;
use Hwkdo\D3RestLaravel\DTO\NewObjectDTO;
use Hwkdo\D3RestLaravel\DTO\TempUploadDTO;
use Hwkdo\D3RestLaravel\Facades\D3RestLaravel;
use Hwkdo\IntranetAppBestellungen\Data\AppSettings;
use Hwkdo\IntranetAppBestellungen\Data\NachtraeglicheBestellungEingabe;
use Hwkdo\IntranetAppBestellungen\Enums\BestellungStatus;
use Hwkdo\IntranetAppBestellungen\Enums\BestellungTyp;
use Hwkdo\IntranetAppBestellungen\Exceptions\WorkflowException;
use Hwkdo\IntranetAppBestellungen\Jobs\ExtractKassenbonJob;
use Hwkdo\IntranetAppBestellungen\Livewire\Apps\Bestellungen\ErstellenNachtraeglich;
use Hwkdo\IntranetAppBestellungen\Livewire\Apps\Bestellungen\ErstellenStart;
use Hwkdo\IntranetAppBestellungen\Models\Bestellung;
use Hwkdo\IntranetAppBestellungen\Models\IntranetAppBestellungenSettings;
use Hwkdo\IntranetAppBestellungen\Models\Kassenbon;
use Hwkdo\IntranetAppBestellungen\Models\KostenstelleCache;
use Hwkdo\IntranetAppBestellungen\Models\LieferantCache;
use Hwkdo\IntranetAppBestellungen\Services\D3\D3BenutzerResolver;
use Hwkdo\IntranetAppBestellungen\Services\Kassenbon\KassenbonExtractionService;
use Hwkdo\IntranetAppBestellungen\Services\Lieferant\LieferantErkennungService;
use Hwkdo\IntranetAppBestellungen\Services\NachtraeglicheBestellungService;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

beforeEach(function (): void {
    $legacyMock = Mockery::mock(IntranetLegacyService::class);
    $legacyMock->shouldReceive('getMaxSequenceFromLegacy')->andReturn(0);
    app()->instance(IntranetLegacyService::class, $legacyMock);

    Role::findOrCreate('Benutzer', 'web');

    IntranetAppBestellungenSettings::create([
        'version' => 1,
        'settings' => AppSettings::from([
            'freigabeStufen' => [
                [
                    'bezeichnung' => 'Standard',
                    'bisBetrag' => null,
                    'berechtigteRollen' => ['Benutzer'],
                    'freigabe1Regeln' => [
                        [
                            'typ' => 'default',
                            'keinFreigeber' => true,
                            'quelleTyp' => 'single',
                            'quelle' => 'vorgesetzter',
                            'excludeAttribute' => [],
                        ],
                    ],
                ],
            ],
            'angebotsRegeln' => [
                ['abBetrag' => 0, 'mindestAngebote' => 0, 'begruendungErlaubt' => true],
            ],
        ])->toArray(),
    ]);
});

it('zeigt vier Karten und öffnet die Kassenbon-Seite', function (): void {
    Permission::findOrCreate('see-app-bestellungen', 'web');

    $user = User::factory()->create(['username' => 'hwkdo42']);
    $user->givePermissionTo('see-app-bestellungen');

    LieferantCache::query()->create([
        'lieferantennummer' => '1',
        'lieferantenname' => 'Unbeteiligt GmbH',
    ]);
    KostenstelleCache::query()->create([
        'kostenstelle' => '1000',
        'bezeichnung' => 'Test',
    ]);

    D3RestLaravel::shouldReceive('getUserInGroupsSoapCached')->andReturn([]);
    D3RestLaravel::shouldReceive('getD3GroupsSoapCached')->andReturn([]);

    Livewire::actingAs($user)
        ->test(ErstellenStart::class)
        ->assertSee('Interne Bestellung')
        ->assertSee('Externe Bestellung')
        ->assertSee('Auftragsvergabe mit Angebotsbezug')
        ->assertSee('Nachträgliche Bestellung')
        ->assertSee('Kassenbon hochladen');

    $this->actingAs($user)
        ->get(route('apps.bestellungen.erstellen.nachtraeglich'))
        ->assertSuccessful()
        ->assertSee('Kassenbon');
});

it('startet die Auswertung, sobald der Kassenbon hochgeladen ist', function (): void {
    Queue::fake();
    Storage::fake('local');

    LieferantCache::query()->create([
        'lieferantennummer' => '1',
        'lieferantenname' => 'Unbeteiligt GmbH',
    ]);
    KostenstelleCache::query()->create([
        'kostenstelle' => '1000',
        'bezeichnung' => 'Test',
    ]);
    D3RestLaravel::shouldReceive('getUserInGroupsSoapCached')->andReturn([]);
    D3RestLaravel::shouldReceive('getD3GroupsSoapCached')->andReturn([]);

    $user = User::factory()->create(['username' => 'hwkdo42']);

    Livewire::actingAs($user)
        ->test(ErstellenNachtraeglich::class)
        ->set('kassenbonDatei', UploadedFile::fake()->create('bon.pdf', 100, 'application/pdf'))
        ->assertRedirect();

    $kassenbon = Kassenbon::query()->where('user_id', $user->id)->first();

    expect($kassenbon)->not->toBeNull()
        ->and($kassenbon->extraction_status)->toBe('pending')
        ->and(Storage::disk('local')->exists($kassenbon->datei_pfad))->toBeTrue();

    Queue::assertPushed(ExtractKassenbonJob::class);
});

it('verlangt eine Lieferantennummer, wenn der gelesene Name keinen Treffer hat', function (): void {
    Permission::findOrCreate('see-app-bestellungen', 'web');

    $user = User::factory()->create(['username' => 'hwkdo42']);
    $user->givePermissionTo('see-app-bestellungen');
    $user->assignRole('Benutzer');

    LieferantCache::query()->create([
        'lieferantennummer' => '1',
        'lieferantenname' => 'Unbeteiligt GmbH',
    ]);
    KostenstelleCache::query()->create([
        'kostenstelle' => '1000',
        'bezeichnung' => 'Test',
    ]);
    D3RestLaravel::shouldReceive('getUserInGroupsSoapCached')->andReturn(['@Rechnungen']);
    D3RestLaravel::shouldReceive('getD3GroupsSoapCached')->andReturn(['@Rechnungen']);

    $kassenbon = Kassenbon::query()->create([
        'user_id' => $user->id,
        'datei_pfad' => 'bestellungen/kassenbons/probe.pdf',
        'mime' => 'application/pdf',
        'extraction_status' => 'done',
        'extraction_payload' => [
            'structured' => [
                'supplier_name' => 'Voltwerk Retail GmbH',
                'receipt_number' => '4471',
                'receipt_date' => '2026-10-17',
                'net_amount' => 10,
                'tax_amount' => 1.9,
                'amount' => 11.9,
                'payment_method' => 'bar',
                'line_items' => [[
                    'description' => 'Kabel',
                    'quantity' => 1,
                    'unit' => 'Stk',
                    'unit_price' => 11.9,
                ]],
            ],
            'lieferant' => [
                'erkannter_name' => 'Voltwerk Retail GmbH',
                'lieferantennummer' => null,
                'lieferantenname' => null,
                'status' => 'offen',
            ],
        ],
    ]);

    Livewire::actingAs($user)
        ->test(ErstellenNachtraeglich::class, ['beleg' => (string) $kassenbon->id])
        ->assertSet('lieferantennummer', null)
        ->assertSet('lieferantSearch', '')
        ->set('auszahlungsEmpfaenger', 'mitarbeiter')
        ->set('auszahlungsArt', 'ueberweisung')
        ->call('auszahlungUebernehmen')
        ->assertSee('Voltwerk Retail GmbH')
        ->set('begruendung', 'Ersatzteil musste sofort gekauft werden.')
        ->set('kostenstelle', '1000')
        ->set('d3GruppenAuswahl', ['@Rechnungen'])
        ->call('abschliessen')
        ->assertHasErrors(['lieferantennummer' => 'required'])
        ->assertSee('Bitte einen Lieferanten wählen');

    expect(Bestellung::query()->count())->toBe(0);

    Livewire::actingAs($user)
        ->test(ErstellenNachtraeglich::class, ['beleg' => (string) $kassenbon->id])
        ->set('auszahlungsEmpfaenger', 'mitarbeiter')
        ->set('auszahlungsArt', 'ueberweisung')
        ->call('auszahlungUebernehmen')
        ->set('lieferantennummer', 'Voltwerk Retail GmbH')
        ->set('begruendung', 'Ersatzteil musste sofort gekauft werden.')
        ->set('kostenstelle', '1000')
        ->set('d3GruppenAuswahl', ['@Rechnungen'])
        ->call('abschliessen')
        ->assertHasErrors(['lieferantennummer'])
        ->assertSee('steht nicht in den Lieferanten');

    expect(Bestellung::query()->count())->toBe(0);
});

it('belegt die Begründung aus der gewählten Auszahlung', function (): void {
    Permission::findOrCreate('see-app-bestellungen', 'web');

    $user = User::factory()->create([
        'username' => 'hwkdo42',
        'vorname' => 'Anna',
        'nachname' => 'Müller',
    ]);
    $user->givePermissionTo('see-app-bestellungen');

    LieferantCache::query()->create([
        'lieferantennummer' => '1',
        'lieferantenname' => 'Unbeteiligt GmbH',
    ]);
    KostenstelleCache::query()->create([
        'kostenstelle' => '1000',
        'bezeichnung' => 'Test',
    ]);
    D3RestLaravel::shouldReceive('getUserInGroupsSoapCached')->andReturn([]);
    D3RestLaravel::shouldReceive('getD3GroupsSoapCached')->andReturn([]);

    $kassenbon = Kassenbon::query()->create([
        'user_id' => $user->id,
        'datei_pfad' => 'bestellungen/kassenbons/probe.pdf',
        'mime' => 'application/pdf',
        'extraction_status' => 'done',
        'extraction_payload' => [
            'structured' => ['supplier_name' => 'Voltwerk Retail GmbH'],
            'lieferant' => [
                'erkannter_name' => 'Voltwerk Retail GmbH',
                'status' => 'offen',
            ],
        ],
    ]);

    Livewire::actingAs($user)
        ->test(ErstellenNachtraeglich::class, ['beleg' => (string) $kassenbon->id])
        ->assertSee('Art der Aus-/Rückzahlung')
        ->set('auszahlungsEmpfaenger', 'mitarbeiter')
        ->set('auszahlungsArt', 'ueberweisung')
        ->call('auszahlungUebernehmen')
        ->assertSet('begruendung', 'Auszahlung per Überweisung auf das Konto von Anna Müller.')
        ->call('auszahlungAendern')
        ->set('auszahlungsArt', 'bar')
        ->call('auszahlungUebernehmen')
        ->assertSet('begruendung', 'Auszahlung per Barauszahlung an Anna Müller.')
        ->call('auszahlungAendern')
        ->set('auszahlungsEmpfaenger', 'lieferant')
        ->call('auszahlungUebernehmen')
        ->assertSet('auszahlungsArt', 'ueberweisung')
        ->assertSet('begruendung', 'Auszahlung per Überweisung an Lieferanten Voltwerk Retail GmbH.');
});

it('merkt den aktuellen Auswertungs-Schritt am Kassenbon', function (): void {
    $user = User::factory()->create();
    $kassenbon = Kassenbon::query()->create([
        'user_id' => $user->id,
        'datei_pfad' => 'bestellungen/kassenbons/probe.pdf',
        'mime' => 'application/pdf',
        'extraction_status' => 'pending',
    ]);

    $kassenbon->merkeSchritt('lesen');

    $kassenbon->refresh();

    expect($kassenbon->extraction_status)->toBe('processing')
        ->and($kassenbon->extraction_payload['schritt'])->toBe('lesen');
});

it('ordnet einen eindeutig erkannten Lieferanten zu', function (): void {
    $user = User::factory()->create();
    LieferantCache::query()->create([
        'lieferantennummer' => '471100',
        'lieferantenname' => 'Nordmarkt Baumarkt GmbH',
    ]);

    $extraction = Mockery::mock(KassenbonExtractionService::class);
    $extraction->shouldReceive('extract')->once()->andReturn(kassenbonExtraktion('Nordmarkt Baumarkt GmbH'));
    app()->instance(KassenbonExtractionService::class, $extraction);

    $kassenbon = Kassenbon::query()->create([
        'user_id' => $user->id,
        'datei_pfad' => 'bestellungen/kassenbons/probe.pdf',
        'mime' => 'application/pdf',
        'extraction_status' => 'pending',
    ]);

    (new ExtractKassenbonJob($kassenbon->id))->handle(
        app(KassenbonExtractionService::class),
        app(LieferantErkennungService::class),
    );

    $kassenbon->refresh();

    expect($kassenbon->extraction_status)->toBe('done')
        ->and($kassenbon->extraction_payload['lieferant']['status'])->toBe('eindeutig')
        ->and($kassenbon->extraction_payload['lieferant']['lieferantennummer'])->toBe('471100');
});

it('ordnet einen mehrdeutigen Lieferanten nicht zu', function (): void {
    $user = User::factory()->create();
    LieferantCache::query()->create([
        'lieferantennummer' => '100',
        'lieferantenname' => 'Werkmarkt GmbH & Co. KG',
    ]);
    LieferantCache::query()->create([
        'lieferantennummer' => '200',
        'lieferantenname' => 'Werkmarkt AG',
    ]);

    $extraction = Mockery::mock(KassenbonExtractionService::class);
    $extraction->shouldReceive('extract')->once()->andReturn(kassenbonExtraktion('Werkmarkt GmbH'));
    app()->instance(KassenbonExtractionService::class, $extraction);

    $kassenbon = Kassenbon::query()->create([
        'user_id' => $user->id,
        'datei_pfad' => 'bestellungen/kassenbons/probe.pdf',
        'mime' => 'application/pdf',
        'extraction_status' => 'pending',
    ]);

    (new ExtractKassenbonJob($kassenbon->id))->handle(
        app(KassenbonExtractionService::class),
        app(LieferantErkennungService::class),
    );

    $kassenbon->refresh();

    expect($kassenbon->extraction_payload['lieferant']['status'])->toBe('offen')
        ->and($kassenbon->extraction_payload['lieferant']['lieferantennummer'])->toBeNull();
});

it('überträgt BESTS und ZAHLB mit BEN, Rechnung und Platzhalter-Lieferant', function (): void {
    $payloads = [];
    kassenbonD3Vorbereiten($payloads, false);
    $user = User::factory()->create(['username' => 'hwkdo42']);
    $kassenbon = Kassenbon::query()->create([
        'user_id' => $user->id,
        'datei_pfad' => 'bestellungen/kassenbons/probe.pdf',
        'mime' => 'application/pdf',
        'extraction_status' => 'done',
    ]);

    $bestellung = app(NachtraeglicheBestellungService::class)->abschliessen(
        $kassenbon,
        $user,
        kassenbonEingabe(),
    );

    $bestellung->refresh();
    $kassenbon->refresh();
    $bests = collect($payloads)->firstWhere('objectDefinitionId', 'BESTS');
    $zahlb = collect($payloads)->firstWhere('objectDefinitionId', 'ZAHLB');

    expect($bestellung->typ)->toBe(BestellungTyp::Nachtraeglich)
        ->and($bestellung->status)->toBe(BestellungStatus::Bestellt)
        ->and($bestellung->benoetigtFinalenLieferantenVorD3())->toBeFalse()
        ->and($bestellung->d3id)->not->toBeNull()
        ->and($kassenbon->d3id_rechnung)->not->toBeNull()
        ->and($bests['extendedProperties']['18'])->toBe((int) $bestellung->nummer)
        ->and($zahlb['extendedProperties']['18'])->toBe((int) $bestellung->nummer)
        ->and($zahlb['extendedProperties']['83'])->toBe('Test, User')
        ->and($zahlb['extendedProperties']['82'])->toBe('Rechnung')
        ->and($zahlb['extendedProperties']['60'])->toBe('0')
        ->and($zahlb['extendedProperties']['64'])->toBe('7000720')
        ->and($zahlb['systemProperties']['property_filename'])->toContain((string) $bestellung->nummer);
});

it('überträgt eine Barauszahlung mit Belegtyp Barauszahlung', function (): void {
    $payloads = [];
    kassenbonD3Vorbereiten($payloads, false);
    $user = User::factory()->create(['username' => 'hwkdo42']);
    $kassenbon = Kassenbon::query()->create([
        'user_id' => $user->id,
        'datei_pfad' => 'bestellungen/kassenbons/probe.pdf',
        'mime' => 'application/pdf',
        'extraction_status' => 'done',
    ]);

    app(NachtraeglicheBestellungService::class)->abschliessen(
        $kassenbon,
        $user,
        kassenbonEingabe('mitarbeiter', 'bar'),
    );

    $zahlb = collect($payloads)->firstWhere('objectDefinitionId', 'ZAHLB');

    expect($zahlb['extendedProperties']['82'])->toBe('Barauszahlung');
});

it('lässt die Bestellung im Entwurf, wenn die Rechnung in D3 fehlschlägt', function (): void {
    $payloads = [];
    kassenbonD3Vorbereiten($payloads, true);
    $user = User::factory()->create(['username' => 'hwkdo42']);
    $kassenbon = Kassenbon::query()->create([
        'user_id' => $user->id,
        'datei_pfad' => 'bestellungen/kassenbons/probe.pdf',
        'mime' => 'application/pdf',
        'extraction_status' => 'done',
    ]);

    expect(fn () => app(NachtraeglicheBestellungService::class)->abschliessen(
        $kassenbon,
        $user,
        kassenbonEingabe(),
    ))->toThrow(WorkflowException::class);

    $bestellung = Bestellung::query()->first();

    expect($bestellung)->not->toBeNull()
        ->and($bestellung->status)->toBe(BestellungStatus::Entwurf)
        ->and($bestellung->d3id)->not->toBeNull()
        ->and($bestellung->kassenbon->d3id_rechnung)->toBeNull();
});

/**
 * @return array<string, mixed>
 */
function kassenbonExtraktion(string $supplierName): array
{
    return [
        'supplier_name' => $supplierName,
        'receipt_number' => '0',
        'line_items' => [],
        'vat' => [],
        'source' => 'document_parse',
        'provider' => 'OpenAI',
        'payload' => ['model' => 'test', 'text_excerpt' => $supplierName],
    ];
}

function kassenbonEingabe(?string $auszahlungsEmpfaenger = null, ?string $auszahlungsArt = null): NachtraeglicheBestellungEingabe
{
    return new NachtraeglicheBestellungEingabe(
        lieferantennummer: '7000720',
        lieferantenname: 'Unbekannter Lieferant',
        belegnummer: '0',
        belegdatum: '2026-11-10',
        netto: 14.92,
        steuer: 2.84,
        brutto: 17.76,
        zahlungsart: 'bar',
        betreff: 'Siphon',
        begruendung: 'Ersatzteil musste sofort im Baumarkt gekauft werden.',
        kostenstelle: '4711',
        haushaltsjahr: 2026,
        projektId: null,
        gruppen: ['@Rechnungen'],
        kontierung: [],
        positionen: [[
            'bezeichnung' => 'Siphon',
            'menge' => 1,
            'einheit' => 'Stk',
            'art_nr' => null,
            'preis' => 17.76,
        ]],
        lieferanschriftUserId: null,
        auszahlungsEmpfaenger: $auszahlungsEmpfaenger,
        auszahlungsArt: $auszahlungsArt,
    );
}

/**
 * @param  array<int, array<string, mixed>>  $payloads
 */
function kassenbonD3Vorbereiten(array &$payloads, bool $rechnungFehlschlaegt): void
{
    $pdf = Mockery::mock(PdfService::class);
    $pdf->shouldReceive('saveFromHtml')->andReturnUsing(function (string $html, string $path, string $filename): string {
        File::ensureDirectoryExists($path);
        $name = str_ends_with($filename, '.pdf') ? $filename : $filename.'.pdf';
        file_put_contents($path.'/'.$name, '%PDF-1.4');

        return $name;
    });
    $pdf->shouldReceive('mergePdfs')->andReturnUsing(function (array $pdfs, string $output, string $filename): string {
        File::ensureDirectoryExists($output);
        $name = str_ends_with($filename, '.pdf') ? $filename : $filename.'.pdf';
        $target = $output.'/'.$name;
        file_put_contents($target, '%PDF-1.4');

        return $target;
    });
    app()->instance(PdfService::class, $pdf);

    $benutzer = Mockery::mock(D3BenutzerResolver::class);
    $benutzer->shouldReceive('resolve')->andReturn(['Test, User']);
    $benutzer->shouldReceive('wert')->andReturn('Test, User');
    app()->instance(D3BenutzerResolver::class, $benutzer);

    $payloads = [];
    $calls = 0;
    D3RestLaravel::shouldReceive('temporaryUpload')->andReturn(new TempUploadDTO(true, null, 'upload.pdf', '/tmp/upload.pdf'));
    D3RestLaravel::shouldReceive('pushDocument')->andReturnUsing(function (array $data) use (&$payloads, &$calls, $rechnungFehlschlaegt): NewObjectDTO {
        $calls++;
        $payloads[] = $data;
        $ok = ! ($rechnungFehlschlaegt && $calls >= 2);

        return new NewObjectDTO($ok, $ok ? null : 'd3-fehler', null, $ok ? 'D3-'.$calls : null);
    });
}
