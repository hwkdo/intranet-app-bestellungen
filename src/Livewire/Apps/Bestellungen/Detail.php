<?php

declare(strict_types=1);

namespace Hwkdo\IntranetAppBestellungen\Livewire\Apps\Bestellungen;

use App\Models\User;
use Flux\Flux;
use Hwkdo\D3RestLaravel\Client as D3Client;
use Hwkdo\D3RestLaravel\Enums\DocTypeEnum;
use Hwkdo\IntranetAppBestellungen\Data\AngebotsregelAuswertung;
use Hwkdo\IntranetAppBestellungen\Enums\AktionTyp;
use Hwkdo\IntranetAppBestellungen\Enums\BestellungStatus;
use Hwkdo\IntranetAppBestellungen\Enums\BestellungTyp;
use Hwkdo\IntranetAppBestellungen\Models\Angebot;
use Hwkdo\IntranetAppBestellungen\Models\Bestellung;
use Hwkdo\IntranetAppBestellungen\Models\LieferantCache;
use Hwkdo\IntranetAppBestellungen\Models\Notiz;
use Hwkdo\IntranetAppBestellungen\Models\Position;
use Hwkdo\IntranetAppBestellungen\Services\AngebotsregelService;
use Hwkdo\IntranetAppBestellungen\Services\Api\BestellungAngebotUploadService;
use Hwkdo\IntranetAppBestellungen\Services\BenNumberService;
use Hwkdo\IntranetAppBestellungen\Services\BestellungWorkflow;
use Hwkdo\IntranetAppBestellungen\Services\D3\AngebotD3Service;
use Hwkdo\IntranetAppBestellungen\Services\Lieferant\LieferantSuggestionsService;
use Hwkdo\IntranetAppBestellungen\Services\WertgrenzenService;
use Hwkdo\IntranetAppBestellungen\Support\PlatzhalterLieferant;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
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

    public ?float $angebotBetrag = null;

    public ?string $angebotBegruendung = null;

    public $angebotPdf = null;

    public ?string $bestellenLieferantennummer = null;

    public string $bestellenLieferantSearch = '';

    public function mount(Bestellung $bestellung): void
    {
        $this->bestellung = $bestellung->load([
            'user', 'freigeber', 'besteller', 'internerEmpfaenger',
            'positionen.art', 'positionen.media', 'angebote.user', 'notizen.user', 'aktionen.user', 'projekt',
        ]);

        abort_unless(
            Auth::user() !== null && $this->bestellung->istSichtbarFuer(Auth::user()),
            403,
        );

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
        $user = Auth::user();

        return $user !== null && $this->bestellung->darfVonUserBestelltAbschliessen($user);
    }

    #[Computed]
    public function bestellenLieferantenSuggestions(): Collection
    {
        return app(LieferantSuggestionsService::class)
            ->suche($this->bestellenLieferantSearch, $this->bestellenLieferantennummer)
            ->reject(fn (LieferantCache $l): bool => PlatzhalterLieferant::istPlatzhalter($l->lieferantennummer));
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
            BestellungTyp::bestellscheinVarianten(),
        );
    }

    public function d3OneUrl(): ?string
    {
        if (! $this->bestellung->d3id) {
            return null;
        }

        return app(D3Client::class)->getD3OneObjectUrl((string) $this->bestellung->d3id);
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

    public function kannEinreichen(): bool
    {
        return $this->bestellung->user_id === Auth::id()
            && $this->bestellung->status === BestellungStatus::Entwurf
            && $this->angebotsregelAuswertung()->bereit;
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

        if (count($this->einreichFreigeberOptionen) === 1) {
            $this->einreichenAnUserId = array_key_first($this->einreichFreigeberOptionen);
            $this->einreichen();

            return;
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
            $kopie->d3id = null;
            $kopie->d3_pushed_at = null;
            $kopie->bestellt_at = null;
            $kopie->wiederholt_von_id = $alt->getKey();
            $kopie->save();

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
            'angebotBetrag' => ['nullable', 'numeric'],
            'angebotPdf' => ['required', 'file', 'mimes:pdf', 'max:10240'],
        ], [
            'angebotPdf.required' => 'Bitte laden Sie das Vergleichsangebot als PDF hoch.',
        ]);

        $angebot = app(BestellungAngebotUploadService::class)->store(
            bestellung: $this->bestellung,
            userId: (int) Auth::id(),
            payload: [
                'file' => $this->angebotPdf,
                'supplier_name' => $this->angebotLieferant,
                'reference_number' => $this->angebotNummer,
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
        unset($this->angebotsregelAuswertung);

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

        $this->reset(['angebotLieferant', 'angebotNummer', 'angebotBetrag', 'angebotBegruendung', 'angebotPdf']);
        $this->angebotTyp = 'angebot';
        $this->refreshBestellung();
        unset($this->angebotsregelAuswertung);

        $auswertung = $this->angebotsregelAuswertung();

        Flux::toast(
            text: $angebot->typ === 'begruendung'
                ? 'Die Ausnahme-Begründung wurde als PDF gespeichert und kann nach dem Bestellen nach D3 übertragen werden.'
                : ($angebot->extraction_status === 'pending'
                    ? 'Das Vergleichsangebot wurde gespeichert. Fehlende Metadaten werden nun automatisch per KI extrahiert.'
                    : 'Das Vergleichsangebot wurde gespeichert. Die D3-Übertragung erfolgt beim Bestellen.'),
            heading: $angebot->typ === 'begruendung' ? 'Ausnahme-Begründung gespeichert' : 'Vergleichsangebot gespeichert',
            variant: 'success',
        );

        if ($auswertung->bereit && $this->bestellung->status === BestellungStatus::Entwurf) {
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
        return view('intranet-app-bestellungen::livewire.apps.bestellungen.detail');
    }

    private function refreshBestellung(): void
    {
        $this->bestellung = $this->bestellung->fresh([
            'user', 'freigeber', 'besteller', 'internerEmpfaenger',
            'positionen.art', 'positionen.media', 'angebote.user', 'notizen.user', 'aktionen.user', 'projekt',
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
