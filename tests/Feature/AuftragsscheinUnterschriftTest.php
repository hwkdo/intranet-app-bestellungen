<?php

declare(strict_types=1);

use App\Models\User;
use App\Services\IntranetLegacyService;
use App\Services\PdfService;
use Hwkdo\IntranetAppBase\Services\UserSignatureStore;
use Hwkdo\IntranetAppBestellungen\Data\AppSettings;
use Hwkdo\IntranetAppBestellungen\Enums\BestellungStatus;
use Hwkdo\IntranetAppBestellungen\Enums\BestellungTyp;
use Hwkdo\IntranetAppBestellungen\Enums\UnterschriftArt;
use Hwkdo\IntranetAppBestellungen\Exceptions\WorkflowException;
use Hwkdo\IntranetAppBestellungen\Livewire\Apps\Bestellungen\Detail;
use Hwkdo\IntranetAppBestellungen\Models\Angebot;
use Hwkdo\IntranetAppBestellungen\Models\Auftragszeile;
use Hwkdo\IntranetAppBestellungen\Models\Bestellung;
use Hwkdo\IntranetAppBestellungen\Services\AngebotsregelService;
use Hwkdo\IntranetAppBestellungen\Services\AuftragsscheinUnterschriftService;
use Hwkdo\IntranetAppBestellungen\Services\BestellungWorkflow;
use Hwkdo\IntranetAppBestellungen\Services\D3\AngebotD3Service;
use Hwkdo\IntranetAppBestellungen\Services\D3\BestellscheinD3Service;
use Hwkdo\IntranetAppBestellungen\Services\D3\KassenbonRechnungD3Service;
use Hwkdo\IntranetAppBestellungen\Services\Pdf\AuftragsschreibenDaten;
use Hwkdo\IntranetAppBestellungen\Services\Pdf\BestellscheinPdfService;
use Hwkdo\IntranetAppBestellungen\Services\WertgrenzenService;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;

beforeEach(function (): void {
    Storage::fake('local');

    $legacy = Mockery::mock(IntranetLegacyService::class);
    $legacy->shouldReceive('getMaxSequenceFromLegacy')->andReturn(0);
    app()->instance(IntranetLegacyService::class, $legacy);

    Role::findOrCreate('Benutzer', 'web');
});

it('setzt bei einstufiger Freigabe den Freigeber als Unterzeichner und unterschreibt digital', function (): void {
    $anforderer = benutzer();
    $freigeber = benutzer();
    app(UserSignatureStore::class)->register($freigeber, $freigeber, auftragSignaturPng(), 'roh');

    $bestellung = Bestellung::factory()->auftragsvergabe()->create([
        'status' => BestellungStatus::ZurFreigabe,
        'user_id' => $anforderer->id,
        'gesamtbetrag' => 100,
        'auftrag_modus' => 'tabelle',
    ]);

    unterschriftWorkflow(einstufig())->freigeben($bestellung, $freigeber);

    $bestellung->refresh();

    expect($bestellung->unterzeichner_id)->toBe($freigeber->id)
        ->and($bestellung->unterschrift_art)->toBe(UnterschriftArt::Digital)
        ->and(Storage::disk('local')->exists($bestellung->unterschrift_bild_pfad))->toBeTrue();
});

it('unterschreibt bei zwei Stufen erst mit dem zweiten Freigeber', function (): void {
    $erster = benutzer();
    $zweiter = benutzer();
    $bestellung = Bestellung::factory()->auftragsvergabe()->create([
        'status' => BestellungStatus::ZurFreigabe,
        'user_id' => $erster->id,
        'gesamtbetrag' => 5000,
        'auftrag_modus' => 'tabelle',
    ]);

    $workflow = unterschriftWorkflow(zweistufig());
    $workflow->freigeben($bestellung, $erster);

    expect($bestellung->fresh()->status)->toBe(BestellungStatus::ZurZweitenFreigabe)
        ->and($bestellung->fresh()->unterzeichner_id)->toBeNull();

    $workflow->freigeben($bestellung->fresh(), $zweiter);

    expect($bestellung->fresh()->status)->toBe(BestellungStatus::Freigegeben)
        ->and($bestellung->fresh()->unterzeichner_id)->toBe($zweiter->id)
        ->and($bestellung->fresh()->unterschrift_art)->toBeNull();
});

it('setzt bei der Selbstfreigabe den Einreicher als Unterzeichner', function (): void {
    $anforderer = benutzer();
    $bestellung = Bestellung::factory()->auftragsvergabe()->create([
        'status' => BestellungStatus::Entwurf,
        'user_id' => $anforderer->id,
        'lieferantenname' => 'Dach GmbH',
        'gesamtbetrag' => 100,
        'auftrag_modus' => 'tabelle',
    ]);
    unterschriftZeile($bestellung, $anforderer);

    unterschriftWorkflow(einstufig(keinFreigeber: true))->einreichen($bestellung, $anforderer);

    expect($bestellung->fresh()->status)->toBe(BestellungStatus::Freigegeben)
        ->and($bestellung->fresh()->unterzeichner_id)->toBe($anforderer->id);
});

it('sperrt das Bestellen ohne Unterschrift', function (): void {
    $anforderer = benutzer();
    $freigeber = benutzer();
    $bestellung = Bestellung::factory()->auftragsvergabe()->create([
        'status' => BestellungStatus::ZurFreigabe,
        'user_id' => $anforderer->id,
        'lieferantenname' => 'Dach GmbH',
        'gesamtbetrag' => 100,
        'auftrag_modus' => 'tabelle',
    ]);
    unterschriftZeile($bestellung, $anforderer);

    $workflow = unterschriftWorkflow(einstufig());
    $workflow->freigeben($bestellung, $freigeber);

    expect(fn () => $workflow->bestellen($bestellung->fresh(), $anforderer))
        ->toThrow(WorkflowException::class, 'unterschrieben werden');
});

it('setzt die digitale Unterschrift und den Namen des Unterzeichners ins Auftragsschreiben', function (): void {
    $anforderer = User::factory()->create(['vorname' => 'Erika', 'nachname' => 'Muster']);
    $unterzeichner = User::factory()->create(['vorname' => 'Björn', 'nachname' => 'Brauckmann', 'title' => 'Abteilungsleitung']);
    $store = app(UserSignatureStore::class);
    $store->register($unterzeichner, $unterzeichner, auftragSignaturPng(), 'roh');

    $bestellung = Bestellung::factory()->auftragsvergabe()->create([
        'status' => BestellungStatus::ZurFreigabe,
        'user_id' => $anforderer->id,
        'besteller_id' => $anforderer->id,
        'gesamtbetrag' => 100,
        'auftrag_modus' => 'vorlage',
        'betreff' => 'Dachsanierung',
    ]);

    unterschriftWorkflow(einstufig())->freigeben($bestellung, $unterzeichner);
    $bestellung->refresh();

    $html = app(BestellscheinPdfService::class)->html($bestellung, BestellungTyp::Auftragsvergabe);

    expect($html)
        ->toContain('data:image/png;base64,')
        ->toContain('Björn Brauckmann')
        ->toContain('Abteilungsleitung');

    $pfad = $bestellung->unterschrift_bild_pfad;
    $original = $store->active($unterzeichner)?->image_path;
    $store->revoke($unterzeichner, $unterzeichner);
    if (is_string($original)) {
        Storage::disk('local')->delete($original);
    }

    $htmlNachWiderruf = app(BestellscheinPdfService::class)->html($bestellung->fresh(), BestellungTyp::Auftragsvergabe);

    expect(Storage::disk('local')->exists($pfad))->toBeTrue()
        ->and($htmlNachWiderruf)->toContain('data:image/png;base64,');
});

it('verwendet den Scan als Basis und hängt die Angebote an', function (): void {
    $user = benutzer();
    $bestellung = Bestellung::factory()->auftragsvergabe()->create([
        'status' => BestellungStatus::Freigegeben,
        'user_id' => $user->id,
        'unterzeichner_id' => $user->id,
        'unterschrift_art' => UnterschriftArt::Scan,
    ]);
    $scan = 'bestellungen/unterschriften/'.$bestellung->id.'/scan.pdf';
    Storage::disk('local')->put($scan, '%PDF-1.4 scan');
    $bestellung->forceFill(['unterschrift_scan_pfad' => $scan])->save();
    unterschriftZeile($bestellung, $user);

    $pdf = Mockery::mock(PdfService::class);
    $pdf->shouldNotReceive('saveFromHtml');
    $pdf->shouldReceive('mergePdfs')->once()->andReturnUsing(function (array $paths) use ($scan): string {
        expect($paths[0])->toBe(Storage::disk('local')->path($scan))
            ->and($paths)->toHaveCount(2);

        return '/tmp/merged-auftrag.pdf';
    });
    app()->instance(PdfService::class, $pdf);

    $pfad = app(BestellscheinPdfService::class)->buildFile($bestellung->fresh(), BestellungTyp::Auftragsvergabe);

    expect($pfad)->toBe('/tmp/merged-auftrag.pdf');
});

it('erlaubt das nachträgliche digitale Unterschreiben nur dem Unterzeichner', function (): void {
    $unterzeichner = benutzer();
    $fremd = benutzer();
    app(UserSignatureStore::class)->register($unterzeichner, $unterzeichner, auftragSignaturPng(), 'roh');

    $bestellung = Bestellung::factory()->auftragsvergabe()->create([
        'status' => BestellungStatus::Freigegeben,
        'user_id' => $fremd->id,
        'unterzeichner_id' => $unterzeichner->id,
        'auftrag_modus' => 'tabelle',
    ]);

    $service = app(AuftragsscheinUnterschriftService::class);

    expect(fn () => $service->digitalUnterschreiben($bestellung, $fremd))
        ->toThrow(WorkflowException::class, 'Nur der Unterzeichner');

    $service->digitalUnterschreiben($bestellung->fresh(), $unterzeichner);

    expect($bestellung->fresh()->unterschrift_art)->toBe(UnterschriftArt::Digital);
});

it('leert Unterzeichner und Unterschrift beim Wiederholen', function (): void {
    $user = benutzer();
    $bestellung = Bestellung::factory()->auftragsvergabe()->create([
        'status' => BestellungStatus::Freigegeben,
        'user_id' => $user->id,
        'unterzeichner_id' => $user->id,
        'unterschrift_art' => UnterschriftArt::Digital,
        'unterschrieben_at' => now(),
        'unterschrift_bild_pfad' => 'bestellungen/unterschriften/1/unterschrift.png',
    ]);

    Livewire::actingAs($user)
        ->test(Detail::class, ['bestellung' => $bestellung])
        ->call('wiederholen');

    $kopie = Bestellung::query()->where('wiederholt_von_id', $bestellung->id)->first();

    expect($kopie)->not->toBeNull()
        ->and($kopie->unterzeichner_id)->toBeNull()
        ->and($kopie->unterschrift_art)->toBeNull()
        ->and($kopie->unterschrift_bild_pfad)->toBeNull();
});

function auftragSignaturPng(): string
{
    return 'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mP8z8BQDwAEhQGAhKmMIQAAAABJRU5ErkJggg==';
}

function benutzer(): User
{
    $user = User::factory()->create();
    $user->assignRole('Benutzer');

    return $user;
}

function einstufig(bool $keinFreigeber = false): AppSettings
{
    return AppSettings::from([
        'autoPushBeiBestellt' => false,
        'freigabeStufen' => [[
            'bezeichnung' => 'Standard',
            'vonBetrag' => 0,
            'bisBetrag' => null,
            'berechtigteRollen' => ['Benutzer'],
            'freigabe1Regeln' => [[
                'typ' => 'default',
                'keinFreigeber' => $keinFreigeber,
                'quelleTyp' => 'single',
                'quelle' => 'vorgesetzter',
                'excludeAttribute' => [],
            ]],
            'freigabe2Regeln' => [],
        ]],
        'angebotsRegeln' => [[
            'abBetrag' => 0,
            'mindestAngebote' => 0,
            'begruendungErlaubt' => true,
        ]],
    ]);
}

function zweistufig(): AppSettings
{
    return AppSettings::from([
        'autoPushBeiBestellt' => false,
        'freigabeStufen' => [[
            'bezeichnung' => 'Standard',
            'vonBetrag' => 0,
            'bisBetrag' => null,
            'berechtigteRollen' => ['Benutzer'],
            'freigabe1Regeln' => [[
                'typ' => 'default',
                'keinFreigeber' => true,
                'quelleTyp' => 'single',
                'quelle' => 'vorgesetzter',
                'excludeAttribute' => [],
            ]],
            'freigabe2Regeln' => [[
                'typ' => 'default',
                'keinFreigeber' => true,
                'quelleTyp' => 'single',
                'quelle' => 'vorgesetzter',
                'excludeAttribute' => [],
            ]],
        ]],
    ]);
}

function unterschriftWorkflow(AppSettings $settings): BestellungWorkflow
{
    return new BestellungWorkflow(
        new WertgrenzenService($settings),
        new AngebotsregelService($settings),
        app(BestellscheinD3Service::class),
        app(AngebotD3Service::class),
        app(KassenbonRechnungD3Service::class),
        app(AuftragsschreibenDaten::class),
    );
}

function unterschriftZeile(Bestellung $bestellung, User $user): void
{
    $pfad = 'bestellungen/angebote/'.$bestellung->id.'/angebot.pdf';
    Storage::disk('local')->put($pfad, '%PDF-1.4 angebot');

    $angebot = Angebot::query()->create([
        'bestellung_id' => $bestellung->id,
        'user_id' => $user->id,
        'typ' => 'angebot',
        'pdf_path' => $pfad,
        'lieferantenname' => 'Dach GmbH',
        'nummer' => 'A-1',
    ]);

    Auftragszeile::factory()->create([
        'bestellung_id' => $bestellung->id,
        'angebot_id' => $angebot->id,
        'nr' => 1,
        'angebotsdatum' => '2026-10-12',
        'angebotsnummer' => 'A-1',
        'betrag_netto' => 100,
        'betrag_brutto' => 119,
    ]);
}
