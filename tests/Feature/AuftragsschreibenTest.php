<?php

declare(strict_types=1);

use App\Models\User;
use App\Services\IntranetLegacyService;
use Hwkdo\IntranetAppBestellungen\Enums\AuftragModus;
use Hwkdo\IntranetAppBestellungen\Enums\BestellungStatus;
use Hwkdo\IntranetAppBestellungen\Enums\BestellungTyp;
use Hwkdo\IntranetAppBestellungen\Exceptions\WorkflowException;
use Hwkdo\IntranetAppBestellungen\Livewire\Apps\Bestellungen\Detail;
use Hwkdo\IntranetAppBestellungen\Models\Angebot;
use Hwkdo\IntranetAppBestellungen\Models\Auftragszeile;
use Hwkdo\IntranetAppBestellungen\Models\Bestellung;
use Hwkdo\IntranetAppBestellungen\Models\LieferantCache;
use Hwkdo\IntranetAppBestellungen\Services\BestellungWorkflow;
use Hwkdo\IntranetAppBestellungen\Services\Pdf\BestellscheinPdfService;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;

beforeEach(function (): void {
    $legacyMock = Mockery::mock(IntranetLegacyService::class);
    $legacyMock->shouldReceive('getMaxSequenceFromLegacy')->andReturn(0);
    app()->instance(IntranetLegacyService::class, $legacyMock);
});

it('erzeugt das Auftragsschreiben aus den vorhandenen Daten', function (): void {
    $anforderer = User::factory()->create([
        'vorname' => 'Erika',
        'nachname' => 'Muster',
    ]);
    $besteller = User::factory()->create([
        'vorname' => 'Björn',
        'nachname' => 'Brauckmann',
        'telefon' => '0231 5493-237',
        'fax' => '0231 5493-95237',
        'email' => 'bjoern.brauckmann@hwk-do.de',
        'title' => 'Sachbearbeitung',
    ]);

    $bestellung = auftragMitZeile($anforderer, [
        'besteller_id' => $besteller->id,
        'bestellt_at' => '2025-11-27 09:00:00',
        'auftrag_modus' => AuftragModus::Vorlage,
        'ausfuehrungsfrist' => '2026-04-15',
        'auftragnehmer_anrede' => 'herr',
        'auftragnehmer_ansprechpartner' => 'Kai Meier',
        'auftragnehmer_email' => 'kai.meier@example.test',
        'betreff' => 'Dachsanierung Werkstatt',
        'lieferantenname' => 'Dach GmbH',
        'lieferanschrift' => [
            'strasse' => 'Industriestraße',
            'hausnummer' => '5',
            'plz' => '44139',
            'ort' => 'Dortmund',
        ],
    ]);

    $html = app(BestellscheinPdfService::class)->html($bestellung->fresh(), BestellungTyp::Auftragsvergabe);

    expect($html)
        ->toContain('Dach GmbH')
        ->toContain('Industriestraße 5')
        ->toContain('44139 Dortmund')
        ->toContain('Zu Hd. Herrn Kai Meier')
        ->toContain('E-Mail: kai.meier@example.test')
        ->toContain('Ihr Angebot mit der Nr. A-100 vom 12.10.2026')
        ->toContain('Dachsanierung Werkstatt')
        ->toContain('Sehr geehrter Herr Kai Meier,')
        ->toContain('Ihrem oben genannten Angebot')
        ->toContain('1.190,00 EURO')
        ->toContain('15.04.2026')
        ->toContain('27. November 2025')
        ->toContain('Unser Zeichen: BB')
        ->toContain('Björn Brauckmann')
        ->toContain('0231 5493-237')
        ->toContain('0231 5493-95237')
        ->toContain('bjoern.brauckmann@hwk-do.de')
        ->toContain('Sachbearbeitung')
        ->toContain('Anlage:')
        ->toContain('Angebot')
        ->not->toContain('Gesamtpreis (netto)')
        ->not->toContain('Erika Muster');
});

it('fasst mehrere Angebote in einer Bezugszeile zusammen', function (): void {
    $user = User::factory()->create([
        'vorname' => 'Max',
        'nachname' => 'Mustermann',
    ]);

    $bestellung = auftragMitZeile($user, [
        'auftrag_modus' => AuftragModus::Vorlage,
        'ausfuehrungsfrist' => '2026-05-01',
        'betreff' => 'Fenster',
    ]);

    Auftragszeile::factory()->create([
        'bestellung_id' => $bestellung->id,
        'nr' => 2,
        'angebotsdatum' => '2026-11-02',
        'angebotsnummer' => 'B-200',
        'betrag_netto' => 400,
        'betrag_brutto' => 500,
    ]);

    $html = app(BestellscheinPdfService::class)->html($bestellung->fresh(), BestellungTyp::Auftragsvergabe);

    expect($html)
        ->toContain('Ihre Angebote mit der Nr. A-100 vom 12.10.2026, Nr. B-200 vom 02.11.2026')
        ->toContain('Ihren oben genannten Angeboten')
        ->toContain('1.690,00 EURO')
        ->toContain('Angebote');
});

it('fällt auf die allgemeine Anrede und den Anforderer zurück', function (): void {
    $anforderer = User::factory()->create([
        'vorname' => 'Ada',
        'nachname' => 'Lovelace',
        'title' => 'Projektleitung',
    ]);

    $bestellung = auftragMitZeile($anforderer, [
        'besteller_id' => null,
        'auftrag_modus' => AuftragModus::Vorlage,
        'auftragnehmer_anrede' => null,
        'auftragnehmer_ansprechpartner' => null,
        'auftragnehmer_email' => null,
    ]);

    $html = app(BestellscheinPdfService::class)->html($bestellung->fresh(), BestellungTyp::Auftragsvergabe);

    expect($html)
        ->toContain('Sehr geehrte Damen und Herren,')
        ->toContain('Ada Lovelace')
        ->toContain('Projektleitung')
        ->toContain('Unser Zeichen: AL')
        ->not->toContain('Zu Hd.')
        ->not->toContain('E-Mail:');
});

it('lässt den Tabellen-Auftragsschein unverändert, wenn keine Vorlage gewählt ist', function (): void {
    $user = User::factory()->create();
    $bestellung = auftragMitZeile($user, [
        'auftrag_modus' => AuftragModus::Tabelle,
    ]);

    $html = app(BestellscheinPdfService::class)->html($bestellung->fresh(), BestellungTyp::Auftragsvergabe);

    expect($html)
        ->toContain('Gesamtpreis (netto)')
        ->toContain('Ihr Angebot vom 12.10.2026 Nr. A-100')
        ->not->toContain('Ausführungsfrist');
});

it('ignoriert hochgeladene Angebote, die nicht als Auftragszeile gewählt wurden', function (): void {
    $user = User::factory()->create();
    LieferantCache::query()->create([
        'lieferantennummer' => '7018260',
        'lieferantenname' => 'Tradeo GmbH',
        'strasse' => 'Berg',
        'hausnummer' => '24',
        'plz' => '84104',
        'ort' => 'Rudelzhausen',
    ]);

    $bestellung = Bestellung::factory()->auftragsvergabe()->create([
        'status' => BestellungStatus::Entwurf,
        'user_id' => $user->id,
        'auftrag_modus' => AuftragModus::Vorlage,
        'betreff' => 'Festplatten',
        'lieferantennummer' => null,
        'lieferantenname' => null,
        'lieferanschrift' => null,
        'ausfuehrungsfrist' => '2026-04-15',
    ]);

    $gewaehlt = Angebot::query()->create([
        'bestellung_id' => $bestellung->id,
        'user_id' => $user->id,
        'typ' => 'angebot',
        'lieferantennummer' => '7018260',
        'lieferantenname' => 'Tradeo GmbH',
        'nummer' => 'AN-900194',
        'angebotsdatum' => '2023-07-12',
        'betrag_netto' => 1130.08,
        'betrag' => 1344.79,
        'extraction_payload' => [
            'contact' => [
                'person' => 'Daniele Altermann',
                'salutation' => null,
                'email' => 'mail@servershop24.de',
            ],
        ],
    ]);
    Angebot::query()->create([
        'bestellung_id' => $bestellung->id,
        'user_id' => $user->id,
        'typ' => 'angebot',
        'lieferantenname' => 'Andere GmbH',
        'nummer' => 'X-1',
        'angebotsdatum' => '2023-08-01',
        'betrag' => 99,
    ]);

    $ohneZeile = app(BestellscheinPdfService::class)->html($bestellung->fresh(), BestellungTyp::Auftragsvergabe);

    expect($ohneZeile)
        ->not->toContain('Tradeo GmbH')
        ->not->toContain('Andere GmbH')
        ->not->toContain('1.344,79 EURO');

    Auftragszeile::factory()->create([
        'bestellung_id' => $bestellung->id,
        'angebot_id' => $gewaehlt->id,
        'nr' => 1,
        'angebotsdatum' => '2023-07-12',
        'angebotsnummer' => 'AN-900194',
        'betrag_netto' => 1130.08,
        'betrag_brutto' => null,
        'freitext' => null,
    ]);

    $html = app(BestellscheinPdfService::class)->html($bestellung->fresh(), BestellungTyp::Auftragsvergabe);

    expect($html)
        ->toContain('Tradeo GmbH')
        ->toContain('Berg 24')
        ->toContain('84104 Rudelzhausen')
        ->toContain('Ihr Angebot mit der Nr. AN-900194 vom 12.07.2023')
        ->toContain('1.344,79 EURO')
        ->toContain('Zu Hd. Daniele Altermann')
        ->not->toContain('Andere GmbH');
});

it('hält die Vorschau zurück, bis ein Angebot gewählt wurde', function (): void {
    $user = User::factory()->create();
    $bestellung = Bestellung::factory()->auftragsvergabe()->create([
        'status' => BestellungStatus::Entwurf,
        'user_id' => $user->id,
        'auftrag_modus' => AuftragModus::Vorlage,
        'betreff' => 'Festplatten',
    ]);

    Angebot::query()->create([
        'bestellung_id' => $bestellung->id,
        'user_id' => $user->id,
        'typ' => 'angebot',
        'lieferantenname' => 'Tradeo GmbH',
        'nummer' => 'AN-900194',
    ]);

    Livewire::actingAs($user)
        ->test(Detail::class, ['bestellung' => $bestellung])
        ->assertSee('Wählen Sie zuerst das Angebot, das beauftragt werden soll.');
});

it('übernimmt die Anschrift aus dem Lieferanten-Cache', function (): void {
    $user = User::factory()->create();
    LieferantCache::query()->create([
        'lieferantennummer' => '4711',
        'lieferantenname' => 'Cache GmbH',
        'strasse' => 'Ardeyweg',
        'hausnummer' => '9',
        'plz' => '44139',
        'ort' => 'Dortmund',
    ]);

    $bestellung = auftragMitZeile($user, [
        'auftrag_modus' => AuftragModus::Vorlage,
        'lieferantennummer' => '4711',
        'lieferantenname' => 'Cache GmbH',
        'lieferanschrift' => null,
    ]);

    $html = app(BestellscheinPdfService::class)->html($bestellung->fresh(), BestellungTyp::Auftragsvergabe);

    expect($html)->toContain('Ardeyweg 9')->toContain('44139 Dortmund');
});

it('übernimmt den Kontakt aus dem Angebot und sperrt das Einreichen ohne Frist oder Brutto', function (): void {
    $user = User::factory()->create();
    $bestellung = auftragMitZeile($user, [
        'status' => BestellungStatus::Entwurf,
        'betreff' => 'Dachsanierung',
        'lieferantenname' => 'Dach GmbH',
        'lieferanschrift' => [
            'strasse' => 'Industriestraße',
            'hausnummer' => '5',
            'plz' => '44139',
            'ort' => 'Dortmund',
        ],
    ], brutto: null);

    $zeile = $bestellung->auftragszeilen()->first();
    $angebot = Angebot::query()->create([
        'bestellung_id' => $bestellung->id,
        'user_id' => $user->id,
        'typ' => 'angebot',
        'lieferantenname' => 'Dach GmbH',
        'nummer' => 'A-100',
        'extraction_payload' => [
            'contact' => [
                'person' => 'Kai Meier',
                'salutation' => 'herr',
                'email' => 'kai.meier@example.test',
            ],
        ],
    ]);
    $zeile->update(['angebot_id' => $angebot->id]);

    Livewire::actingAs($user)
        ->test(Detail::class, ['bestellung' => $bestellung->fresh()])
        ->set('auftragModus', AuftragModus::Vorlage->value)
        ->assertSet('auftragnehmerAnrede', 'herr')
        ->assertSet('auftragnehmerAnsprechpartner', 'Kai Meier')
        ->assertSet('auftragnehmerEmail', 'kai.meier@example.test')
        ->assertSee('aus Angebot erkannt')
        ->assertSee('Für das Schreiben fehlen noch: Ausführungsfrist, Bruttobetrag')
        ->set('ausfuehrungsfrist', '2026-04-15')
        ->call('auftragsschreibenSpeichern')
        ->assertHasNoErrors()
        ->assertSee('Bruttobetrag');

    $bestellung->refresh();

    expect($bestellung->auftrag_modus)->toBe(AuftragModus::Vorlage)
        ->and($bestellung->ausfuehrungsfrist?->toDateString())->toBe('2026-04-15')
        ->and($bestellung->auftragnehmer_ansprechpartner)->toBe('Kai Meier');

    $gesperrt = Livewire::actingAs($user)->test(Detail::class, ['bestellung' => $bestellung->fresh()]);
    expect($gesperrt->instance()->kannEinreichen())->toBeFalse();

    $zeile->refresh()->update(['betrag_brutto' => 1190]);

    $nochOhnePdf = Livewire::actingAs($user)->test(Detail::class, ['bestellung' => $bestellung->fresh()]);
    expect($nochOhnePdf->instance()->kannEinreichen())->toBeFalse()
        ->and($nochOhnePdf->instance()->einreichenGesperrtHinweis())->toBe('Jede Auftragszeile braucht das hinterlegte Angebot als PDF.');

    Storage::fake('local');
    $pdfPfad = 'bestellungen/angebote/'.$angebot->id.'.pdf';
    Storage::disk('local')->put($pdfPfad, '%PDF-1.4');
    $angebot->update(['pdf_path' => $pdfPfad]);

    $offen = Livewire::actingAs($user)->test(Detail::class, ['bestellung' => $bestellung->fresh()]);
    expect($offen->instance()->kannEinreichen())->toBeTrue();
});

it('überschreibt einen bereits gepflegten Ansprechpartner nicht', function (): void {
    $user = User::factory()->create();
    $bestellung = auftragMitZeile($user, [
        'status' => BestellungStatus::Entwurf,
        'auftragnehmer_ansprechpartner' => 'Fest Verdacht',
    ]);

    $angebot = Angebot::query()->create([
        'bestellung_id' => $bestellung->id,
        'user_id' => $user->id,
        'typ' => 'angebot',
        'extraction_payload' => [
            'contact' => [
                'person' => 'Kai Meier',
                'salutation' => 'herr',
                'email' => 'kai.meier@example.test',
            ],
        ],
    ]);
    $bestellung->auftragszeilen()->first()?->update(['angebot_id' => $angebot->id]);

    Livewire::actingAs($user)
        ->test(Detail::class, ['bestellung' => $bestellung])
        ->set('auftragModus', AuftragModus::Vorlage->value)
        ->assertSet('auftragnehmerAnsprechpartner', 'Fest Verdacht')
        ->assertSet('auftragnehmerAnrede', null);
});

it('verweigert das Einreichen, solange die Vorlage unvollständig ist', function (): void {
    $user = User::factory()->create();
    $bestellung = auftragMitZeile($user, [
        'status' => BestellungStatus::Entwurf,
        'auftrag_modus' => AuftragModus::Vorlage,
        'ausfuehrungsfrist' => null,
        'betreff' => 'Dachsanierung',
        'lieferantenname' => 'Dach GmbH',
        'lieferanschrift' => [
            'strasse' => 'Industriestraße',
            'hausnummer' => '5',
            'plz' => '44139',
            'ort' => 'Dortmund',
        ],
    ]);

    expect(fn () => app(BestellungWorkflow::class)->einreichen($bestellung->fresh(), $user))
        ->toThrow(WorkflowException::class, 'Ausführungsfrist');
});

it('übernimmt den Vorlagenmodus beim Wiederholen, aber nicht die Frist', function (): void {
    $user = User::factory()->create();
    $bestellung = Bestellung::factory()->auftragsvergabe()->bestellt()->create([
        'user_id' => $user->id,
        'auftrag_modus' => AuftragModus::Vorlage,
        'ausfuehrungsfrist' => '2026-04-15',
        'auftragnehmer_anrede' => 'frau',
        'auftragnehmer_ansprechpartner' => 'Lina Vogt',
        'auftragnehmer_email' => 'lina@example.test',
    ]);

    Livewire::actingAs($user)
        ->test(Detail::class, ['bestellung' => $bestellung])
        ->call('wiederholen')
        ->assertRedirect();

    $kopie = Bestellung::query()->where('wiederholt_von_id', $bestellung->id)->first();

    expect($kopie)->not->toBeNull()
        ->and($kopie->auftrag_modus)->toBe(AuftragModus::Vorlage)
        ->and($kopie->ausfuehrungsfrist)->toBeNull()
        ->and($kopie->auftragnehmer_anrede)->toBe('frau')
        ->and($kopie->auftragnehmer_ansprechpartner)->toBe('Lina Vogt')
        ->and($kopie->auftragnehmer_email)->toBe('lina@example.test');
});

it('speichert die Ausführungsfrist beim Setzen und mit der Auftragszeile', function (): void {
    $user = User::factory()->create();
    $bestellung = auftragMitZeile($user, [
        'status' => BestellungStatus::Entwurf,
        'auftrag_modus' => AuftragModus::Vorlage,
        'ausfuehrungsfrist' => null,
    ]);

    Storage::fake('local');
    $pdfPfad = 'bestellungen/angebote/frist-'.$bestellung->id.'.pdf';
    Storage::disk('local')->put($pdfPfad, '%PDF-1.4');
    $angebot = Angebot::query()->create([
        'bestellung_id' => $bestellung->id,
        'user_id' => $user->id,
        'typ' => 'angebot',
        'lieferantenname' => 'Dach GmbH',
        'nummer' => 'A-100',
        'pdf_path' => $pdfPfad,
    ]);
    $bestellung->auftragszeilen()->first()?->update(['angebot_id' => $angebot->id]);

    $component = Livewire::actingAs($user)
        ->test(Detail::class, ['bestellung' => $bestellung])
        ->set('ausfuehrungsfrist', '2026-04-15')
        ->assertSet('ausfuehrungsfrist', '2026-04-15');

    expect($bestellung->fresh()->ausfuehrungsfrist?->toDateString())->toBe('2026-04-15')
        ->and($component->instance()->kannEinreichen())->toBeTrue();

    $component->call('auftragszeileSpeichern', 0)->assertHasNoErrors();

    expect($bestellung->fresh()->ausfuehrungsfrist?->toDateString())->toBe('2026-04-15');
});

it('zeigt den Ansprechpartner aus dem gewählten Angebot im Formular', function (): void {
    $user = User::factory()->create();
    $bestellung = auftragMitZeile($user, [
        'status' => BestellungStatus::Entwurf,
        'auftrag_modus' => AuftragModus::Vorlage,
        'auftragnehmer_anrede' => null,
        'auftragnehmer_ansprechpartner' => null,
        'auftragnehmer_email' => null,
    ]);
    $angebot = Angebot::query()->create([
        'bestellung_id' => $bestellung->id,
        'user_id' => $user->id,
        'typ' => 'angebot',
        'extraction_payload' => [
            'contact' => [
                'person' => 'Daniele Altermann',
                'salutation' => null,
                'email' => 'mail@servershop24.de',
            ],
        ],
    ]);
    $bestellung->auftragszeilen()->first()?->update(['angebot_id' => $angebot->id]);

    Livewire::actingAs($user)
        ->test(Detail::class, ['bestellung' => $bestellung->fresh()])
        ->assertSet('auftragnehmerAnsprechpartner', 'Daniele Altermann')
        ->assertSet('auftragnehmerEmail', 'mail@servershop24.de')
        ->assertSee('aus Angebot erkannt');

    expect($bestellung->fresh()->auftragnehmer_ansprechpartner)->toBe('Daniele Altermann');
});

it('lässt einen geleerten Ansprechpartner leer', function (): void {
    $user = User::factory()->create();
    $bestellung = auftragMitZeile($user, [
        'status' => BestellungStatus::Entwurf,
        'auftrag_modus' => AuftragModus::Vorlage,
        'auftragnehmer_ansprechpartner' => '',
        'auftragnehmer_email' => '',
    ]);
    $angebot = Angebot::query()->create([
        'bestellung_id' => $bestellung->id,
        'user_id' => $user->id,
        'typ' => 'angebot',
        'extraction_payload' => [
            'contact' => [
                'person' => 'Daniele Altermann',
                'salutation' => null,
                'email' => 'mail@servershop24.de',
            ],
        ],
    ]);
    $bestellung->auftragszeilen()->first()?->update(['angebot_id' => $angebot->id]);

    Livewire::actingAs($user)
        ->test(Detail::class, ['bestellung' => $bestellung->fresh()])
        ->assertSet('auftragnehmerAnsprechpartner', '');

    expect($bestellung->fresh()->auftragnehmer_ansprechpartner)->toBe('');
});

it('zeigt Speichern erst nach einer Änderung an Zeile oder Ansprechpartner', function (): void {
    $user = User::factory()->create();
    $bestellung = auftragMitZeile($user, [
        'status' => BestellungStatus::Entwurf,
        'auftrag_modus' => AuftragModus::Vorlage,
        'auftragnehmer_ansprechpartner' => 'Kai Meier',
    ]);

    Livewire::actingAs($user)
        ->test(Detail::class, ['bestellung' => $bestellung])
        ->assertDontSee('Zeile speichern')
        ->assertDontSee('Auftragsschreiben speichern')
        ->assertSee('Gespeichert')
        ->set('auftragszeilenDraft.0.angebotsnummer', 'NEU-1')
        ->assertSee('Änderung speichern')
        ->call('auftragszeileSpeichern', 0)
        ->assertHasNoErrors()
        ->assertDontSee('Änderung speichern');

    expect($bestellung->fresh()->auftragszeilen()->first()?->angebotsnummer)->toBe('NEU-1');
});

it('setzt geänderte Zeile und Ansprechpartner auf den gespeicherten Stand zurück', function (): void {
    $user = User::factory()->create();
    $bestellung = auftragMitZeile($user, [
        'status' => BestellungStatus::Entwurf,
        'auftrag_modus' => AuftragModus::Vorlage,
        'auftragnehmer_ansprechpartner' => 'Kai Meier',
    ]);
    $nummer = $bestellung->auftragszeilen()->first()?->angebotsnummer;

    Livewire::actingAs($user)
        ->test(Detail::class, ['bestellung' => $bestellung])
        ->set('auftragszeilenDraft.0.angebotsnummer', 'NEU-1')
        ->set('auftragnehmerAnsprechpartner', 'Jemand anders')
        ->assertSee('Änderungen rückgängig machen')
        ->call('auftragszeileAenderungenVerwerfen', 0)
        ->assertSet('auftragszeilenDraft.0.angebotsnummer', $nummer)
        ->call('auftragnehmerAenderungenVerwerfen')
        ->assertSet('auftragnehmerAnsprechpartner', 'Kai Meier')
        ->assertDontSee('Änderung speichern');

    expect($bestellung->fresh()->auftragnehmer_ansprechpartner)->toBe('Kai Meier')
        ->and($bestellung->auftragszeilen()->first()?->angebotsnummer)->toBe($nummer);
});

function auftragMitZeile(User $user, array $attribute = [], ?float $brutto = 1190): Bestellung
{
    $bestellung = Bestellung::factory()->auftragsvergabe()->create(array_merge([
        'status' => BestellungStatus::Entwurf,
        'user_id' => $user->id,
        'betreff' => 'Dachsanierung',
        'lieferantenname' => 'Dach GmbH',
        'lieferanschrift' => [
            'strasse' => 'Industriestraße',
            'hausnummer' => '5',
            'plz' => '44139',
            'ort' => 'Dortmund',
        ],
    ], $attribute));

    Auftragszeile::factory()->create([
        'bestellung_id' => $bestellung->id,
        'nr' => 1,
        'angebotsdatum' => '2026-10-12',
        'angebotsnummer' => 'A-100',
        'betrag_netto' => 1000,
        'betrag_brutto' => $brutto,
        'freitext' => null,
    ]);

    return $bestellung;
}
