<?php

declare(strict_types=1);

use App\Models\User;
use App\Services\IntranetLegacyService;
use Hwkdo\IntranetAppBestellungen\Data\AppSettings;
use Hwkdo\IntranetAppBestellungen\Enums\AuftragModus;
use Hwkdo\IntranetAppBestellungen\Enums\BestellungStatus;
use Hwkdo\IntranetAppBestellungen\Enums\BestellungTyp;
use Hwkdo\IntranetAppBestellungen\Enums\UnterschriftArt;
use Hwkdo\IntranetAppBestellungen\Exceptions\WorkflowException;
use Hwkdo\IntranetAppBestellungen\Livewire\Apps\Bestellungen\Admin\AuftragsvorlagenEditor;
use Hwkdo\IntranetAppBestellungen\Livewire\Apps\Bestellungen\Detail;
use Hwkdo\IntranetAppBestellungen\Models\Angebot;
use Hwkdo\IntranetAppBestellungen\Models\Auftragsvorlage;
use Hwkdo\IntranetAppBestellungen\Models\Auftragszeile;
use Hwkdo\IntranetAppBestellungen\Models\Bestellung;
use Hwkdo\IntranetAppBestellungen\Models\IntranetAppBestellungenSettings;
use Hwkdo\IntranetAppBestellungen\Services\BestellungWorkflow;
use Hwkdo\IntranetAppBestellungen\Services\Pdf\BestellscheinPdfService;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;

beforeEach(function (): void {
    $legacy = Mockery::mock(IntranetLegacyService::class);
    $legacy->shouldReceive('getMaxSequenceFromLegacy')->andReturn(0);
    app()->instance(IntranetLegacyService::class, $legacy);

    Role::findOrCreate('Benutzer', 'web');

    IntranetAppBestellungenSettings::create([
        'version' => 1,
        'settings' => AppSettings::from([
            'autoPushBeiBestellt' => false,
            'freigabeStufen' => [[
                'bezeichnung' => 'Standard',
                'bisBetrag' => null,
                'berechtigteRollen' => ['Benutzer'],
                'freigabe1Regeln' => [[
                    'typ' => 'default',
                    'keinFreigeber' => true,
                    'quelleTyp' => 'single',
                    'quelle' => 'vorgesetzter',
                    'excludeAttribute' => [],
                ]],
            ]],
            'angebotsRegeln' => [
                ['abBetrag' => 0, 'mindestAngebote' => 0, 'begruendungErlaubt' => true],
            ],
        ])->toArray(),
    ]);
});

it('legt die Startvorlagen Hausverwaltung und Allgemeine Dienstleistungen an', function (): void {
    $hausverwaltung = Auftragsvorlage::query()->where('schluessel', 'hausverwaltung')->first();
    $dienste = Auftragsvorlage::query()->where('schluessel', 'allgemeine-dienstleistungen')->first();

    expect($hausverwaltung)->not->toBeNull()
        ->and($hausverwaltung->anrede_automatisch)->toBeTrue()
        ->and($hausverwaltung->inhalt)->toContain('{frist}')
        ->and($dienste)->not->toBeNull()
        ->and($dienste->anrede_automatisch)->toBeFalse()
        ->and($dienste->inhalt)->toBe('{freitext}');
});

it('übernimmt den Freitext in den Auftragsschein und sperrt das Einreichen ohne Brieftext', function (): void {
    $user = User::factory()->create();
    $user->assignRole('Benutzer');
    $bestellung = vorlagenBestellung($user, 'allgemeine-dienstleistungen');

    expect(fn () => app(BestellungWorkflow::class)->einreichen($bestellung->fresh(), $user))
        ->toThrow(WorkflowException::class, 'Brieftext');

    $bestellung->forceFill([
        'vorlage_werte' => ['freitext' => 'hier mein Text zur Beauftragung.'],
    ])->save();

    $html = app(BestellscheinPdfService::class)->html($bestellung->fresh(), BestellungTyp::Auftragsvergabe);

    expect($html)
        ->toContain('hier mein Text zur Beauftragung.')
        ->toContain('Bezug:')
        ->toContain('Mit freundlichen Grüßen')
        ->toContain('Anlage:')
        ->not->toContain('Sehr geehrte Damen und Herren')
        ->not->toContain('Sie erhalten hiermit den Auftrag');
});

it('friert den Brieftext beim Bestellen ein', function (): void {
    $user = User::factory()->create();
    $user->assignRole('Benutzer');
    $bestellung = vorlagenBestellung($user, 'hausverwaltung', BestellungStatus::Freigegeben);
    $bestellung->forceFill([
        'unterzeichner_id' => $user->id,
        'unterschrift_art' => UnterschriftArt::Digital,
        'unterschrieben_at' => now(),
    ])->save();

    app(BestellungWorkflow::class)->bestellen($bestellung->fresh(), $user);

    $bestellung->refresh();
    expect($bestellung->auftragsschreiben_inhalt)->toContain('Sie erhalten hiermit den Auftrag');

    $bestellung->auftragsvorlage?->update(['inhalt' => 'NEUER TEXT {betreff}']);

    $html = app(BestellscheinPdfService::class)->html($bestellung->fresh(), BestellungTyp::Auftragsvergabe);

    expect($html)
        ->toContain('Sie erhalten hiermit den Auftrag')
        ->not->toContain('NEUER TEXT');

    Livewire::actingAs($user)
        ->test(Detail::class, ['bestellung' => $bestellung])
        ->call('wiederholen');

    $kopie = Bestellung::query()->where('wiederholt_von_id', $bestellung->id)->first();

    expect($kopie)->not->toBeNull()
        ->and($kopie->auftragsschreiben_inhalt)->toBeNull()
        ->and($kopie->auftragsvorlage_id)->toBe($bestellung->auftragsvorlage_id)
        ->and($kopie->ausfuehrungsfrist)->toBeNull();
});

it('verwaltet Auftragsvorlagen und deaktiviert verwendete Vorlagen statt sie zu löschen', function (): void {
    $user = User::factory()->create();

    Livewire::actingAs($user)
        ->test(AuftragsvorlagenEditor::class)
        ->call('neu')
        ->set('name', 'Sonderauftrag')
        ->set('schluessel', 'sonderauftrag')
        ->set('inhalt', 'Auftrag zu {betreff}.')
        ->set('variablen', [])
        ->set('anredeAutomatisch', false)
        ->call('speichern')
        ->assertHasNoErrors();

    $vorlage = Auftragsvorlage::query()->where('schluessel', 'sonderauftrag')->first();

    expect($vorlage)->not->toBeNull()
        ->and($vorlage->anrede_automatisch)->toBeFalse();

    Livewire::actingAs($user)
        ->test(AuftragsvorlagenEditor::class)
        ->call('edit', $vorlage->id)
        ->set('name', 'Sonderauftrag geändert')
        ->set('inhalt', 'Text mit {unbekannt}')
        ->call('speichern')
        ->assertHasErrors('inhalt');

    expect($vorlage->fresh()->name)->toBe('Sonderauftrag');

    Livewire::actingAs($user)
        ->test(AuftragsvorlagenEditor::class)
        ->call('edit', $vorlage->id)
        ->set('name', 'Sonderauftrag geändert')
        ->call('speichern')
        ->assertHasNoErrors();

    expect($vorlage->fresh()->name)->toBe('Sonderauftrag geändert');

    $bestellung = Bestellung::factory()->auftragsvergabe()->create([
        'user_id' => $user->id,
        'auftrag_modus' => AuftragModus::Vorlage,
        'auftragsvorlage_id' => $vorlage->id,
    ]);

    Livewire::actingAs($user)
        ->test(AuftragsvorlagenEditor::class)
        ->call('loeschen', $vorlage->id);

    expect($vorlage->fresh()->aktiv)->toBeFalse()
        ->and(Auftragsvorlage::query()->whereKey($vorlage->id)->exists())->toBeTrue()
        ->and($bestellung->fresh()->auftragsvorlage_id)->toBe($vorlage->id);

    $frei = Auftragsvorlage::factory()->create(['schluessel' => 'ungenutzt']);

    Livewire::actingAs($user)
        ->test(AuftragsvorlagenEditor::class)
        ->call('loeschen', $frei->id);

    expect(Auftragsvorlage::query()->whereKey($frei->id)->exists())->toBeFalse();
});

function vorlagenBestellung(User $user, string $schluessel, BestellungStatus $status = BestellungStatus::Entwurf): Bestellung
{
    Storage::fake('local');

    $vorlage = Auftragsvorlage::query()->where('schluessel', $schluessel)->firstOrFail();

    $bestellung = Bestellung::factory()->auftragsvergabe()->create([
        'status' => $status,
        'user_id' => $user->id,
        'auftrag_modus' => AuftragModus::Vorlage,
        'auftragsvorlage_id' => $vorlage->id,
        'betreff' => 'Dachsanierung',
        'lieferantenname' => 'Dach GmbH',
        'lieferanschrift' => [
            'strasse' => 'Industriestraße',
            'hausnummer' => '5',
            'plz' => '44139',
            'ort' => 'Dortmund',
        ],
        'ausfuehrungsfrist' => '2026-04-15',
        'gesamtbetrag' => 1190,
    ]);

    $pfad = 'bestellungen/angebote/'.$bestellung->id.'/angebot.pdf';
    Storage::disk('local')->put($pfad, '%PDF-1.4');

    $angebot = Angebot::query()->create([
        'bestellung_id' => $bestellung->id,
        'user_id' => $user->id,
        'typ' => 'angebot',
        'lieferantenname' => 'Dach GmbH',
        'nummer' => 'A-100',
        'pdf_path' => $pfad,
        'betrag_netto' => 1000,
        'betrag' => 1190,
    ]);

    Auftragszeile::factory()->create([
        'bestellung_id' => $bestellung->id,
        'angebot_id' => $angebot->id,
        'nr' => 1,
        'angebotsdatum' => '2026-10-12',
        'angebotsnummer' => 'A-100',
        'betrag_netto' => 1000,
        'betrag_brutto' => 1190,
    ]);

    return $bestellung;
}
