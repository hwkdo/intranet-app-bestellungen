<?php

declare(strict_types=1);

namespace Hwkdo\IntranetAppBestellungen\Livewire\Apps\Bestellungen;

use App\Models\User;
use Flux\Flux;
use Hwkdo\D3RestLaravel\Facades\D3RestLaravel;
use Hwkdo\IntranetAppBestellungen\Enums\AktionTyp;
use Hwkdo\IntranetAppBestellungen\Enums\BestellungStatus;
use Hwkdo\IntranetAppBestellungen\Models\Art;
use Hwkdo\IntranetAppBestellungen\Models\Bestellung;
use Hwkdo\IntranetAppBestellungen\Models\IntranetAppBestellungenSettings;
use Hwkdo\IntranetAppBestellungen\Models\KostenstelleCache;
use Hwkdo\IntranetAppBestellungen\Models\LieferantCache;
use Hwkdo\IntranetAppBestellungen\Models\LieferantNutzung;
use Hwkdo\IntranetAppBestellungen\Models\Position;
use Hwkdo\IntranetAppBestellungen\Models\Projekt;
use Hwkdo\IntranetAppBestellungen\Services\AngebotsregelService;
use Hwkdo\IntranetAppBestellungen\Services\BenNumberService;
use Hwkdo\IntranetAppBestellungen\Services\BestellungWorkflow;
use Hwkdo\IntranetAppBestellungen\Services\Stammdaten\StammdatenSyncService;
use Hwkdo\IntranetAppBestellungen\Services\WertgrenzenService;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithFileUploads;

#[Title('Bestellungen – Neue Bestellung')]
class Erstellen extends Component
{
    use WithFileUploads;

    private const HINWEIS_FREIGEBER_AUSWAHL = 'Es konnte kein eindeutiger Freigeber ermittel werden. Bitte Freigeber auswählen';

    public ?string $lieferantennummer = null;

    public ?string $lieferantenname = null;

    public ?string $kostenstelle = null;

    public ?int $lieferanschriftUserId = null;

    public int $haushaltsjahr;

    public ?string $betreff = null;

    public ?string $begruendung = null;

    /** @var array<int, array<string, mixed>> */
    public array $positionen = [];

    /** @var array<int, array<string, mixed>> */
    public array $kontierung = [];

    /** @var array<int, mixed> */
    public array $positionPdfs = [];

    /** @var array<int, string> */
    public array $d3GruppenAuswahl = [];

    /** @var array<int, string> */
    public array $d3GruppenOptionen = [];

    #[Url(as: 'projekt')]
    public ?int $projektId = null;

    public string $lieferantSearch = '';

    public string $kostenstelleSearch = '';

    /** @var array<int, string> */
    public array $kontierungSearch = [];

    private const SUGGEST_LIMIT = 30;

    public function mount(): void
    {
        app(StammdatenSyncService::class)->syncIfEmpty();

        $this->haushaltsjahr = (int) date('Y');
        $this->positionen = [
            $this->emptyPosition(1),
        ];
        $this->positionPdfs = [null];
        $this->kontierung = [
            $this->emptyKontierung(),
        ];
        $this->lieferanschriftUserId = Auth::id();

        $this->loadD3GruppenAuswahl();
        $this->applyProjektBegruendungPrefill();
    }

    public function updatedProjektId(?int $projektId): void
    {
        $this->applyProjektBegruendungPrefill();
    }

    private function applyProjektBegruendungPrefill(): void
    {
        if ($this->projektId === null) {
            return;
        }

        $projekt = Projekt::query()
            ->forUser((int) Auth::id())
            ->find($this->projektId);

        if (! $projekt || ! filled($projekt->begruendung)) {
            return;
        }

        $this->begruendung = (string) $projekt->begruendung;
    }

    /**
     * @return array<string, string>
     */
    protected function messages(): array
    {
        return [
            'begruendung.required' => 'Bitte geben Sie eine Begründung für die Bestellung an.',
            'begruendung.min' => 'Die Begründung muss mindestens 10 Zeichen lang sein.',
        ];
    }

    public function rules(): array
    {
        return [
            'lieferantenname' => ['required', 'string', 'max:255'],
            'kostenstelle' => ['required', 'string', 'max:50'],
            'lieferanschriftUserId' => [
                'required',
                'integer',
                Rule::exists('users', 'id')->where(fn ($query) => $query->where('active', true)),
            ],
            'haushaltsjahr' => ['required', 'integer', 'min:2000', 'max:2100'],
            'betreff' => ['nullable', 'string', 'max:255'],
            'begruendung' => ['required', 'string', 'min:10'],
            'positionen' => ['required', 'array', 'min:1'],
            'positionen.*.bezeichnung' => ['required', 'string', 'max:255'],
            'positionen.*.menge' => ['required', 'numeric', 'min:0.01'],
            'positionen.*.preis' => ['required', 'numeric', 'min:0'],
            'positionen.*.einheit' => ['nullable', 'string', 'max:20'],
            'positionen.*.art_id' => ['nullable', 'integer'],
            'positionen.*.art_nr' => ['nullable', 'string', 'max:100'],
            'positionPdfs' => ['array'],
            'positionPdfs.*' => ['nullable', 'file', 'mimes:pdf', 'max:10240'],
            'd3GruppenAuswahl' => ['required', 'array', 'min:1'],
            'd3GruppenAuswahl.*' => ['required', 'string', 'max:255'],
            'kontierung' => ['array'],
            'kontierung.*.kostenstelle' => ['nullable', 'string', 'max:50'],
            'kontierung.*.kursnummer' => ['nullable', 'string', 'max:100'],
            'kontierung.*.raum' => ['nullable', 'string', 'max:100'],
            'kontierung.*.aufteilung' => ['nullable', 'numeric', 'min:0', 'max:100'],
        ];
    }

    public function kontierungSummeProzent(): float
    {
        return (float) collect($this->kontierung)->sum(fn (array $k): float => (float) ($k['aufteilung'] ?? 0));
    }

    public function addKontierung(): void
    {
        $this->kontierung[] = $this->emptyKontierung();
    }

    public function removeKontierung(int $idx): void
    {
        unset($this->kontierung[$idx]);
        unset($this->kontierungSearch[$idx]);
        $this->kontierung = array_values($this->kontierung);
        $this->kontierungSearch = array_values($this->kontierungSearch);
    }

    #[Computed]
    public function lieferantenSuggestions(): Collection
    {
        return $this->buildLieferantenQuery($this->lieferantSearch, $this->lieferantennummer);
    }

    #[Computed]
    public function kostenstellenSuggestions(): Collection
    {
        return $this->buildKostenstellenQuery($this->kostenstelleSearch, $this->kostenstelle);
    }

    #[Computed]
    public function lieferanschriftUserSuggestions(): Collection
    {
        return User::query()
            ->aktiv()
            ->orderBy('nachname')
            ->orderBy('vorname')
            ->get();
    }

    #[Computed]
    public function userHasProjekte(): bool
    {
        return Projekt::query()->forUser(Auth::id())->exists();
    }

    #[Computed]
    public function projektSuggestions(): Collection
    {
        return Projekt::query()->forUser(Auth::id())->orderBy('name')->get();
    }

    /**
     * Liefert die gefilterten Kostenstellen-Vorschläge für eine Kontierungs-Zeile.
     */
    public function kostenstellenForKontierung(int $idx): Collection
    {
        $search = $this->kontierungSearch[$idx] ?? '';
        $selected = $this->kontierung[$idx]['kostenstelle'] ?? null;

        return $this->buildKostenstellenQuery($search, $selected);
    }

    public function arten(): Collection
    {
        return Art::query()->aktiv()->orderBy('sortierung')->orderBy('bezeichnung')->get();
    }

    public function gesamtbetrag(): float
    {
        return collect($this->positionen)
            ->sum(fn (array $p): float => (float) ($p['menge'] ?? 0) * (float) ($p['preis'] ?? 0));
    }

    public function angebotsHinweis(): ?string
    {
        $regel = app(AngebotsregelService::class)->regelFuerBetrag($this->gesamtbetrag());
        if (! $regel || $regel->mindestAngebote === 0) {
            return null;
        }

        return sprintf(
            'Ab %s € sind mindestens %d Vergleichsangebote oder %s erforderlich.',
            number_format($regel->abBetrag, 2, ',', '.'),
            $regel->mindestAngebote,
            $regel->begruendungErlaubt ? 'eine ausführliche Begründung' : 'keine Ausnahmen erlaubt',
        );
    }

    public function freigeberHinweis(): ?string
    {
        $wertgrenzen = app(WertgrenzenService::class);
        $betrag = $this->gesamtbetrag();
        $stufe = $wertgrenzen->stufeFuerBetrag($betrag);

        if (! $stufe) {
            return 'Keine passende Freigabestufe definiert. Bitte Admin kontaktieren.';
        }

        if (! $wertgrenzen->darfBestellen(Auth::user(), $betrag)) {
            return sprintf(
                'Stufe "%s" – Sie sind nicht berechtigt, in dieser Betragsklasse zu bestellen.',
                $stufe->bezeichnung,
            );
        }

        return sprintf(
            'Stufe "%s" – %s%s',
            $stufe->bezeichnung,
            $stufe->bisBetrag === null
                ? 'unbegrenzt'
                : 'bis '.number_format($stufe->bisBetrag, 2, ',', '.').' €',
            $wertgrenzen->zweiteFreigabeNoetig($betrag)
                ? ' · zweite Freigabe erforderlich'
                : '',
        );
    }

    public function addPosition(): void
    {
        $this->positionen[] = $this->emptyPosition(count($this->positionen) + 1);
        $this->positionPdfs[] = null;
    }

    public function addPdfPosition(): void
    {
        $position = $this->emptyPosition(count($this->positionen) + 1);
        $position['pdf_position'] = true;
        $position['bezeichnung'] = 'Siehe PDF-Anlage';
        $position['menge'] = 1;
        $position['einheit'] = 'Pos';
        $position['art_nr'] = null;
        $position['oberbegriff'] = null;

        $this->positionen[] = $position;
        $this->positionPdfs[] = null;
    }

    public function removePosition(int $index): void
    {
        unset($this->positionen[$index]);
        unset($this->positionPdfs[$index]);
        $this->positionen = array_values($this->positionen);
        $this->positionPdfs = array_values($this->positionPdfs);
        foreach ($this->positionen as $idx => &$pos) {
            $pos['nr'] = $idx + 1;
        }
    }

    public function updatedLieferantennummer(?string $nummer): void
    {
        if (! $nummer) {
            $this->lieferantenname = null;

            return;
        }

        $lieferant = LieferantCache::query()->where('lieferantennummer', $nummer)->first();
        if ($lieferant) {
            $this->lieferantenname = $lieferant->lieferantenname;
        }
    }

    /**
     * Bei PDF-Positionen befüllen wir die ansonsten verpflichtenden Felder
     * mit Defaults, da Bezeichnung/Menge/Einheit dem PDF zu entnehmen sind
     * und der Nutzer ausschließlich den Gesamtpreis erfasst.
     */
    private function normalizePdfPositionen(): void
    {
        foreach ($this->positionen as $idx => &$pos) {
            if (empty($this->positionPdfs[$idx])) {
                continue;
            }

            $pos['bezeichnung'] = filled($pos['bezeichnung'] ?? null)
                ? $pos['bezeichnung']
                : 'Siehe PDF-Anlage';
            $pos['menge'] = 1;
            $pos['einheit'] = filled($pos['einheit'] ?? null) ? $pos['einheit'] : 'Pos';
            $pos['art_nr'] = $pos['art_nr'] ?? null;
            $pos['oberbegriff'] = $pos['oberbegriff'] ?? null;
        }
        unset($pos);
    }

    public function isPdfPosition(int $idx): bool
    {
        return (bool) ($this->positionen[$idx]['pdf_position'] ?? false) || ! empty($this->positionPdfs[$idx]);
    }

    public function positionPdfPreviewUrl(int $idx): ?string
    {
        $upload = $this->positionPdfs[$idx] ?? null;
        if ($upload === null) {
            return null;
        }

        try {
            return $upload->temporaryUrl();
        } catch (\Throwable) {
            return null;
        }
    }

    public function speichern(): void
    {
        $this->normalizePdfPositionen();
        $this->validate();

        foreach ($this->positionen as $idx => $position) {
            if (($position['pdf_position'] ?? false) && empty($this->positionPdfs[$idx])) {
                $this->addError('positionPdfs.'.$idx, 'Bitte wählen Sie für die PDF-Position eine PDF-Datei aus.');
            }
        }

        if ($this->getErrorBag()->isNotEmpty()) {
            return;
        }

        $service = app(WertgrenzenService::class);
        $regelService = app(AngebotsregelService::class);
        $workflow = app(BestellungWorkflow::class);

        // Frühzeitig prüfen ob der User in dieser Betragsklasse bestellen darf
        if (! $service->darfBestellen(Auth::user(), $this->gesamtbetrag())) {
            $this->addError('gesamtbetrag', 'Sie sind nicht berechtigt, in dieser Betragsklasse zu bestellen.');

            return;
        }

        $bestellung = DB::transaction(function () use ($service, $regelService, $workflow): Bestellung {
            $nummer = app(BenNumberService::class)->next(Auth::user(), $this->haushaltsjahr);
            $lieferanschriftUser = User::query()->find($this->lieferanschriftUserId);

            $bestellung = Bestellung::create([
                'nummer' => $nummer,
                'status' => BestellungStatus::Entwurf,
                'lieferantennummer' => $this->lieferantennummer,
                'lieferantenname' => $this->lieferantenname,
                'kostenstelle' => $this->kostenstelle,
                'haushaltsjahr' => $this->haushaltsjahr,
                'betreff' => $this->betreff,
                'begruendung' => $this->begruendung,
                'kontierung' => $this->normalizeKontierung(),
                'gruppen' => $this->normalizeD3GruppenAuswahl(),
                'lieferanschrift_user_id' => $lieferanschriftUser?->getKey(),
                'user_id' => Auth::id(),
                'projekt_id' => $this->projektId,
                'gesamtbetrag' => $this->gesamtbetrag(),
            ]);

            foreach ($this->positionen as $idx => $pos) {
                $position = Position::create([
                    'bestellung_id' => $bestellung->getKey(),
                    'art_id' => $pos['art_id'] ?? null,
                    'nr' => $idx + 1,
                    'menge' => $pos['menge'] ?? 1,
                    'einheit' => $pos['einheit'] ?? null,
                    'art_nr' => $pos['art_nr'] ?? null,
                    'oberbegriff' => $pos['oberbegriff'] ?? null,
                    'bezeichnung' => $pos['bezeichnung'] ?? '',
                    'preis' => $pos['preis'] ?? 0,
                ]);

                $pdf = $this->positionPdfs[$idx] ?? null;
                if ($pdf !== null) {
                    $media = $position->addMedia($pdf->getRealPath())
                        ->usingFileName($pdf->getClientOriginalName())
                        ->toMediaCollection('position_pdf');

                    // Legacy-Kompatibilität: bestehende Zusammenführungslogik berücksichtigt weiterhin "file".
                    $position->forceFill(['file' => $media->getPathRelativeToRoot()])->save();
                }
            }

            if (filled($bestellung->lieferantennummer)) {
                LieferantNutzung::upsert(
                    [
                        'lieferantennummer' => $bestellung->lieferantennummer,
                        'v3_bestellungen_count' => 1,
                        'legacy_bestellungen_count' => 0,
                    ],
                    uniqueBy: ['lieferantennummer'],
                    update: ['v3_bestellungen_count' => \Illuminate\Support\Facades\DB::raw('v3_bestellungen_count + 1')],
                );
            }

            $bestellung->refresh();
            $bestellung->refreshGesamtbetrag();

            $stufe = $service->stufeFuerBetrag((float) $bestellung->gesamtbetrag);
            if ($stufe) {
                $pool = $service->freigeber1FuerBestellung($bestellung);
                if (
                    $service->darfFreigeber1AutomatischZugewiesenWerden($bestellung)
                    && $pool->count() === 1
                ) {
                    $bestellung->freigeber_id = $pool->first()?->getKey();
                    $bestellung->save();
                }
            }

            $workflow->logAktion($bestellung, Auth::user(), AktionTyp::Erstellt);

            return $bestellung;
        });

        try {
            app(BestellungWorkflow::class)->einreichen($bestellung, Auth::user());

            Flux::toast(
                heading: 'Bestellung eingereicht',
                text: 'Bestellnummer '.$bestellung->nummer.' wurde zur Freigabe weitergeleitet.',
                variant: 'success',
            );
        } catch (\Throwable $e) {
            if ($e->getMessage() === self::HINWEIS_FREIGEBER_AUSWAHL) {
                $this->redirectRoute('apps.bestellungen.detail', [
                    'bestellung' => $bestellung,
                    'aktion' => 'einreichen',
                    'hinweis' => 'freigeber',
                ]);

                return;
            }

            Flux::toast(
                heading: 'Hinweis',
                text: $e->getMessage(),
                variant: 'warning',
            );
        }

        $this->redirectRoute('apps.bestellungen.detail', ['bestellung' => $bestellung]);
    }

    public function render(): View
    {
        return view('intranet-app-bestellungen::livewire.apps.bestellungen.erstellen', [
            'arten' => $this->arten(),
        ]);
    }

    /**
     * Server-seitige Filterung der Lieferanten. Limitiert das Ergebnis auf
     * SUGGEST_LIMIT Einträge und stellt sicher, dass der aktuell gewählte
     * Lieferant immer im Ergebnis enthalten ist.
     */
    private function buildLieferantenQuery(string $search, ?string $selected): Collection
    {
        $term = trim($search);

        $results = LieferantCache::query()
            ->leftJoin(
                'intranet_app_bestellungen_lieferant_nutzung as ln',
                'ln.lieferantennummer',
                '=',
                'intranet_app_bestellungen_lieferanten_cache.lieferantennummer'
            )
            ->select('intranet_app_bestellungen_lieferanten_cache.*')
            ->when($term !== '', function ($q) use ($term): void {
                $like = '%'.$term.'%';
                $q->where(function ($inner) use ($like): void {
                    $inner->where('lieferantenname', 'like', $like)
                        ->orWhere('intranet_app_bestellungen_lieferanten_cache.lieferantennummer', 'like', $like);
                });
            })
            ->orderByRaw('(COALESCE(ln.legacy_bestellungen_count, 0) + COALESCE(ln.v3_bestellungen_count, 0)) DESC')
            ->orderBy('lieferantenname')
            ->limit(self::SUGGEST_LIMIT)
            ->get();

        if ($selected && ! $results->firstWhere('lieferantennummer', $selected)) {
            $row = LieferantCache::query()->where('lieferantennummer', $selected)->first();
            if ($row) {
                $results->prepend($row);
            }
        }

        return $results;
    }

    /**
     * Server-seitige Filterung der Kostenstellen. Stellt sicher, dass die
     * aktuell gewählte Kostenstelle immer im Ergebnis enthalten ist.
     */
    private function buildKostenstellenQuery(string $search, ?string $selected): Collection
    {
        $term = trim($search);

        $results = KostenstelleCache::query()
            ->where('aktiv', true)
            ->when($term !== '', function ($q) use ($term): void {
                $like = '%'.$term.'%';
                $q->where(function ($inner) use ($like): void {
                    $inner->where('kostenstelle', 'like', $like)
                        ->orWhere('bezeichnung', 'like', $like);
                });
            })
            ->orderBy('kostenstelle')
            ->limit(self::SUGGEST_LIMIT)
            ->get();

        if ($selected && ! $results->firstWhere('kostenstelle', $selected)) {
            $row = KostenstelleCache::query()->where('kostenstelle', $selected)->first();
            if ($row) {
                $results->prepend($row);
            }
        }

        return $results;
    }

    /**
     * @return array<string, mixed>
     */
    private function emptyPosition(int $nr): array
    {
        return [
            'nr' => $nr,
            'pdf_position' => false,
            'art_id' => null,
            'art_nr' => null,
            'oberbegriff' => null,
            'bezeichnung' => '',
            'menge' => 1,
            'einheit' => 'Stk',
            'preis' => 0.0,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function emptyKontierung(): array
    {
        return [
            'kostenstelle' => $this->kostenstelle,
            'kursnummer' => null,
            'raum' => null,
            'aufteilung' => 100.0,
        ];
    }

    /**
     * Filtert leere Zeilen, normalisiert Werte und erzwingt eine Standard-Aufteilung,
     * wenn nur eine einzige Zeile mit fehlender %-Angabe übrig bleibt.
     *
     * @return array<int, array<string, mixed>>
     */
    private function normalizeKontierung(): array
    {
        $rows = collect($this->kontierung)
            ->map(static fn (array $row): array => [
                'kostenstelle' => $row['kostenstelle'] ?? null,
                'kursnummer' => $row['kursnummer'] ?? null,
                'raum' => $row['raum'] ?? null,
                'aufteilung' => isset($row['aufteilung']) ? (float) $row['aufteilung'] : null,
            ])
            ->reject(static fn (array $row): bool => empty($row['kostenstelle']) && empty($row['kursnummer']) && empty($row['raum']))
            ->values()
            ->all();

        if (count($rows) === 1 && empty($rows[0]['aufteilung'])) {
            $rows[0]['aufteilung'] = 100.0;
        }

        return $rows;
    }

    private function loadD3GruppenAuswahl(): void
    {
        $username = (string) (Auth::user()?->username ?? '');
        if ($username === '') {
            $this->d3GruppenOptionen = [];
            $this->d3GruppenAuswahl = [];

            return;
        }

        $userGroups = [];
        try {
            $userGroups = D3RestLaravel::getUserInGroupsSoapCached($username, $this->soapUserGroupsCacheTtlSeconds());
        } catch (\Throwable $e) {
            Log::warning('bestellungen.erstellen.d3_groups_user.failed', [
                'username' => $username,
                'error' => $e->getMessage(),
            ]);
        }

        $allGroups = [];
        try {
            $allGroups = D3RestLaravel::getD3GroupsSoapCached($this->soapAllGroupsCacheTtlSeconds());
        } catch (\Throwable $e) {
            Log::warning('bestellungen.erstellen.d3_groups_all.failed', [
                'username' => $username,
                'error' => $e->getMessage(),
            ]);
        }

        $normalizedUserGroups = collect($userGroups)
            ->map(fn ($group) => trim((string) $group))
            ->filter(fn (string $group): bool => $group !== '' && str_starts_with($group, '@'))
            ->values();

        // Legacy-Sonderfall: Bei Dozenten/EDV @Rechnungen zusätzlich vorauswählen.
        if ($normalizedUserGroups->contains('@Dozenten') || $normalizedUserGroups->contains('@D3EDV')) {
            $normalizedUserGroups->prepend('@Rechnungen');
        }

        $normalizedUserGroups = $normalizedUserGroups
            ->unique()
            ->values();

        $options = collect($allGroups)
            ->map(fn ($group) => trim((string) $group))
            ->filter(fn (string $group): bool => $group !== '' && str_starts_with($group, '@'))
            ->merge($normalizedUserGroups)
            ->unique()
            ->sort()
            ->values()
            ->all();

        $this->d3GruppenOptionen = $options;
        $this->d3GruppenAuswahl = $normalizedUserGroups->values()->all();
    }

    /**
     * @return array<int, string>
     */
    private function normalizeD3GruppenAuswahl(): array
    {
        return collect($this->d3GruppenAuswahl)
            ->map(fn ($group) => trim((string) $group))
            ->filter(fn (string $group): bool => $group !== '' && str_starts_with($group, '@'))
            ->unique()
            ->values()
            ->all();
    }

    private function soapUserGroupsCacheTtlSeconds(): int
    {
        $hours = IntranetAppBestellungenSettings::resolvedAppSettings()->d3SoapUserGroupsCacheTtlStunden;

        return max(1, (int) $hours) * 3600;
    }

    private function soapAllGroupsCacheTtlSeconds(): int
    {
        $hours = IntranetAppBestellungenSettings::resolvedAppSettings()->d3SoapAllGroupsCacheTtlStunden;

        return max(1, (int) $hours) * 3600;
    }
}
