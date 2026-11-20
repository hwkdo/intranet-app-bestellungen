<?php

declare(strict_types=1);

namespace Hwkdo\IntranetAppBestellungen\Livewire\Apps\Bestellungen;

use App\Models\User;
use Flux\Flux;
use Hwkdo\D3RestLaravel\Client as D3Client;
use Hwkdo\D3RestLaravel\Enums\DocTypeEnum;
use Hwkdo\IntranetAppBestellungen\Data\AngebotsregelAuswertung;
use Hwkdo\IntranetAppBestellungen\Enums\AktionTyp;
use Hwkdo\IntranetAppBestellungen\Enums\AuftragModus;
use Hwkdo\IntranetAppBestellungen\Enums\BestellungStatus;
use Hwkdo\IntranetAppBestellungen\Enums\BestellungTyp;
use Hwkdo\IntranetAppBestellungen\Exceptions\WorkflowException;
use Hwkdo\IntranetAppBestellungen\Http\Requests\MeldeFehlendenLieferantRequest;
use Hwkdo\IntranetAppBestellungen\Jobs\ExtractAngebotMetadataJob;
use Hwkdo\IntranetAppBestellungen\Models\Angebot;
use Hwkdo\IntranetAppBestellungen\Models\Auftragszeile;
use Hwkdo\IntranetAppBestellungen\Models\Bestellung;
use Hwkdo\IntranetAppBestellungen\Models\LieferantCache;
use Hwkdo\IntranetAppBestellungen\Models\Notiz;
use Hwkdo\IntranetAppBestellungen\Models\Position;
use Hwkdo\IntranetAppBestellungen\Services\AngebotsregelService;
use Hwkdo\IntranetAppBestellungen\Services\Api\BestellungAngebotUploadService;
use Hwkdo\IntranetAppBestellungen\Services\AuftragszeilenService;
use Hwkdo\IntranetAppBestellungen\Services\BenNumberService;
use Hwkdo\IntranetAppBestellungen\Services\BestellungWorkflow;
use Hwkdo\IntranetAppBestellungen\Services\D3\AngebotD3Service;
use Hwkdo\IntranetAppBestellungen\Services\Lieferant\FehlenderLieferantMeldungService;
use Hwkdo\IntranetAppBestellungen\Services\Lieferant\LieferantSuggestionsService;
use Hwkdo\IntranetAppBestellungen\Services\Pdf\AuftragsschreibenDaten;
use Hwkdo\IntranetAppBestellungen\Services\WertgrenzenService;
use Hwkdo\IntranetAppBestellungen\Support\PlatzhalterLieferant;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithFileUploads;

#[Title('Bestellung Detail')]
class Detail extends Component
{
    use WithFileUploads;

    public Bestellung $bestellung;

    #[Url(as: 'aktion')]
    public ?string $aktionParam = null;

    #[Url(as: 'hinweis')]
    public ?string $hinweisParam = null;

    #[Url(as: 'tab')]
    public string $activeTab = 'positionen';

    public bool $d3DokumenteGeladen = false;

    /** @var array<int, array<string, mixed>> */
    public array $d3Dokumente = [];

    /** @var array<string, bool> */
    public array $d3DokumenteAmpel = [
        'bestellschein' => false,
        'rechnung' => false,
        'lieferschein' => false,
    ];

    public ?string $freigabeNachricht = null;

    public ?string $ablehnenGrund = null;

    public ?int $weiterleitenAnUserId = null;

    public ?string $weiterleitenNachricht = null;

    public ?int $einreichenAnUserId = null;

    public bool $positionenBearbeiten = false;

    /** @var array<int, array<string, mixed>> */
    public array $positionenDraft = [];

    /** @var array<int, mixed> */
    public array $positionenDraftPdfs = [];

    /** @var array<int, string> */
    public array $einreichFreigeberOptionen = [];

    /** @var array<int, string> */
    public array $einreichFreigeberHinweise = [];

    public string $notizText = '';

    public string $angebotTyp = 'angebot';

    public ?string $angebotLieferant = null;

    public ?string $angebotNummer = null;

    public ?string $angebotDatum = null;

    public ?float $angebotBetragNetto = null;

    public ?float $angebotBetrag = null;

    public ?string $angebotBegruendung = null;

    public $angebotPdf = null;

    public ?string $bestellenLieferantennummer = null;

    public string $bestellenLieferantSearch = '';

    /** @var list<int|string> */
    public array $ausgewaehlteAngebotIds = [];

    public ?string $manuelleZeileDatum = null;

    public ?string $manuelleZeileNummer = null;

    public ?string $manuelleZeileNetto = null;

    public ?string $manuelleZeileBrutto = null;

    public ?string $manuelleZeileFreitext = null;

    public ?string $manuelleZeileLieferantennummer = null;

    public string $manuelleZeileLieferantSearch = '';

    public string $auftragModus = 'tabelle';

    public ?string $ausfuehrungsfrist = null;

    public ?string $auftragnehmerAnrede = null;

    public ?string $auftragnehmerAnsprechpartner = null;

    public ?string $auftragnehmerEmail = null;

    public ?int $angebotAngabenId = null;

    public ?string $angebotAngabenLieferantennummer = null;

    public string $angebotAngabenLieferantSearch = '';

    public ?string $angebotAngabenErkannterLieferant = null;

    public string $fehlenderLieferantName = '';

    public string $fehlenderLieferantAdresse = '';

    public string $fehlenderLieferantIban = '';

    public string $fehlenderLieferantWebseite = '';

    public ?string $angebotAngabenLieferant = null;

    public ?string $angebotAngabenNummer = null;

    public ?string $angebotAngabenDatum = null;

    public ?string $angebotAngabenNetto = null;

    public ?string $angebotAngabenBrutto = null;

    /** @var list<array<string, mixed>> */
    public array $auftragszeilenDraft = [];

    public function mount(Bestellung $bestellung): void
    {
        $this->bestellung = $bestellung->load([
            'user', 'freigeber', 'besteller', 'internerEmpfaenger',
            'positionen.art', 'positionen.media', 'angebote.user', 'angebote.auftragszeile',
            'auftragszeilen.angebot', 'notizen.user', 'aktionen.user', 'projekt',
        ]);

        abort_unless(
            Auth::user() !== null && $this->bestellung->istSichtbarFuer(Auth::user()),
            403,
        );

        if ($this->bestellung->istAuftragsvergabe() && $this->activeTab === 'positionen') {
            $this->activeTab = 'auftrag';
        }

        $this->syncAuftragszeilenDraft();
        $this->syncAuftragsschreiben();

        if ($this->aktionParam === 'freigeben' && $this->kannFreigeben()) {
            Flux::modal('freigeben-modal')->show();
        }
        if ($this->aktionParam === 'ablehnen' && $this->kannFreigeben()) {
            Flux::modal('ablehnen-modal')->show();
        }
        if ($this->aktionParam === 'einreichen' && $this->kannEinreichen()) {
            if ($this->hinweisParam === 'freigeber') {
                Flux::toast(
                    heading: 'Hinweis',
                    text: 'Es konnte kein eindeutiger Freigeber ermittel werden. Bitte Freigeber auswählen',
                    variant: 'warning',
                );
            }
            $this->einreichenModalOeffnen();
        }
        if ($this->aktionParam === 'bestellen' && $this->kannBestellen()) {
            $this->bestellen();
        }
    }

    public function updatedActiveTab(string $value): void
    {
        if ($value === 'd3' && ! $this->d3DokumenteGeladen) {
            $this->ladeD3Dokumente();
        }
    }

    public function kannFreigeben(): bool
    {
        if (! $this->bestellung->status?->isFreigabePending()) {
            return false;
        }

        return app(WertgrenzenService::class)->darfFreigeben(Auth::user(), $this->bestellung);
    }

    public function kannBestellen(): bool
    {
        if ($this->bestellung->istNachtraeglich()) {
            return false;
        }

        $user = Auth::user();

        return $user !== null && $this->bestellung->darfVonUserBestelltAbschliessen($user);
    }

    public function kassenbonD3Erneut(): void
    {
        $user = Auth::user();
        if ($user === null || ! $this->bestellung->istNachtraeglich() || $this->bestellung->status !== BestellungStatus::Entwurf) {
            return;
        }

        if ((int) $this->bestellung->user_id !== (int) $user->getKey() && ! $user->can('manage-app-bestellungen')) {
            return;
        }

        try {
            app(BestellungWorkflow::class)->nachtraeglichAbschliessen($this->bestellung, $user);
            $this->bestellung->refresh();
            Flux::toast(text: 'Bestellschein und Rechnung wurden an D3 übertragen.', variant: 'success');
        } catch (WorkflowException $e) {
            Flux::toast(heading: 'D3-Übertragung', text: $e->getMessage(), variant: 'error');
        }
    }

    #[Computed]
    public function bestellenLieferantenSuggestions(): Collection
    {
        return app(LieferantSuggestionsService::class)
            ->suche($this->bestellenLieferantSearch, $this->bestellenLieferantennummer)
            ->reject(fn (LieferantCache $l): bool => PlatzhalterLieferant::istPlatzhalter($l->lieferantennummer));
    }

    #[Computed]
    public function manuelleLieferantenSuggestions(): Collection
    {
        return app(LieferantSuggestionsService::class)
            ->suche($this->manuelleZeileLieferantSearch, $this->manuelleZeileLieferantennummer)
            ->reject(fn (LieferantCache $l): bool => PlatzhalterLieferant::istPlatzhalter($l->lieferantennummer));
    }

    #[Computed]
    public function angebotAngabenLieferantenSuggestions(): Collection
    {
        return app(LieferantSuggestionsService::class)
            ->suche($this->angebotAngabenLieferantSearch, $this->angebotAngabenLieferantennummer)
            ->reject(fn (LieferantCache $l): bool => PlatzhalterLieferant::istPlatzhalter($l->lieferantennummer)
                && $l->lieferantennummer !== $this->angebotAngabenLieferantennummer);
    }

    /**
     * @return array<int, array{typ: string, label: string, inline_url: string}>
     */
    #[Computed]
    public function bestellscheinPdfMenue(): array
    {
        return array_map(
            fn (BestellungTyp $typ): array => [
                'typ' => $typ->value,
                'label' => $typ->bestellscheinLabel(),
                'inline_url' => route('apps.bestellungen.pdf.inline', [
                    'bestellung' => $this->bestellung,
                    'typ' => $typ->value,
                ]),
            ],
            BestellungTyp::bestellscheinVariantenFuer($this->bestellung->typ ?? BestellungTyp::Extern),
        );
    }

    public function d3OneUrl(?string $d3id = null): ?string
    {
        $d3id ??= $this->bestellung->d3id;

        if (! filled($d3id)) {
            return null;
        }

        return app(D3Client::class)->getD3OneObjectUrl((string) $d3id);
    }

    public function ladeD3Dokumente(): void
    {
        try {
            $result = app(D3Client::class)->SearchResult(fulltext: $this->bestellung->nummer);
            $collection = $result instanceof Collection ? $result : collect($result ?? []);

            $this->d3Dokumente = $collection
                ->filter(fn ($doc) => isset($doc->id))
                ->map(function ($doc): array {
                    $docType = $doc->doc_type instanceof DocTypeEnum ? $doc->doc_type : null;
                    $docTypeValue = $docType?->value;

                    return [
                        'id' => (string) $doc->id,
                        'art' => $this->d3DokumentArtLabel($docTypeValue),
                        'doc_type' => $docTypeValue,
                        'filename' => $doc->filename ?? null,
                        'caption' => $doc->betreff ?? null,
                        'url' => $doc->d3one ?? app(D3Client::class)->getD3OneObjectUrl((string) $doc->id),
                    ];
                })
                ->values()
                ->all();

            $this->d3DokumenteAmpel = [
                'bestellschein' => collect($this->d3Dokumente)->contains(fn (array $doc): bool => $doc['doc_type'] === DocTypeEnum::Bestellschein->value),
                'rechnung' => collect($this->d3Dokumente)->contains(fn (array $doc): bool => $doc['doc_type'] === DocTypeEnum::Zahlungsbeleg->value),
                'lieferschein' => collect($this->d3Dokumente)->contains(fn (array $doc): bool => $doc['doc_type'] === DocTypeEnum::Lieferschein->value),
            ];
            $this->d3DokumenteGeladen = true;
        } catch (\Throwable $e) {
            Flux::toast(heading: 'D3-Suche fehlgeschlagen', text: $e->getMessage(), variant: 'error');
        }
    }

    private function d3DokumentArtLabel(?string $docType): string
    {
        return match ($docType) {
            DocTypeEnum::Bestellschein->value => 'Bestellschein',
            DocTypeEnum::Zahlungsbeleg->value => 'Rechnung',
            DocTypeEnum::Lieferschein->value => 'Lieferschein',
            DocTypeEnum::Bestellvorgang->value => 'Bestellvorgang',
            DocTypeEnum::Angebote->value => 'Angebot',
            default => $docType ?? 'Unbekannt',
        };
    }

    public function kannBearbeiten(): bool
    {
        return $this->bestellung->user_id === Auth::id()
            && in_array($this->bestellung->status, [BestellungStatus::Entwurf, BestellungStatus::Abgelehnt], true);
    }

    public function kannLoeschen(): bool
    {
        $user = Auth::user();

        return $user !== null
            && (int) $this->bestellung->user_id === (int) $user->getAuthIdentifier()
            && $this->bestellung->status === BestellungStatus::Entwurf;
    }

    public function loeschen(): void
    {
        if (! $this->kannLoeschen()) {
            return;
        }

        $user = Auth::user();
        if (! $user instanceof User) {
            return;
        }

        $nummer = $this->bestellung->nummer;

        DB::transaction(function () use ($user): void {
            app(BestellungWorkflow::class)->logAktion(
                $this->bestellung,
                $user,
                AktionTyp::Geloescht,
                'Entwurf gelöscht.',
            );
            $this->bestellung->kassenbon()->update(['bestellung_id' => null]);
            $this->bestellung->delete();
        });

        Flux::toast(
            heading: 'Entwurf gelöscht',
            text: 'Die Bestellung '.$nummer.' wurde gelöscht.',
            variant: 'success',
        );

        $this->redirectRoute('apps.bestellungen.meine', navigate: true);
    }

    public function kannEinreichen(): bool
    {
        if ($this->bestellung->istNachtraeglich()) {
            return false;
        }

        if ($this->bestellung->user_id !== Auth::id() || $this->bestellung->status !== BestellungStatus::Entwurf) {
            return false;
        }

        if (! $this->angebotsregelAuswertung()->bereit) {
            return false;
        }

        if (! $this->bestellung->istAuftragsvergabe()) {
            return true;
        }

        if (! $this->auftragszeilenVollstaendig() || ! filled($this->bestellung->lieferantenname)) {
            return false;
        }

        return ! $this->bestellung->nutztAuftragsvorlage() || $this->auftragsschreibenFehlendeAngaben() === [];
    }

    public function einreichenGesperrtHinweis(): string
    {
        if ($this->bestellung->istAuftragsvergabe() && (! $this->auftragszeilenFelderVollstaendig() || ! filled($this->bestellung->lieferantenname))) {
            return 'Mindestens eine vollständige Auftragszeile erfassen. Der Anbieter des übernommenen Angebots wird der Auftragnehmer.';
        }

        if ($this->bestellung->istAuftragsvergabe() && ! $this->auftragszeilenHabenAngebotsPdf()) {
            return 'Jede Auftragszeile braucht das hinterlegte Angebot als PDF.';
        }

        if ($this->bestellung->nutztAuftragsvorlage()) {
            $fehlend = $this->auftragsschreibenFehlendeAngaben();

            if ($fehlend !== []) {
                return 'Für das Auftragsschreiben fehlen: '.implode(', ', $fehlend).'.';
            }
        }

        return 'Angebotsvoraussetzungen auf dem Reiter „Angebote / Begründung“ erfüllen';
    }

    public function kannAngeboteErfassen(): bool
    {
        return $this->bestellung->user_id === Auth::id()
            && in_array($this->bestellung->status, [BestellungStatus::Entwurf, BestellungStatus::Abgelehnt], true);
    }

    #[Computed]
    public function angebotsregelAuswertung(): AngebotsregelAuswertung
    {
        return app(AngebotsregelService::class)->auswertung($this->bestellung);
    }

    public function freigeben(): void
    {
        if (! $this->kannFreigeben()) {
            return;
        }
        try {
            app(BestellungWorkflow::class)->freigeben($this->bestellung, Auth::user(), $this->freigabeNachricht);
            Flux::modal('freigeben-modal')->close();
            Flux::toast(heading: 'Freigegeben', text: 'Die Bestellung wurde freigegeben.', variant: 'success');
            $this->refreshBestellung();
        } catch (\Throwable $e) {
            Flux::toast(heading: 'Fehler', text: $e->getMessage(), variant: 'error');
        }
    }

    public function ablehnen(): void
    {
        $this->validate(['ablehnenGrund' => ['required', 'string', 'min:3']]);
        try {
            app(BestellungWorkflow::class)->ablehnen($this->bestellung, Auth::user(), (string) $this->ablehnenGrund);
            Flux::modal('ablehnen-modal')->close();
            Flux::toast(heading: 'Abgelehnt', text: 'Die Bestellung wurde abgelehnt.', variant: 'success');
            $this->refreshBestellung();
        } catch (\Throwable $e) {
            Flux::toast(heading: 'Fehler', text: $e->getMessage(), variant: 'error');
        }
    }

    public function weiterleiten(): void
    {
        $this->validate(['weiterleitenAnUserId' => ['required', 'integer']]);
        try {
            app(BestellungWorkflow::class)->weiterleiten(
                $this->bestellung,
                Auth::user(),
                (int) $this->weiterleitenAnUserId,
                $this->weiterleitenNachricht,
            );
            Flux::modal('weiterleiten-modal')->close();
            Flux::toast(heading: 'Weitergeleitet', text: 'Die Bestellung wurde weitergeleitet.', variant: 'success');
            $this->refreshBestellung();
        } catch (\Throwable $e) {
            Flux::toast(heading: 'Fehler', text: $e->getMessage(), variant: 'error');
        }
    }

    public function bestellen(): void
    {
        if (! $this->kannBestellen()) {
            return;
        }

        if ($this->bestellung->istIntern()) {
            $this->bestellenLieferantennummer = null;
            $this->bestellenLieferantSearch = '';
            Flux::modal('bestellen-lieferant')->show();

            return;
        }

        $this->bestellenAusfuehren();
    }

    public function bestellenMitLieferant(): void
    {
        if (! $this->kannBestellen()) {
            return;
        }

        $this->validate([
            'bestellenLieferantennummer' => [
                'required',
                'string',
                'max:50',
                function (string $attribute, mixed $value, \Closure $fail): void {
                    if (PlatzhalterLieferant::istPlatzhalter(is_string($value) ? $value : null)) {
                        $fail('Bitte wählen Sie den tatsächlichen Lieferanten (nicht den Platzhalter).');
                    }

                    $exists = LieferantCache::query()
                        ->where('lieferantennummer', $value)
                        ->exists();

                    if (! $exists) {
                        $fail('Der gewählte Lieferant ist nicht im Stammdaten-Cache vorhanden.');
                    }
                },
            ],
        ], [
            'bestellenLieferantennummer.required' => 'Bitte wählen Sie den tatsächlichen Lieferanten.',
        ]);

        $lieferant = LieferantCache::query()
            ->where('lieferantennummer', $this->bestellenLieferantennummer)
            ->first();

        $this->bestellung->update([
            'lieferantennummer' => $lieferant?->lieferantennummer,
            'lieferantenname' => $lieferant?->lieferantenname,
        ]);

        $this->refreshBestellung();
        Flux::modal('bestellen-lieferant')->close();
        $this->bestellenAusfuehren();
    }

    private function bestellenAusfuehren(): void
    {
        try {
            app(BestellungWorkflow::class)->bestellen($this->bestellung->fresh(), Auth::user());
            Flux::toast(heading: 'Bestellt', text: 'Die Bestellung wurde erfolgreich an D3 übertragen.', variant: 'success');
            $this->refreshBestellung();
        } catch (\Throwable $e) {
            Flux::toast(heading: 'Fehler', text: $e->getMessage(), variant: 'error');
        }
    }

    public function einreichen(): void
    {
        if (! $this->kannEinreichen()) {
            return;
        }

        try {
            $freigeberId = $this->resolveEinreichFreigeberId();

            app(BestellungWorkflow::class)->einreichen($this->bestellung, Auth::user(), $freigeberId);
            Flux::modal('einreichen-modal')->close();
            $this->refreshBestellung();

            if ($this->bestellung->status === BestellungStatus::Freigegeben) {
                Flux::toast(
                    heading: 'Bestellung freigegeben',
                    text: 'Die Bestellung wurde automatisch freigegeben (kein Freigeber erforderlich).',
                    variant: 'success',
                );
            } else {
                Flux::toast(
                    heading: 'Zur Freigabe eingereicht',
                    text: 'Die Bestellung wurde zur Freigabe weitergeleitet.',
                    variant: 'success',
                );
            }
        } catch (\Throwable $e) {
            Flux::toast(heading: 'Fehler', text: $e->getMessage(), variant: 'error');
        }
    }

    public function einreichenModalOeffnen(): void
    {
        if (! $this->kannEinreichen()) {
            return;
        }

        if (app(WertgrenzenService::class)->istFreigeber1NichtNoetig($this->bestellung)) {
            $this->einreichen();

            return;
        }

        $this->einreichenAnUserId = null;
        $this->prepareEinreichFreigeberAuswahl();

        if ($this->einreichFreigeberOptionen === []) {
            $this->einreichen();

            return;
        }

        // Eine Option ohne Abwesenheitshinweis: eindeutig und anwesend → direkt einreichen.
        // Mit Hinweisen (Vertretung/Abwesenheit) immer Modal zeigen, damit der Nutzer informiert ist.
        if (count($this->einreichFreigeberOptionen) === 1 && $this->einreichFreigeberHinweise === []) {
            $this->einreichenAnUserId = array_key_first($this->einreichFreigeberOptionen);
            $this->einreichen();

            return;
        }

        if (count($this->einreichFreigeberOptionen) === 1) {
            $this->einreichenAnUserId = array_key_first($this->einreichFreigeberOptionen);
        } elseif ($this->einreichFreigeberHinweise !== []) {
            // Bei Abwesenheit standardmäßig die Vertretung vorwählen, Freigeber bleibt aber wählbar.
            foreach ($this->einreichFreigeberOptionen as $userId => $label) {
                if (str_contains((string) $label, 'Vertretung für')) {
                    $this->einreichenAnUserId = (int) $userId;
                    break;
                }
            }
        }

        Flux::modal('einreichen-modal')->show();
    }

    public function positionenBearbeitenStarten(): void
    {
        if (! $this->kannBearbeiten()) {
            return;
        }

        $this->positionenDraft = $this->bestellung->positionen
            ->sortBy('nr')
            ->values()
            ->map(fn (Position $pos): array => [
                'id' => $pos->getKey(),
                'bezeichnung' => $pos->bezeichnung,
                'art_nr' => $pos->art_nr,
                'menge' => (float) $pos->menge,
                'einheit' => $pos->einheit,
                'preis' => (float) $pos->preis,
                'pdf_position' => $pos->hasPositionPdf(),
            ])->all();
        $this->positionenDraftPdfs = array_fill(0, count($this->positionenDraft), null);

        $this->positionenBearbeiten = true;
    }

    public function positionenBearbeitenAbbrechen(): void
    {
        $this->positionenBearbeiten = false;
        $this->positionenDraft = [];
        $this->positionenDraftPdfs = [];
    }

    public function positionDraftHinzufuegen(): void
    {
        if (! $this->positionenBearbeiten) {
            return;
        }

        $this->positionenDraft[] = [
            'id' => null,
            'bezeichnung' => '',
            'art_nr' => null,
            'menge' => 1,
            'einheit' => 'Stk',
            'preis' => 0,
            'pdf_position' => false,
        ];
        $this->positionenDraftPdfs[] = null;
    }

    public function positionDraftEntfernen(int $idx): void
    {
        if (! $this->positionenBearbeiten) {
            return;
        }

        unset($this->positionenDraft[$idx]);
        unset($this->positionenDraftPdfs[$idx]);
        $this->positionenDraft = array_values($this->positionenDraft);
        $this->positionenDraftPdfs = array_values($this->positionenDraftPdfs);
    }

    public function positionenSpeichern(): void
    {
        if (! $this->kannBearbeiten()) {
            return;
        }

        $this->validate([
            'positionenDraft' => ['required', 'array', 'min:1'],
            'positionenDraft.*.bezeichnung' => ['required', 'string', 'max:255'],
            'positionenDraft.*.art_nr' => ['nullable', 'string', 'max:100'],
            'positionenDraft.*.menge' => ['required', 'numeric', 'min:0.01'],
            'positionenDraft.*.einheit' => ['nullable', 'string', 'max:20'],
            'positionenDraft.*.preis' => ['required', 'numeric', 'min:0'],
            'positionenDraft.*.pdf_position' => ['boolean'],
            'positionenDraftPdfs' => ['array'],
            'positionenDraftPdfs.*' => ['nullable', 'file', 'mimes:pdf', 'max:10240'],
        ]);

        foreach ($this->positionenDraft as $idx => $draft) {
            if (! ($draft['pdf_position'] ?? false)) {
                continue;
            }

            $hasExistingPdf = false;
            if (! empty($draft['id'])) {
                $existing = $this->bestellung->positionen->firstWhere('id', $draft['id']);
                $hasExistingPdf = $existing?->hasPositionPdf() ?? false;
            }

            if (! $hasExistingPdf && empty($this->positionenDraftPdfs[$idx])) {
                $this->addError('positionenDraftPdfs.'.$idx, 'Bitte wählen Sie für die PDF-Position eine PDF-Datei aus.');
            }
        }

        if ($this->getErrorBag()->isNotEmpty()) {
            return;
        }

        DB::transaction(function (): void {
            $vorhandeneIds = $this->bestellung->positionen->pluck('id')->all();
            $draftIds = collect($this->positionenDraft)->pluck('id')->filter()->values()->all();
            $zuLoeschen = array_diff($vorhandeneIds, $draftIds);

            if ($zuLoeschen !== []) {
                Position::query()
                    ->where('bestellung_id', $this->bestellung->getKey())
                    ->whereIn('id', $zuLoeschen)
                    ->delete();
            }

            foreach ($this->positionenDraft as $idx => $draft) {
                $payload = [
                    'nr' => $idx + 1,
                    'bezeichnung' => (string) $draft['bezeichnung'],
                    'art_nr' => $draft['art_nr'] ?: null,
                    'menge' => (float) $draft['menge'],
                    'einheit' => $draft['einheit'] ?: null,
                    'preis' => (float) $draft['preis'],
                ];

                if (! empty($draft['id'])) {
                    Position::query()
                        ->where('bestellung_id', $this->bestellung->getKey())
                        ->where('id', $draft['id'])
                        ->update($payload);

                    $position = Position::query()->find($draft['id']);
                    if ($position) {
                        if (! empty($this->positionenDraftPdfs[$idx])) {
                            $pdf = $this->positionenDraftPdfs[$idx];
                            $media = $position->addMedia($pdf->getRealPath())
                                ->usingFileName($pdf->getClientOriginalName())
                                ->toMediaCollection('position_pdf');
                            $position->forceFill(['file' => $media->getPathRelativeToRoot()])->save();
                        } elseif (! ($draft['pdf_position'] ?? false)) {
                            $position->clearMediaCollection('position_pdf');
                            $position->forceFill(['file' => null])->save();
                        }
                    }
                } else {
                    $position = Position::create(array_merge($payload, [
                        'bestellung_id' => $this->bestellung->getKey(),
                        'art_id' => null,
                        'oberbegriff' => null,
                    ]));

                    if (! empty($this->positionenDraftPdfs[$idx])) {
                        $pdf = $this->positionenDraftPdfs[$idx];
                        $media = $position->addMedia($pdf->getRealPath())
                            ->usingFileName($pdf->getClientOriginalName())
                            ->toMediaCollection('position_pdf');
                        $position->forceFill(['file' => $media->getPathRelativeToRoot()])->save();
                    }
                }
            }
        });

        $this->bestellung->refreshGesamtbetrag();
        $this->refreshBestellung();
        $this->positionenBearbeitenAbbrechen();

        Flux::toast(
            heading: 'Positionen gespeichert',
            text: 'Die Positionen wurden aktualisiert.',
            variant: 'success',
        );
    }

    public function wiederholen(): void
    {
        $alt = $this->bestellung;

        $neu = DB::transaction(function () use ($alt): Bestellung {
            $nummer = app(BenNumberService::class)->next(Auth::user(), (int) date('Y'));

            $kopie = $alt->replicate(['nummer', 'd3id', 'd3_pushed_at', 'bestellt_at', 'status', 'freigeber_id', 'besteller_id']);
            $kopie->nummer = $nummer;
            $kopie->status = BestellungStatus::Entwurf;
            $kopie->haushaltsjahr = (int) date('Y');
            $kopie->user_id = Auth::id();
            $kopie->freigeber_id = null;
            $kopie->besteller_id = null;
            $kopie->ausfuehrungsfrist = null;
            $kopie->d3id = null;
            $kopie->d3_pushed_at = null;
            $kopie->bestellt_at = null;
            $kopie->wiederholt_von_id = $alt->getKey();
            $kopie->save();

            if (! $alt->istAuftragsvergabe()) {
                foreach ($alt->positionen as $pos) {
                    $newPosition = Position::create([
                        'bestellung_id' => $kopie->getKey(),
                        'art_id' => $pos->art_id,
                        'nr' => $pos->nr,
                        'menge' => $pos->menge,
                        'einheit' => $pos->einheit,
                        'art_nr' => $pos->art_nr,
                        'oberbegriff' => $pos->oberbegriff,
                        'bezeichnung' => $pos->bezeichnung,
                        'preis' => $pos->preis,
                        'file' => $pos->file,
                    ]);

                    $positionPdf = $pos->getFirstMedia('position_pdf');
                    if ($positionPdf) {
                        $copiedMedia = $positionPdf->copy($newPosition, 'position_pdf');
                        $newPosition->forceFill(['file' => $copiedMedia->getPathRelativeToRoot()])->save();
                    }
                }
            }

            $kopie->refreshGesamtbetrag();

            app(BestellungWorkflow::class)->logAktion(
                $kopie,
                Auth::user(),
                AktionTyp::Wiederholt,
                payload: ['vorlage_id' => $alt->getKey(), 'vorlage_nummer' => $alt->nummer],
            );

            return $kopie;
        });

        Flux::toast(
            heading: 'Bestellung dupliziert',
            text: 'Bestellnummer '.$neu->nummer.' wurde aus '.$alt->nummer.' erzeugt.',
            variant: 'success',
        );

        $this->redirectRoute('apps.bestellungen.detail', ['bestellung' => $neu->getKey()], navigate: true);
    }

    public function notizSpeichern(): void
    {
        $this->validate(['notizText' => ['required', 'string', 'min:1']]);

        $notiz = Notiz::create([
            'bestellung_id' => $this->bestellung->getKey(),
            'user_id' => Auth::id(),
            'text' => $this->notizText,
        ]);

        app(BestellungWorkflow::class)->logAktion(
            $this->bestellung,
            Auth::user(),
            AktionTyp::NotizHinzugefuegt,
            payload: ['notiz_id' => $notiz->getKey()],
        );

        $this->notizText = '';
        $this->refreshBestellung();

        Flux::toast(text: 'Die Notiz wurde gespeichert.', heading: 'Notiz hinzugefügt', variant: 'success');
    }

    public function angebotSpeichern(): void
    {
        if (! $this->kannAngeboteErfassen()) {
            return;
        }

        if ($this->bestellung->istAuftragsvergabe() && $this->angebotTyp === 'begruendung') {
            $this->addError('angebotTyp', 'Für eine Auftragsvergabe werden Angebote erfasst. Eine Ausnahme-Begründung gibt es hier nicht.');

            return;
        }

        if ($this->angebotTyp === 'begruendung') {
            $this->validate([
                'angebotTyp' => ['required', 'in:angebot,begruendung'],
                'angebotBegruendung' => ['required', 'string', 'min:10'],
            ], [
                'angebotBegruendung.required' => 'Bitte geben Sie die Ausnahme-Begründung ein.',
                'angebotBegruendung.min' => 'Die Ausnahme-Begründung muss mindestens 10 Zeichen lang sein.',
            ]);

            if ($this->bestellung->angebote()->where('typ', 'begruendung')->exists()) {
                $this->addError('angebotBegruendung', 'Für diese Bestellung existiert bereits eine Ausnahme-Begründung.');

                return;
            }

            $angebot = Angebot::create([
                'bestellung_id' => $this->bestellung->getKey(),
                'user_id' => Auth::id(),
                'typ' => 'begruendung',
                'begruendung' => $this->angebotBegruendung,
            ]);

            app(AngebotD3Service::class)->generateBegruendungPdf($angebot);

            $this->nachAngebotGespeichert($angebot);

            return;
        }

        $this->validate([
            'angebotTyp' => ['required', 'in:angebot,begruendung'],
            'angebotLieferant' => ['nullable', 'string', 'max:255'],
            'angebotNummer' => ['nullable', 'string', 'max:100'],
            'angebotDatum' => ['nullable', 'date'],
            'angebotBetragNetto' => ['nullable', 'numeric'],
            'angebotBetrag' => ['nullable', 'numeric'],
            'angebotPdf' => ['required', 'file', 'mimes:pdf', 'max:10240'],
        ], [
            'angebotPdf.required' => $this->bestellung->istAuftragsvergabe()
                ? 'Bitte laden Sie das Angebot als PDF hoch.'
                : 'Bitte laden Sie das Vergleichsangebot als PDF hoch.',
        ]);

        $angebot = app(BestellungAngebotUploadService::class)->store(
            bestellung: $this->bestellung,
            userId: (int) Auth::id(),
            payload: [
                'file' => $this->angebotPdf,
                'supplier_name' => $this->angebotLieferant,
                'reference_number' => $this->angebotNummer,
                'offer_date' => filled($this->angebotDatum) ? $this->angebotDatum : null,
                'net_amount' => $this->angebotBetragNetto === '' ? null : $this->angebotBetragNetto,
                'amount' => $this->angebotBetrag === '' ? null : $this->angebotBetrag,
            ],
        );

        $this->nachAngebotGespeichert($angebot);
    }

    public function angebotLoeschen(int $angebotId): void
    {
        if (! $this->kannAngeboteErfassen()) {
            return;
        }

        $angebot = $this->bestellung->angebote()
            ->whereKey($angebotId)
            ->first();

        if (! $angebot) {
            return;
        }

        if (filled($angebot->pdf_path)) {
            Storage::disk('local')->delete((string) $angebot->pdf_path);
        }

        $angebot->delete();

        app(BestellungWorkflow::class)->logAktion(
            $this->bestellung,
            Auth::user(),
            AktionTyp::AngebotEntfernt,
            payload: ['angebot_id' => $angebotId, 'typ' => $angebot->typ],
        );

        $this->refreshBestellung();
        unset($this->angebotsregelAuswertung, $this->waehlbareAngebote);

        Flux::toast(
            text: 'Das Angebot wurde inkl. Datei gelöscht.',
            heading: 'Angebot gelöscht',
            variant: 'success',
        );
    }

    private function nachAngebotGespeichert(Angebot $angebot): void
    {
        app(BestellungWorkflow::class)->logAktion(
            $this->bestellung,
            Auth::user(),
            AktionTyp::AngebotHinzugefuegt,
            payload: ['angebot_id' => $angebot->getKey(), 'typ' => $angebot->typ],
        );

        $this->reset(['angebotLieferant', 'angebotNummer', 'angebotDatum', 'angebotBetragNetto', 'angebotBetrag', 'angebotBegruendung', 'angebotPdf']);
        $this->angebotTyp = 'angebot';
        $this->refreshBestellung();
        unset($this->angebotsregelAuswertung, $this->waehlbareAngebote);

        $auswertung = $this->angebotsregelAuswertung();
        $istAuftragsvergabe = $this->bestellung->istAuftragsvergabe();

        Flux::toast(
            text: $angebot->typ === 'begruendung'
                ? 'Die Ausnahme-Begründung wurde als PDF gespeichert und kann nach dem Bestellen nach D3 übertragen werden.'
                : ($angebot->extraction_status === 'pending'
                    ? ($istAuftragsvergabe
                        ? 'Das Angebot wurde gespeichert. Fehlende Metadaten werden nun automatisch per KI extrahiert.'
                        : 'Das Vergleichsangebot wurde gespeichert. Fehlende Metadaten werden nun automatisch per KI extrahiert.')
                    : ($istAuftragsvergabe
                        ? 'Das Angebot wurde gespeichert. Die D3-Übertragung erfolgt beim Bestellen.'
                        : 'Das Vergleichsangebot wurde gespeichert. Die D3-Übertragung erfolgt beim Bestellen.')),
            heading: $angebot->typ === 'begruendung'
                ? 'Ausnahme-Begründung gespeichert'
                : ($istAuftragsvergabe ? 'Angebot gespeichert' : 'Vergleichsangebot gespeichert'),
            variant: 'success',
        );

        if ($auswertung->anwendbar && $auswertung->bereit && $this->bestellung->status === BestellungStatus::Entwurf) {
            Flux::toast(
                text: 'Sie können die Bestellung jetzt zur Freigabe einreichen.',
                heading: 'Angebotsvoraussetzungen erfüllt',
                variant: 'success',
            );
        }
    }

    public function moeglicheFreigeberOptions(): array
    {
        return User::query()
            ->where('id', '!=', Auth::id())
            ->orderBy('nachname')
            ->orderBy('vorname')
            ->limit(200)
            ->get(['id', 'vorname', 'nachname'])
            ->mapWithKeys(fn (User $u): array => [$u->id => $u->name])
            ->all();
    }

    public function moeglicheEinreichFreigeberOptions(): array
    {
        return $this->einreichFreigeberOptionen;
    }

    /**
     * @return array<int, string>
     */
    public function aktuelleFreigeberNamen(): array
    {
        if (! $this->bestellung->status?->isFreigabePending()) {
            return [];
        }

        if ($this->bestellung->freigeber) {
            return [$this->bestellung->freigeber->name];
        }

        $service = app(WertgrenzenService::class);
        $pool = $this->bestellung->status === BestellungStatus::ZurZweitenFreigabe
            ? $service->freigeber2FuerBestellung($this->bestellung)
            : $service->freigeber1FuerBestellung($this->bestellung);

        return $pool
            ->map(fn (User $user): string => $user->name)
            ->values()
            ->all();
    }

    public function render(): View
    {
        $this->bestellung->unsetRelation('angebote');
        $this->bestellung->load(['angebote.user', 'angebote.auftragszeile']);

        $this->bestellung->loadMissing('kassenbon');

        return view('intranet-app-bestellungen::livewire.apps.bestellungen.detail');
    }

    #[Computed]
    public function angebotAnalyseLaeuft(): bool
    {
        return $this->bestellung->angebote->contains(
            fn (Angebot $angebot): bool => in_array($angebot->extraction_status, ['pending', 'processing'], true),
        );
    }

    public function angebotAnalyseErneutStarten(int $angebotId): void
    {
        if (! $this->kannAngeboteErfassen()) {
            return;
        }

        $angebot = $this->angebotFuerAngaben($angebotId);
        if (! $angebot instanceof Angebot || $angebot->analyseStatus() !== 'fehlgeschlagen') {
            return;
        }

        if (! filled($angebot->pdf_path)) {
            Flux::toast(
                heading: 'Analyse nicht möglich',
                text: 'Für dieses Angebot liegt keine PDF-Datei vor.',
                variant: 'warning',
            );

            return;
        }

        $angebot->forceFill([
            'extraction_status' => 'pending',
            'extraction_error' => null,
        ])->save();

        ExtractAngebotMetadataJob::dispatch($angebot->getKey());

        $this->refreshBestellung();
        unset($this->angebotAnalyseLaeuft, $this->waehlbareAngebote);

        Flux::toast(
            heading: 'Analyse gestartet',
            text: 'Die Erkennung läuft erneut im Hintergrund.',
            variant: 'success',
        );
    }

    public function angebotAngabenOeffnen(int $angebotId): void
    {
        if (! $this->kannAngeboteErfassen()) {
            return;
        }

        $angebot = $this->angebotFuerAngaben($angebotId);
        if (! $angebot instanceof Angebot) {
            return;
        }

        $this->angebotAngabenId = $angebot->id;
        $this->angebotAngabenLieferantennummer = $angebot->lieferantennummer;
        $this->angebotAngabenLieferantSearch = filled($angebot->lieferantennummer)
            ? ''
            : (string) ($angebot->lieferantenname ?? '');
        $this->angebotAngabenErkannterLieferant = filled(data_get($angebot->extraction_payload, 'erkannter_lieferant'))
            ? (string) data_get($angebot->extraction_payload, 'erkannter_lieferant')
            : $angebot->lieferantenname;
        $this->angebotAngabenLieferant = $angebot->lieferantenname;
        $this->angebotAngabenNummer = $angebot->nummer;
        $this->angebotAngabenDatum = $angebot->angebotsdatum?->toDateString();
        $this->angebotAngabenNetto = $angebot->betrag_netto !== null ? (string) $angebot->betrag_netto : null;
        $this->angebotAngabenBrutto = $angebot->betrag !== null ? (string) $angebot->betrag : null;

        Flux::modal('angebot-angaben')->show();
    }

    public function angebotFehlendenLieferantVorbereiten(): void
    {
        if (! $this->kannAngeboteErfassen()) {
            return;
        }

        if (! filled($this->fehlenderLieferantName)) {
            $this->fehlenderLieferantName = (string) ($this->angebotAngabenErkannterLieferant ?? '');
        }

        Flux::modal('angebot-fehlender-lieferant')->show();
    }

    public function angebotFehlendenLieferantMelden(): void
    {
        if (! $this->kannAngeboteErfassen()) {
            return;
        }

        $this->validate(
            MeldeFehlendenLieferantRequest::livewireValidationRules(),
            MeldeFehlendenLieferantRequest::validationMessages(),
            MeldeFehlendenLieferantRequest::validationAttributes(),
        );

        try {
            app(FehlenderLieferantMeldungService::class)->send(
                Auth::user(),
                $this->fehlenderLieferantName,
                $this->fehlenderLieferantAdresse ?: null,
                $this->fehlenderLieferantIban ?: null,
                $this->fehlenderLieferantWebseite ?: null,
            );
        } catch (\Throwable $exception) {
            Flux::toast(heading: 'Fehler', text: $exception->getMessage(), variant: 'error');

            return;
        }

        $this->angebotAngabenLieferantennummer = PlatzhalterLieferant::nummer();
        $this->angebotAngabenLieferantSearch = '';
        $this->fehlenderLieferantName = '';
        $this->fehlenderLieferantAdresse = '';
        $this->fehlenderLieferantIban = '';
        $this->fehlenderLieferantWebseite = '';
        unset($this->angebotAngabenLieferantenSuggestions);

        $platzhalter = LieferantCache::query()->where('lieferantennummer', $this->angebotAngabenLieferantennummer)->first();
        if (! $platzhalter instanceof LieferantCache) {
            Log::warning('Platzhalter-Lieferant nicht im Stammdaten-Cache', [
                'lieferantennummer' => $this->angebotAngabenLieferantennummer,
            ]);
        }

        Flux::modal('angebot-fehlender-lieferant')->close();
        Flux::toast(text: 'Fehlender Lieferant erfolgreich gemeldet', variant: 'success');
    }

    public function angebotAngabenSpeichern(): void
    {
        if (! $this->kannAngeboteErfassen() || $this->angebotAngabenId === null) {
            return;
        }

        $angebot = $this->angebotFuerAngaben($this->angebotAngabenId);
        if (! $angebot instanceof Angebot) {
            return;
        }

        $this->validate([
            'angebotAngabenLieferantennummer' => ['required', 'string', 'max:50'],
            'angebotAngabenNummer' => ['required', 'string', 'max:100'],
            'angebotAngabenDatum' => ['required', 'date'],
            'angebotAngabenNetto' => ['required', 'numeric', 'min:0'],
            'angebotAngabenBrutto' => ['nullable', 'numeric', 'min:0'],
        ]);

        $lieferant = LieferantCache::query()
            ->where('lieferantennummer', $this->angebotAngabenLieferantennummer)
            ->first();

        $istPlatzhalter = PlatzhalterLieferant::istPlatzhalter($this->angebotAngabenLieferantennummer);

        if (! $lieferant instanceof LieferantCache && ! $istPlatzhalter) {
            $this->addError('angebotAngabenLieferantennummer', 'Bitte wählen Sie einen Lieferanten aus den Stammdaten.');

            return;
        }

        $angebot->loadMissing('auftragszeile');

        try {
            if ($angebot->auftragszeile !== null && $lieferant instanceof LieferantCache) {
                app(AuftragszeilenService::class)->auftragnehmerAusNummer(
                    $this->bestellung,
                    (string) $lieferant->lieferantennummer,
                );
            }
        } catch (WorkflowException $exception) {
            $this->addError('angebotAngabenLieferantennummer', $exception->getMessage());

            return;
        }

        $angebot->forceFill([
            'lieferantenname' => $lieferant?->lieferantenname ?: 'Unbekannter Lieferant',
            'lieferantennummer' => $lieferant?->lieferantennummer ?: $this->angebotAngabenLieferantennummer,
            'nummer' => trim((string) $this->angebotAngabenNummer),
            'angebotsdatum' => $this->angebotAngabenDatum,
            'betrag_netto' => $this->angebotAngabenNetto,
            'betrag' => filled($this->angebotAngabenBrutto) ? $this->angebotAngabenBrutto : null,
            'extraction_status' => 'done',
            'extraction_source' => 'manual',
            'extraction_error' => null,
            'extracted_at' => now(),
        ])->save();

        if ($angebot->auftragszeile !== null) {
            $angebot->auftragszeile->update([
                'angebotsdatum' => $angebot->angebotsdatum,
                'angebotsnummer' => $angebot->nummer,
                'betrag_netto' => $angebot->betrag_netto,
                'betrag_brutto' => $angebot->betrag,
            ]);
            $this->bestellung->refreshGesamtbetrag();
            $this->syncAuftragszeilenDraft();
        }

        app(BestellungWorkflow::class)->logAktion(
            $this->bestellung,
            Auth::user(),
            AktionTyp::AngebotGeaendert,
            'Angaben zum Angebot wurden manuell ergänzt.',
            payload: ['angebot_id' => $angebot->getKey()],
        );

        $this->refreshBestellung();
        unset($this->angebotAnalyseLaeuft, $this->waehlbareAngebote);
        Flux::modal('angebot-angaben')->close();
        Flux::toast(heading: 'Angaben gespeichert', text: 'Die Angebotsangaben wurden übernommen.', variant: 'success');
    }

    /**
     * @return Collection<int, Angebot>
     */
    #[Computed]
    public function waehlbareAngebote(): Collection
    {
        return $this->bestellung->angebote
            ->where('typ', 'angebot')
            ->filter(fn (Angebot $angebot): bool => $angebot->auftragszeile === null)
            ->values();
    }

    public function angebotWeichtVomAuftragnehmerAb(Angebot $angebot): bool
    {
        $erkannt = mb_strtolower(trim((string) $angebot->lieferantenname));
        $auftragnehmer = mb_strtolower(trim((string) $this->bestellung->lieferantenname));

        return $erkannt !== '' && $auftragnehmer !== '' && $erkannt !== $auftragnehmer;
    }

    public function angeboteAlsAuftragszeilenUebernehmen(): void
    {
        if (! $this->kannAuftragszeilenBearbeiten()) {
            return;
        }

        $this->auftragsschreibenAusFormularUebernehmen();

        try {
            $anzahl = app(AuftragszeilenService::class)->ausAngeboten(
                $this->bestellung,
                array_map(static fn (mixed $id): int => (int) $id, $this->ausgewaehlteAngebotIds),
                Auth::user(),
            );
        } catch (WorkflowException $exception) {
            Flux::toast(heading: 'Angebot nicht übernommen', text: $exception->getMessage(), variant: 'error');

            return;
        }

        $this->ausgewaehlteAngebotIds = [];
        $this->refreshBestellung();
        $this->syncAuftragszeilenDraft();
        $this->syncAuftragsschreiben();
        unset($this->waehlbareAngebote);
        Flux::modal('angebote-waehlen')->close();

        Flux::toast(
            heading: $anzahl > 0 ? 'Auftragszeilen übernommen' : 'Keine Angebote übernommen',
            text: $anzahl > 0
                ? $anzahl.' Angebot'.($anzahl === 1 ? '' : 'e').' als Auftragszeile übernommen.'
                    .($this->bestellung->nutztAuftragsvorlage() ? '' : ' Freitext können Sie je Zeile ergänzen.')
                : 'Die Auswahl enthält keine freien Angebote.',
            variant: $anzahl > 0 ? 'success' : 'warning',
        );
    }

    public function manuelleAuftragszeileSpeichern(): void
    {
        if (! $this->kannAuftragszeilenBearbeiten()) {
            return;
        }

        $this->auftragsschreibenAusFormularUebernehmen();

        $this->validate([
            'manuelleZeileDatum' => ['required', 'date'],
            'manuelleZeileNummer' => ['required', 'string', 'max:100'],
            'manuelleZeileNetto' => ['required', 'numeric', 'min:0'],
            'manuelleZeileBrutto' => ['nullable', 'numeric', 'min:0', Rule::requiredIf(fn (): bool => $this->bestellung->nutztAuftragsvorlage())],
            'manuelleZeileFreitext' => ['nullable', 'string'],
            'manuelleZeileLieferantennummer' => [
                Rule::requiredIf(fn (): bool => ! filled($this->bestellung->lieferantenname)),
                'nullable',
                'string',
                'max:50',
            ],
        ]);

        try {
            app(AuftragszeilenService::class)->manuell($this->bestellung, [
                'angebotsdatum' => (string) $this->manuelleZeileDatum,
                'angebotsnummer' => (string) $this->manuelleZeileNummer,
                'betrag_netto' => $this->manuelleZeileNetto,
                'betrag_brutto' => $this->manuelleZeileBrutto,
                'freitext' => $this->manuelleZeileFreitext,
                'lieferantennummer' => $this->manuelleZeileLieferantennummer,
            ], Auth::user());
        } catch (WorkflowException $exception) {
            Flux::toast(heading: 'Auftragnehmer fehlt', text: $exception->getMessage(), variant: 'error');

            return;
        }

        $this->reset([
            'manuelleZeileDatum',
            'manuelleZeileNummer',
            'manuelleZeileNetto',
            'manuelleZeileBrutto',
            'manuelleZeileFreitext',
            'manuelleZeileLieferantennummer',
            'manuelleZeileLieferantSearch',
        ]);
        $this->refreshBestellung();
        $this->syncAuftragszeilenDraft();
        Flux::modal('zeile-manuell')->close();
        Flux::toast(heading: 'Auftragszeile erfasst', text: 'Die Zeile ohne Angebot wurde gespeichert.', variant: 'success');
    }

    public function auftragszeileSpeichern(int $idx): void
    {
        if (! $this->kannAuftragszeilenBearbeiten()) {
            return;
        }

        $this->auftragsschreibenAusFormularUebernehmen();

        $this->validate([
            'auftragszeilenDraft.'.$idx.'.angebotsdatum' => ['required', 'date'],
            'auftragszeilenDraft.'.$idx.'.angebotsnummer' => ['required', 'string', 'max:100'],
            'auftragszeilenDraft.'.$idx.'.betrag_netto' => ['required', 'numeric', 'min:0'],
            'auftragszeilenDraft.'.$idx.'.betrag_brutto' => ['nullable', 'numeric', 'min:0', Rule::requiredIf(fn (): bool => $this->bestellung->nutztAuftragsvorlage())],
            'auftragszeilenDraft.'.$idx.'.freitext' => ['nullable', 'string'],
        ]);

        $draft = $this->auftragszeilenDraft[$idx] ?? null;
        if (! is_array($draft)) {
            return;
        }

        $zeile = $this->bestellung->auftragszeilen()->whereKey($draft['id'] ?? null)->first();
        if (! $zeile instanceof Auftragszeile) {
            return;
        }

        app(AuftragszeilenService::class)->aktualisieren($zeile, [
            'angebotsdatum' => $draft['angebotsdatum'] ?? null,
            'angebotsnummer' => $draft['angebotsnummer'] ?? null,
            'betrag_netto' => $draft['betrag_netto'] ?? null,
            'betrag_brutto' => $draft['betrag_brutto'] ?? null,
            'freitext' => $draft['freitext'] ?? null,
        ], Auth::user());

        $this->refreshBestellung();
        $this->syncAuftragszeilenDraft();
        Flux::toast(heading: 'Zeile gespeichert', text: 'Die Auftragszeile wurde aktualisiert.', variant: 'success');
    }

    public function auftragszeileAenderungenVerwerfen(int $idx): void
    {
        if (! $this->kannAuftragszeilenBearbeiten()) {
            return;
        }

        $draft = $this->auftragszeilenDraft[$idx] ?? null;
        if (! is_array($draft)) {
            return;
        }

        $zeile = $this->bestellung->auftragszeilen->firstWhere('id', $draft['id'] ?? null);
        if (! $zeile instanceof Auftragszeile) {
            return;
        }

        $this->auftragszeilenDraft[$idx] = $this->auftragszeileAlsDraft($zeile);
        $this->resetValidation([
            'auftragszeilenDraft.'.$idx.'.angebotsdatum',
            'auftragszeilenDraft.'.$idx.'.angebotsnummer',
            'auftragszeilenDraft.'.$idx.'.betrag_netto',
            'auftragszeilenDraft.'.$idx.'.betrag_brutto',
            'auftragszeilenDraft.'.$idx.'.freitext',
        ]);
    }

    public function auftragszeileEntfernen(int $zeileId): void
    {
        if (! $this->kannAuftragszeilenBearbeiten()) {
            return;
        }

        $zeile = $this->bestellung->auftragszeilen()->whereKey($zeileId)->first();
        if (! $zeile instanceof Auftragszeile) {
            return;
        }

        app(AuftragszeilenService::class)->entfernen($zeile, Auth::user());
        $this->refreshBestellung();
        $this->syncAuftragszeilenDraft();
        unset($this->waehlbareAngebote);
        Flux::toast(heading: 'Zeile entfernt', text: 'Die Auftragszeile wurde gelöscht.', variant: 'success');
    }

    public function auftragszeileVerschieben(int $zeileId, int $richtung): void
    {
        if (! $this->kannAuftragszeilenBearbeiten()) {
            return;
        }

        $zeile = $this->bestellung->auftragszeilen()->whereKey($zeileId)->first();
        if (! $zeile instanceof Auftragszeile) {
            return;
        }

        app(AuftragszeilenService::class)->verschieben($zeile, $richtung, Auth::user());
        $this->refreshBestellung();
        $this->syncAuftragszeilenDraft();
    }

    public function updatedAuftragModus(): void
    {
        $this->auftragModusGeaendert();
    }

    public function auftragModusGeaendert(): void
    {
        if (! $this->kannAuftragszeilenBearbeiten()) {
            $this->syncAuftragsschreiben();

            return;
        }

        $modus = AuftragModus::tryFrom($this->auftragModus) ?? AuftragModus::Tabelle;

        if ($this->auftragModus !== $modus->value) {
            $this->auftragModus = $modus->value;
        }

        $this->bestellung->forceFill([
            'auftrag_modus' => $modus,
        ])->save();
        $this->auftragnehmerVorbelegenWennLeer();

        app(BestellungWorkflow::class)->logAktion(
            $this->bestellung,
            Auth::user(),
            AktionTyp::AuftragszeileGeaendert,
            $modus === AuftragModus::Vorlage
                ? 'Auftragsschein wird aus der Vorlage erzeugt.'
                : 'Auftragsschein verwendet Freitext je Zeile.',
        );

        $this->refreshBestellung();
        $this->syncAuftragsschreiben();
    }

    public function updatedAusfuehrungsfrist(): void
    {
        $this->auftragsschreibenAusFormularUebernehmen();
        $this->bestellung->refresh();
    }

    public function auftragsschreibenSpeichern(): void
    {
        if (! $this->kannAuftragszeilenBearbeiten()) {
            return;
        }

        $this->auftragnehmerAnrede = $this->auftragnehmerAnrede === '' ? null : $this->auftragnehmerAnrede;

        $this->validate([
            'ausfuehrungsfrist' => ['nullable', 'date'],
            'auftragnehmerAnrede' => ['nullable', Rule::in(['herr', 'frau'])],
            'auftragnehmerAnsprechpartner' => ['nullable', 'string', 'max:255'],
            'auftragnehmerEmail' => ['nullable', 'email', 'max:255'],
        ]);

        $this->bestellung->forceFill([
            'ausfuehrungsfrist' => $this->ausfuehrungsfrist,
            'auftragnehmer_anrede' => $this->auftragnehmerAnrede,
            'auftragnehmer_ansprechpartner' => $this->nullableText($this->auftragnehmerAnsprechpartner) ?? '',
            'auftragnehmer_email' => $this->nullableText($this->auftragnehmerEmail) ?? '',
        ])->save();

        app(BestellungWorkflow::class)->logAktion(
            $this->bestellung,
            Auth::user(),
            AktionTyp::AuftragszeileGeaendert,
            'Ansprechpartner im Auftragsschreiben aktualisiert.',
            payload: ['ausfuehrungsfrist' => $this->ausfuehrungsfrist],
        );

        $this->refreshBestellung();
        $this->syncAuftragsschreiben();

        Flux::toast(heading: 'Ansprechpartner gespeichert', text: 'Die Angaben für das Anschreiben wurden übernommen.', variant: 'success');
    }

    public function auftragnehmerAenderungenVerwerfen(): void
    {
        if (! $this->kannAuftragszeilenBearbeiten()) {
            return;
        }

        $this->auftragnehmerAnrede = $this->bestellung->auftragnehmer_anrede;
        $this->auftragnehmerAnsprechpartner = $this->bestellung->auftragnehmer_ansprechpartner;
        $this->auftragnehmerEmail = $this->bestellung->auftragnehmer_email;
        $this->resetValidation([
            'auftragnehmerAnrede',
            'auftragnehmerAnsprechpartner',
            'auftragnehmerEmail',
        ]);
    }

    public function auftragnehmerIstGeaendert(): bool
    {
        return $this->vergleichstext($this->auftragnehmerAnrede) !== $this->vergleichstext($this->bestellung->auftragnehmer_anrede)
            || $this->vergleichstext($this->auftragnehmerAnsprechpartner) !== $this->vergleichstext($this->bestellung->auftragnehmer_ansprechpartner)
            || $this->vergleichstext($this->auftragnehmerEmail) !== $this->vergleichstext($this->bestellung->auftragnehmer_email);
    }

    public function auftragszeileIstGeaendert(int $idx): bool
    {
        $draft = $this->auftragszeilenDraft[$idx] ?? null;

        if (! is_array($draft)) {
            return false;
        }

        $zeile = $this->bestellung->auftragszeilen->firstWhere('id', $draft['id'] ?? null);

        if (! $zeile instanceof Auftragszeile) {
            return false;
        }

        return $this->vergleichstext($draft['angebotsdatum'] ?? null) !== $zeile->angebotsdatum?->toDateString()
            || $this->vergleichstext($draft['angebotsnummer'] ?? null) !== $this->vergleichstext($zeile->angebotsnummer)
            || $this->vergleichsbetrag($draft['betrag_netto'] ?? null) !== $this->vergleichsbetrag($zeile->betrag_netto)
            || $this->vergleichsbetrag($draft['betrag_brutto'] ?? null) !== $this->vergleichsbetrag($zeile->betrag_brutto)
            || $this->vergleichstext($draft['freitext'] ?? null) !== $this->vergleichstext($zeile->freitext);
    }

    /**
     * @return list<string>
     */
    public function auftragsschreibenFehlendeAngaben(): array
    {
        if (! $this->bestellung->nutztAuftragsvorlage()) {
            return [];
        }

        return app(AuftragsschreibenDaten::class)->fehlendeAngaben($this->bestellung);
    }

    public function auftragnehmerAusAngebotErkannt(): bool
    {
        $vorbelegung = app(AuftragsschreibenDaten::class)->vorbelegungAusAngeboten($this->bestellung);

        if ($vorbelegung['anrede'] === null && $vorbelegung['ansprechpartner'] === null && $vorbelegung['email'] === null) {
            return false;
        }

        return $this->auftragnehmerAnrede === $vorbelegung['anrede']
            && $this->auftragnehmerAnsprechpartner === $vorbelegung['ansprechpartner']
            && $this->auftragnehmerEmail === $vorbelegung['email'];
    }

    private function auftragsschreibenAusFormularUebernehmen(): void
    {
        if (! $this->bestellung->nutztAuftragsvorlage() || ! $this->kannAuftragszeilenBearbeiten()) {
            return;
        }

        $frist = $this->nullableText($this->ausfuehrungsfrist);

        if ($frist !== null) {
            try {
                $frist = Carbon::parse($frist)->toDateString();
            } catch (\Throwable) {
                return;
            }
        }

        $anrede = in_array($this->auftragnehmerAnrede, ['herr', 'frau'], true) ? $this->auftragnehmerAnrede : null;
        $email = $this->nullableText($this->auftragnehmerEmail);

        if ($email !== null && filter_var($email, FILTER_VALIDATE_EMAIL) === false) {
            $email = $this->bestellung->auftragnehmer_email;
        }

        $felder = [
            'ausfuehrungsfrist' => $frist,
        ];

        if ($this->auftragnehmerAnrede !== null || $this->bestellung->auftragnehmer_anrede !== null) {
            $felder['auftragnehmer_anrede'] = $anrede;
        }

        if (is_string($this->auftragnehmerAnsprechpartner) || $this->bestellung->auftragnehmer_ansprechpartner !== null) {
            $felder['auftragnehmer_ansprechpartner'] = $this->nullableText($this->auftragnehmerAnsprechpartner) ?? '';
        }

        if (is_string($this->auftragnehmerEmail) || $this->bestellung->auftragnehmer_email !== null) {
            $felder['auftragnehmer_email'] = $email ?? '';
        }

        $this->bestellung->forceFill($felder)->save();
    }

    private function kannAuftragszeilenBearbeiten(): bool
    {
        return $this->bestellung->istAuftragsvergabe() && $this->kannBearbeiten();
    }

    private function auftragszeilenVollstaendig(): bool
    {
        return $this->auftragszeilenFelderVollstaendig() && $this->auftragszeilenHabenAngebotsPdf();
    }

    private function auftragszeilenFelderVollstaendig(): bool
    {
        $zeilen = $this->bestellung->auftragszeilen;

        if ($zeilen->isEmpty()) {
            return false;
        }

        return $zeilen->every(
            fn (Auftragszeile $zeile): bool => $zeile->angebotsdatum !== null
                && filled($zeile->angebotsnummer)
                && $zeile->betrag_netto !== null,
        );
    }

    private function auftragszeilenHabenAngebotsPdf(): bool
    {
        $this->bestellung->loadMissing('auftragszeilen.angebot');

        $zeilen = $this->bestellung->auftragszeilen;

        if ($zeilen->isEmpty()) {
            return false;
        }

        return $zeilen->every(
            fn (Auftragszeile $zeile): bool => $zeile->angebot?->absolutePdfPath() !== null,
        );
    }

    private function syncAuftragszeilenDraft(): void
    {
        $this->auftragszeilenDraft = $this->bestellung->auftragszeilen
            ->map(fn (Auftragszeile $zeile): array => $this->auftragszeileAlsDraft($zeile))
            ->values()
            ->all();
    }

    /**
     * @return array{id: int|string|null, angebot_id: int|null, nr: int|null, angebotsdatum: string|null, angebotsnummer: string|null, betrag_netto: mixed, betrag_brutto: mixed, freitext: string|null}
     */
    private function auftragszeileAlsDraft(Auftragszeile $zeile): array
    {
        return [
            'id' => $zeile->getKey(),
            'angebot_id' => $zeile->angebot_id,
            'nr' => $zeile->nr,
            'angebotsdatum' => $zeile->angebotsdatum?->toDateString(),
            'angebotsnummer' => $zeile->angebotsnummer,
            'betrag_netto' => $zeile->betrag_netto,
            'betrag_brutto' => $zeile->betrag_brutto,
            'freitext' => $zeile->freitext,
        ];
    }

    private function auftragnehmerVorbelegenWennLeer(): void
    {
        if (! $this->bestellung->nutztAuftragsvorlage() || ! $this->kannAuftragszeilenBearbeiten()) {
            return;
        }

        if (
            $this->bestellung->auftragnehmer_anrede !== null
            || $this->bestellung->auftragnehmer_ansprechpartner !== null
            || $this->bestellung->auftragnehmer_email !== null
        ) {
            return;
        }

        $vorbelegung = app(AuftragsschreibenDaten::class)->vorbelegungAusAngeboten($this->bestellung);

        if ($vorbelegung['anrede'] === null && $vorbelegung['ansprechpartner'] === null && $vorbelegung['email'] === null) {
            return;
        }

        $this->bestellung->forceFill([
            'auftragnehmer_anrede' => $vorbelegung['anrede'],
            'auftragnehmer_ansprechpartner' => $vorbelegung['ansprechpartner'],
            'auftragnehmer_email' => $vorbelegung['email'],
        ])->save();
    }

    private function syncAuftragsschreiben(): void
    {
        $this->auftragnehmerVorbelegenWennLeer();

        $modus = $this->bestellung->auftrag_modus;

        $this->auftragModus = $modus instanceof AuftragModus ? $modus->value : AuftragModus::Tabelle->value;
        $this->ausfuehrungsfrist = $this->bestellung->ausfuehrungsfrist?->toDateString();
        $this->auftragnehmerAnrede = $this->bestellung->auftragnehmer_anrede;
        $this->auftragnehmerAnsprechpartner = $this->bestellung->auftragnehmer_ansprechpartner;
        $this->auftragnehmerEmail = $this->bestellung->auftragnehmer_email;
    }

    private function vergleichstext(mixed $value): ?string
    {
        return $this->nullableText(is_string($value) ? $value : (is_scalar($value) ? (string) $value : null));
    }

    private function vergleichsbetrag(mixed $value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        if (! is_numeric($value)) {
            return $this->vergleichstext($value);
        }

        return number_format((float) $value, 2, '.', '');
    }

    private function nullableText(mixed $value): ?string
    {
        if (! is_string($value)) {
            return null;
        }

        $trimmed = trim($value);

        return $trimmed === '' ? null : $trimmed;
    }

    private function angebotFuerAngaben(int $angebotId): ?Angebot
    {
        return $this->bestellung->angebote()
            ->where('typ', 'angebot')
            ->whereKey($angebotId)
            ->first();
    }

    private function refreshBestellung(): void
    {
        $this->bestellung = $this->bestellung->fresh([
            'user', 'freigeber', 'besteller', 'internerEmpfaenger',
            'positionen.art', 'positionen.media', 'angebote.user', 'angebote.auftragszeile',
            'auftragszeilen.angebot', 'notizen.user', 'aktionen.user', 'projekt',
        ]);
    }

    private function resolveEinreichFreigeberId(): ?int
    {
        $wertgrenzen = app(WertgrenzenService::class);

        if ($wertgrenzen->istFreigeber1NichtNoetig($this->bestellung)) {
            return null;
        }

        $pool = $wertgrenzen->freigeber1FuerBestellung($this->bestellung);

        if ($pool->isEmpty()) {
            return null;
        }

        $this->validate([
            'einreichenAnUserId' => ['required', 'integer'],
        ], [
            'einreichenAnUserId.required' => 'Bitte wählen Sie einen Freigeber aus.',
        ]);

        return $this->einreichenAnUserId;
    }

    private function prepareEinreichFreigeberAuswahl(): void
    {
        $pool = app(WertgrenzenService::class)->freigeber1FuerBestellung($this->bestellung);
        $optionen = collect();
        $hinweise = [];
        $vorgesetzte = $this->alleVorgesetztenDesBestellers();

        foreach ($pool as $kandidat) {
            $vertretung = $this->d3VertretungWennAbwesend($kandidat);

            if ($vertretung['is_absent'] === false) {
                $optionen->put($kandidat->id, $kandidat->name);

                continue;
            }

            // Eigentlichen Freigeber weiter anbieten (als abwesend markiert), z. B. bei geplanter Rückkehr.
            $optionen->put(
                $kandidat->id,
                sprintf('%s (abwesend)', $kandidat->name),
            );

            if ($vertretung['deputy'] instanceof User) {
                $deputy = $vertretung['deputy'];
                $optionen->put(
                    $deputy->id,
                    sprintf('%s (Vertretung für %s)', $deputy->name, $kandidat->name),
                );
                $hinweise[] = sprintf(
                    '%s ist in D3 abwesend. Vertretung: %s.',
                    $kandidat->name,
                    $deputy->name,
                );

                continue;
            }

            $hinweise[] = sprintf(
                'Der Freigeber %s hat keine Vertretung angegeben, bitte wählen Sie einen anderen Vertreter.',
                $kandidat->name,
            );

            foreach ($vorgesetzte as $vorgesetzter) {
                $optionen->put($vorgesetzter->id, $vorgesetzter->name);
            }
        }

        $this->einreichFreigeberOptionen = $optionen->all();
        $this->einreichFreigeberHinweise = $hinweise;
    }

    /**
     * @return Collection<int, User>
     */
    private function alleVorgesetztenDesBestellers(): Collection
    {
        $besteller = $this->bestellung->user;
        if (! $besteller) {
            return collect();
        }

        return $besteller->getVorgesetzte()
            ->reject(fn (User $u): bool => $u->getKey() === $besteller->getKey())
            ->unique('id')
            ->values();
    }

    /**
     * @return array{is_absent: bool, deputy: ?User}
     */
    private function d3VertretungWennAbwesend(User $kandidat): array
    {
        try {
            $client = app(D3Client::class);
            $d3UserId = $client->getUserIdByUsername($kandidat->username);

            if (! $d3UserId) {
                return ['is_absent' => false, 'deputy' => null];
            }

            $absence = $client->getUserAbsence($d3UserId);

            return [
                'is_absent' => (bool) $absence->abwesend,
                'deputy' => $absence->vertreter instanceof User ? $absence->vertreter : null,
            ];
        } catch (\Throwable) {
            return ['is_absent' => false, 'deputy' => null];
        }
    }
}
