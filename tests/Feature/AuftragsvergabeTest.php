<?php

declare(strict_types=1);

use App\Models\User;
use App\Services\IntranetLegacyService;
use App\Services\PdfService;
use Hwkdo\IntranetAppBestellungen\Data\AppSettings;
use Hwkdo\IntranetAppBestellungen\Enums\BestellungStatus;
use Hwkdo\IntranetAppBestellungen\Enums\BestellungTyp;
use Hwkdo\IntranetAppBestellungen\Exceptions\AuftragsscheinUnvollstaendigException;
use Hwkdo\IntranetAppBestellungen\Exceptions\WorkflowException;
use Hwkdo\IntranetAppBestellungen\Jobs\ExtractAngebotMetadataJob;
use Hwkdo\IntranetAppBestellungen\Livewire\Apps\Bestellungen\Detail;
use Hwkdo\IntranetAppBestellungen\Livewire\Apps\Bestellungen\Erstellen;
use Hwkdo\IntranetAppBestellungen\Mail\FehlenderLieferantGemeldetMail;
use Hwkdo\IntranetAppBestellungen\Models\Angebot;
use Hwkdo\IntranetAppBestellungen\Models\Auftragszeile;
use Hwkdo\IntranetAppBestellungen\Models\Bestellung;
use Hwkdo\IntranetAppBestellungen\Models\IntranetAppBestellungenSettings;
use Hwkdo\IntranetAppBestellungen\Models\LieferantCache;
use Hwkdo\IntranetAppBestellungen\Services\Api\AngebotPdfMetadataExtractionService;
use Hwkdo\IntranetAppBestellungen\Services\BestellungWorkflow;
use Hwkdo\IntranetAppBestellungen\Services\Lieferant\LieferantErkennungService;
use Hwkdo\IntranetAppBestellungen\Services\Pdf\BestellscheinPdfService;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
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

it('legt eine Auftragsvergabe ohne Positionen an', function (): void {
    $user = User::factory()->create();
    $user->assignRole('Benutzer');

    Livewire::actingAs($user)
        ->test(Erstellen::class, ['typ' => 'auftragsvergabe'])
        ->set('kostenstelle', '4711')
        ->set('haushaltsjahr', 2026)
        ->set('betreff', 'Dachsanierung')
        ->set('begruendung', 'Beauftragung auf Basis der vorliegenden Angebote.')
        ->set('d3GruppenAuswahl', ['@Rechnungen'])
        ->call('speichern')
        ->assertHasNoErrors()
        ->assertRedirect();

    $bestellung = Bestellung::query()->where('user_id', $user->id)->first();

    expect($bestellung)->not->toBeNull()
        ->and($bestellung->typ)->toBe(BestellungTyp::Auftragsvergabe)
        ->and($bestellung->positionen)->toHaveCount(0)
        ->and($bestellung->lieferantenname)->toBeNull()
        ->and((float) $bestellung->gesamtbetrag)->toEqual(0.0);
});

it('übernimmt Angebote und manuelle Zeilen und rechnet den Nettobetrag', function (): void {
    $user = User::factory()->create([
        'vorname' => 'Max',
        'nachname' => 'Mustermann',
        'title' => 'Abteilungsleitung',
        'extension2' => 'Abteilung Gebäude',
    ]);
    $user->assignRole('Benutzer');

    $bestellung = Bestellung::factory()->auftragsvergabe()->create([
        'status' => BestellungStatus::Entwurf,
        'user_id' => $user->id,
        'lieferantenname' => null,
        'lieferantennummer' => null,
        'gesamtbetrag' => 0,
    ]);

    $angebot = Angebot::query()->create([
        'bestellung_id' => $bestellung->getKey(),
        'user_id' => $user->id,
        'typ' => 'angebot',
        'lieferantenname' => 'Dachdecker Meier GmbH',
        'nummer' => 'A-100',
        'angebotsdatum' => '2026-10-12',
        'betrag_netto' => 1000,
        'betrag' => 1190,
    ]);

    Livewire::actingAs($user)
        ->test(Detail::class, ['bestellung' => $bestellung])
        ->set('ausgewaehlteAngebotIds', [$angebot->getKey()])
        ->call('angeboteAlsAuftragszeilenUebernehmen')
        ->set('manuelleZeileDatum', '2026-01-15')
        ->set('manuelleZeileNummer', 'M-9')
        ->set('manuelleZeileNetto', '250.50')
        ->set('manuelleZeileBrutto', '298.10')
        ->set('manuelleZeileFreitext', 'Bitte die Gerüststellung beachten.')
        ->call('manuelleAuftragszeileSpeichern')
        ->assertHasNoErrors()
        ->assertDontSee('Angebotsvoraussetzungen')
        ->assertDontSee('Für diesen Betrag sind keine Vergleichsangebote erforderlich.');

    $bestellung->refresh();

    expect($bestellung->auftragszeilen)->toHaveCount(2)
        ->and($bestellung->lieferantenname)->toBe('Dachdecker Meier GmbH')
        ->and((float) $bestellung->gesamtbetrag)->toEqual(1250.5)
        ->and($bestellung->toSearchableArray()['positionen_text'])->toContain('A-100')->toContain('Gerüststellung');

    $html = app(BestellscheinPdfService::class)->html(
        $bestellung->fresh(['auftragszeilen', 'user', 'besteller']),
        BestellungTyp::Auftragsvergabe,
    );

    expect($html)
        ->toContain('Auftragnehmer')
        ->toContain('Auftraggeber')
        ->toContain('Handwerkskammer Dortmund')
        ->toContain('Max Mustermann')
        ->toContain('Abteilung Gebäude')
        ->toContain('Ihr Angebot vom 12.10.2026 Nr. A-100')
        ->toContain('Ihr Angebot vom 15.01.2026 Nr. M-9')
        ->toContain('1.000,00')
        ->toContain('250,50')
        ->toContain('Bitte die Gerüststellung beachten.')
        ->toContain('Unterschrift Auftraggeber')
        ->toContain('Abteilungsleitung');
});

it('übernimmt Angebote unterschiedlicher Anbieter nicht', function (): void {
    $user = User::factory()->create();
    $user->assignRole('Benutzer');

    $bestellung = Bestellung::factory()->auftragsvergabe()->create([
        'status' => BestellungStatus::Entwurf,
        'user_id' => $user->id,
        'lieferantenname' => null,
        'lieferantennummer' => null,
        'gesamtbetrag' => 0,
    ]);

    $erster = Angebot::query()->create([
        'bestellung_id' => $bestellung->getKey(),
        'user_id' => $user->id,
        'typ' => 'angebot',
        'lieferantenname' => 'Dachdecker Meier GmbH',
        'nummer' => 'A-1',
        'angebotsdatum' => '2026-10-12',
        'betrag_netto' => 100,
        'betrag' => 119,
    ]);
    $zweiter = Angebot::query()->create([
        'bestellung_id' => $bestellung->getKey(),
        'user_id' => $user->id,
        'typ' => 'angebot',
        'lieferantenname' => 'Maler Schulz',
        'nummer' => 'A-2',
        'angebotsdatum' => '2026-10-13',
        'betrag_netto' => 80,
        'betrag' => 95.2,
    ]);

    Livewire::actingAs($user)
        ->test(Detail::class, ['bestellung' => $bestellung])
        ->set('ausgewaehlteAngebotIds', [$erster->getKey(), $zweiter->getKey()])
        ->call('angeboteAlsAuftragszeilenUebernehmen');

    $bestellung->refresh();

    expect($bestellung->auftragszeilen)->toHaveCount(0)
        ->and($bestellung->lieferantenname)->toBeNull();
});

it('verweigert das Einreichen ohne Auftragszeile', function (): void {
    $user = User::factory()->create();
    $user->assignRole('Benutzer');

    $bestellung = Bestellung::factory()->auftragsvergabe()->create([
        'status' => BestellungStatus::Entwurf,
        'user_id' => $user->id,
        'gesamtbetrag' => 0,
    ]);

    expect(fn () => app(BestellungWorkflow::class)->einreichen($bestellung, $user))
        ->toThrow(WorkflowException::class, 'mindestens eine Auftragszeile');
});

it('verweigert Einreichen und Bestellen ohne Angebots-PDF an der Auftragszeile', function (): void {
    $user = User::factory()->create();
    $user->assignRole('Benutzer');

    $entwurf = Bestellung::factory()->auftragsvergabe()->create([
        'status' => BestellungStatus::Entwurf,
        'user_id' => $user->id,
        'lieferantenname' => 'Dach GmbH',
        'gesamtbetrag' => 100,
    ]);
    Auftragszeile::factory()->create([
        'bestellung_id' => $entwurf->id,
        'angebot_id' => null,
        'nr' => 1,
        'angebotsdatum' => '2026-10-12',
        'angebotsnummer' => 'A-100',
        'betrag_netto' => 100,
    ]);

    expect(fn () => app(BestellungWorkflow::class)->einreichen($entwurf->fresh(), $user))
        ->toThrow(WorkflowException::class, 'hinterlegte Angebot als PDF');

    $freigegeben = Bestellung::factory()->auftragsvergabe()->create([
        'status' => BestellungStatus::Freigegeben,
        'user_id' => $user->id,
        'lieferantenname' => 'Dach GmbH',
        'gesamtbetrag' => 100,
    ]);
    Auftragszeile::factory()->create([
        'bestellung_id' => $freigegeben->id,
        'angebot_id' => null,
        'nr' => 1,
        'angebotsdatum' => '2026-10-12',
        'angebotsnummer' => 'A-100',
        'betrag_netto' => 100,
    ]);

    expect(fn () => app(BestellungWorkflow::class)->bestellen($freigegeben->fresh(), $user))
        ->toThrow(WorkflowException::class, 'hinterlegte Angebot als PDF');
});

it('hängt die PDFs der übernommenen Angebote in Zeilenreihenfolge an den Auftragsschein', function (): void {
    Storage::fake('local');

    $user = User::factory()->create();
    $bestellung = Bestellung::factory()->auftragsvergabe()->create([
        'user_id' => $user->id,
        'lieferantenname' => 'Dach GmbH',
    ]);

    $erster = angebotMitPdf($bestellung, $user, 'erstes.pdf');
    $zweiter = angebotMitPdf($bestellung, $user, 'zweites.pdf');
    $nichtGewaehlt = angebotMitPdf($bestellung, $user, 'offen.pdf');

    Auftragszeile::factory()->create([
        'bestellung_id' => $bestellung->id,
        'angebot_id' => $zweiter->id,
        'nr' => 2,
        'angebotsnummer' => 'B-2',
    ]);
    Auftragszeile::factory()->create([
        'bestellung_id' => $bestellung->id,
        'angebot_id' => $erster->id,
        'nr' => 1,
        'angebotsnummer' => 'A-1',
    ]);

    $merged = null;
    $pdf = Mockery::mock(PdfService::class);
    $pdf->shouldReceive('saveFromHtml')->once()->andReturnUsing(function (string $html, string $path, string $filename): string {
        File::ensureDirectoryExists($path);
        $name = str_ends_with($filename, '.pdf') ? $filename : $filename.'.pdf';
        file_put_contents($path.'/'.$name, '%PDF-schein');

        return $name;
    });
    $pdf->shouldReceive('mergePdfs')->once()->andReturnUsing(function (array $pdfs) use (&$merged): string {
        $merged = $pdfs;

        return $pdfs[0];
    });
    app()->instance(PdfService::class, $pdf);

    app(BestellscheinPdfService::class)->buildFile($bestellung->fresh(), BestellungTyp::Auftragsvergabe);

    expect($merged)->toHaveCount(3)
        ->and($merged[1])->toBe($erster->absolutePdfPath())
        ->and($merged[2])->toBe($zweiter->absolutePdfPath())
        ->and($merged)->not->toContain($nichtGewaehlt->absolutePdfPath());
});

it('erzeugt keinen Auftragsschein, wenn einer Auftragszeile die Angebots-PDF fehlt', function (): void {
    Storage::fake('local');

    $user = User::factory()->create();
    $bestellung = Bestellung::factory()->auftragsvergabe()->create([
        'user_id' => $user->id,
        'lieferantenname' => 'Dach GmbH',
    ]);
    $mitPdf = angebotMitPdf($bestellung, $user, 'vorhanden.pdf');
    $ohnePdf = Angebot::query()->create([
        'bestellung_id' => $bestellung->id,
        'user_id' => $user->id,
        'typ' => 'angebot',
        'pdf_path' => 'bestellungen/angebote/fehlt.pdf',
    ]);

    Auftragszeile::factory()->create([
        'bestellung_id' => $bestellung->id,
        'angebot_id' => $mitPdf->id,
        'nr' => 1,
    ]);
    Auftragszeile::factory()->create([
        'bestellung_id' => $bestellung->id,
        'angebot_id' => $ohnePdf->id,
        'nr' => 2,
    ]);

    $pdf = Mockery::mock(PdfService::class);
    $pdf->shouldReceive('saveFromHtml')->once()->andReturnUsing(function (string $html, string $path, string $filename): string {
        File::ensureDirectoryExists($path);
        $name = str_ends_with($filename, '.pdf') ? $filename : $filename.'.pdf';
        file_put_contents($path.'/'.$name, '%PDF-schein');

        return $name;
    });
    $pdf->shouldNotReceive('mergePdfs');
    app()->instance(PdfService::class, $pdf);

    expect(fn () => app(BestellscheinPdfService::class)->buildFile($bestellung->fresh(), BestellungTyp::Auftragsvergabe))
        ->toThrow(AuftragsscheinUnvollstaendigException::class, 'Auftragszeile 2');
});

it('wiederholt eine Auftragsvergabe ohne Zeilen und ohne Angebote', function (): void {
    $user = User::factory()->create();
    $user->assignRole('Benutzer');

    $bestellung = Bestellung::factory()->auftragsvergabe()->create([
        'status' => BestellungStatus::Bestellt,
        'user_id' => $user->id,
        'gesamtbetrag' => 500,
    ]);

    Angebot::query()->create([
        'bestellung_id' => $bestellung->getKey(),
        'typ' => 'angebot',
        'nummer' => 'A-1',
        'betrag_netto' => 500,
        'betrag' => 595,
    ]);

    $bestellung->auftragszeilen()->create([
        'nr' => 1,
        'angebotsdatum' => '2026-10-12',
        'angebotsnummer' => 'A-1',
        'betrag_netto' => 500,
        'betrag_brutto' => 595,
        'freitext' => 'Hinweis',
    ]);

    Livewire::actingAs($user)
        ->test(Detail::class, ['bestellung' => $bestellung->fresh()])
        ->call('wiederholen')
        ->assertRedirect();

    $kopie = Bestellung::query()->where('wiederholt_von_id', $bestellung->getKey())->first();

    expect($kopie)->not->toBeNull()
        ->and($kopie->auftragszeilen)->toHaveCount(0)
        ->and($kopie->angebote)->toHaveCount(0)
        ->and($kopie->positionen)->toHaveCount(0)
        ->and((float) $kopie->gesamtbetrag)->toEqual(0.0);
});

it('schreibt extrahiertes Angebotsdatum und Netto auf das Angebot', function (): void {
    Storage::fake('local');

    $bestellung = Bestellung::factory()->auftragsvergabe()->create();
    $path = 'bestellungen/angebote/'.$bestellung->getKey().'/angebot.pdf';
    Storage::disk('local')->put($path, '%PDF-1.4');

    $angebot = Angebot::query()->create([
        'bestellung_id' => $bestellung->getKey(),
        'typ' => 'angebot',
        'pdf_path' => $path,
        'extraction_status' => 'pending',
    ]);

    $extraction = Mockery::mock(AngebotPdfMetadataExtractionService::class);
    $extraction->shouldReceive('extract')->once()->andReturn([
        'supplier_name' => 'Muster GmbH',
        'reference_number' => 'A-100',
        'offer_date' => '2026-10-12',
        'net_amount' => 1000.5,
        'amount' => 1190.6,
        'source' => 'text',
        'method' => 'structured',
        'provider' => 'OpenAI',
        'payload' => ['model' => 'gpt-test'],
        'confidence' => 1.0,
    ]);

    (new ExtractAngebotMetadataJob($angebot->getKey()))->handle(
        $extraction,
        app(BestellungWorkflow::class),
        app(LieferantErkennungService::class),
    );

    $angebot->refresh();

    expect($angebot->angebotsdatum?->toDateString())->toBe('2026-10-12')
        ->and((float) $angebot->betrag_netto)->toEqual(1000.5)
        ->and((float) $angebot->betrag)->toEqual(1190.6)
        ->and($angebot->nummer)->toBe('A-100')
        ->and($angebot->lieferantenname)->toBe('Muster GmbH')
        ->and($angebot->extraction_status)->toBe('done');
});

it('zeigt den Analysestatus und übernimmt ein Angebot erst nach ergänzten Angaben', function (): void {
    $user = User::factory()->create();
    $user->assignRole('Benutzer');

    $bestellung = Bestellung::factory()->auftragsvergabe()->create([
        'status' => BestellungStatus::Entwurf,
        'user_id' => $user->id,
        'lieferantenname' => null,
        'lieferantennummer' => null,
        'gesamtbetrag' => 0,
    ]);

    $angebot = Angebot::query()->create([
        'bestellung_id' => $bestellung->getKey(),
        'user_id' => $user->id,
        'typ' => 'angebot',
        'extraction_status' => 'failed',
        'extraction_error' => 'HTTP 404',
    ]);

    Livewire::actingAs($user)
        ->test(Detail::class, ['bestellung' => $bestellung])
        ->assertSee('Analyse fehlgeschlagen')
        ->assertSee('Lieferant wählen')
        ->assertSee('Lokal, Übertragung beim Bestellen')
        ->assertDontSee('lokal (Push bei Bestellen)')
        ->assertDontSee('Angebot anzeigen')
        ->set('ausgewaehlteAngebotIds', [$angebot->getKey()])
        ->call('angeboteAlsAuftragszeilenUebernehmen');

    expect($bestellung->fresh()->auftragszeilen)->toHaveCount(0);

    LieferantCache::query()->create([
        'lieferantennummer' => '100',
        'lieferantenname' => 'Dachdecker Meier GmbH',
    ]);

    Livewire::actingAs($user)
        ->test(Detail::class, ['bestellung' => $bestellung->fresh()])
        ->call('angebotAngabenOeffnen', $angebot->getKey())
        ->set('angebotAngabenLieferantennummer', '100')
        ->set('angebotAngabenNummer', 'A-100')
        ->set('angebotAngabenDatum', '2026-10-12')
        ->set('angebotAngabenNetto', '1000')
        ->set('angebotAngabenBrutto', '1190')
        ->call('angebotAngabenSpeichern')
        ->assertHasNoErrors()
        ->assertSee('Analyse abgeschlossen')
        ->assertSee('Lieferant 100')
        ->assertDontSee('Lieferant 100 zugeordnet');

    $angebot->refresh();

    expect($angebot->extraction_status)->toBe('done')
        ->and($angebot->extraction_source)->toBe('manual')
        ->and($angebot->lieferantenname)->toBe('Dachdecker Meier GmbH')
        ->and($angebot->lieferantennummer)->toBe('100');

    Livewire::actingAs($user)
        ->test(Detail::class, ['bestellung' => $bestellung->fresh()])
        ->set('ausgewaehlteAngebotIds', [$angebot->getKey()])
        ->call('angeboteAlsAuftragszeilenUebernehmen');

    $bestellung->refresh();

    expect($bestellung->auftragszeilen)->toHaveCount(1)
        ->and($bestellung->lieferantenname)->toBe('Dachdecker Meier GmbH');
});

it('meldet einen fehlenden Lieferanten aus den Angebotsangaben', function (): void {
    Mail::fake();

    $user = User::factory()->create();
    $user->assignRole('Benutzer');

    $bestellung = Bestellung::factory()->auftragsvergabe()->create([
        'status' => BestellungStatus::Entwurf,
        'user_id' => $user->id,
    ]);

    $angebot = Angebot::query()->create([
        'bestellung_id' => $bestellung->getKey(),
        'user_id' => $user->id,
        'typ' => 'angebot',
        'lieferantenname' => 'Eaton',
        'extraction_status' => 'done',
    ]);

    Livewire::actingAs($user)
        ->test(Detail::class, ['bestellung' => $bestellung])
        ->call('angebotAngabenOeffnen', $angebot->getKey())
        ->call('angebotFehlendenLieferantVorbereiten')
        ->set('fehlenderLieferantName', 'Eaton Industries GmbH')
        ->set('fehlenderLieferantAdresse', 'Hein-Moeller-Str. 7, 53115 Bonn')
        ->call('angebotFehlendenLieferantMelden')
        ->assertHasNoErrors()
        ->assertSet('angebotAngabenLieferantennummer', '7000720');

    Mail::assertQueued(FehlenderLieferantGemeldetMail::class);
});

it('startet eine fehlgeschlagene Angebotsanalyse erneut', function (): void {
    Queue::fake();

    $user = User::factory()->create();
    $user->assignRole('Benutzer');

    $bestellung = Bestellung::factory()->auftragsvergabe()->create([
        'status' => BestellungStatus::Entwurf,
        'user_id' => $user->id,
    ]);

    $angebot = Angebot::query()->create([
        'bestellung_id' => $bestellung->getKey(),
        'user_id' => $user->id,
        'typ' => 'angebot',
        'pdf_path' => 'bestellungen/angebote/'.$bestellung->getKey().'/angebot.pdf',
        'extraction_status' => 'failed',
        'extraction_error' => 'HTTP 404',
    ]);

    Livewire::actingAs($user)
        ->test(Detail::class, ['bestellung' => $bestellung])
        ->assertSee('Analyse erneut starten')
        ->call('angebotAnalyseErneutStarten', $angebot->getKey())
        ->assertSee('Analyse läuft');

    $angebot->refresh();

    expect($angebot->extraction_status)->toBe('pending')
        ->and($angebot->extraction_error)->toBeNull();

    Queue::assertPushed(ExtractAngebotMetadataJob::class, function (ExtractAngebotMetadataJob $job) use ($angebot): bool {
        return $job->angebotId === $angebot->getKey();
    });
});

it('aktualisiert die Seite solange die Angebotsanalyse läuft', function (): void {
    $user = User::factory()->create();
    $user->assignRole('Benutzer');

    $bestellung = Bestellung::factory()->auftragsvergabe()->create([
        'status' => BestellungStatus::Entwurf,
        'user_id' => $user->id,
    ]);

    Angebot::query()->create([
        'bestellung_id' => $bestellung->getKey(),
        'user_id' => $user->id,
        'typ' => 'angebot',
        'extraction_status' => 'processing',
    ]);

    Livewire::actingAs($user)
        ->test(Detail::class, ['bestellung' => $bestellung])
        ->assertSee('Analyse läuft')
        ->assertSee('wire:poll.5s.visible');
});

it('verlinkt ein in D3 liegendes Angebot auf d3one', function (): void {
    config(['d3-rest-laravel.d3one-object-url-template' => 'https://d3one.example/o2/{id}/']);

    $user = User::factory()->create();
    $user->assignRole('Benutzer');

    $bestellung = Bestellung::factory()->auftragsvergabe()->create([
        'status' => BestellungStatus::Entwurf,
        'user_id' => $user->id,
    ]);

    Angebot::query()->create([
        'bestellung_id' => $bestellung->getKey(),
        'user_id' => $user->id,
        'typ' => 'angebot',
        'd3id' => 'DOC-99',
        'extraction_status' => 'done',
    ]);

    Livewire::actingAs($user)
        ->test(Detail::class, ['bestellung' => $bestellung])
        ->assertSeeHtml('href="https://d3one.example/o2/DOC-99/"')
        ->assertSeeHtml('target="_blank"');
});

function angebotMitPdf(Bestellung $bestellung, User $user, string $dateiname): Angebot
{
    $pfad = 'bestellungen/angebote/'.$bestellung->id.'/'.$dateiname;
    Storage::disk('local')->put($pfad, '%PDF-1.4 '.$dateiname);

    return Angebot::query()->create([
        'bestellung_id' => $bestellung->id,
        'user_id' => $user->id,
        'typ' => 'angebot',
        'lieferantenname' => 'Dach GmbH',
        'nummer' => $dateiname,
        'pdf_path' => $pfad,
    ]);
}
