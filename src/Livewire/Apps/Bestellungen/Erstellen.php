<?php

declare(strict_types=1);

namespace Hwkdo\IntranetAppBestellungen\Livewire\Apps\Bestellungen;

use Flux\Flux;
use Hwkdo\IntranetAppBestellungen\Enums\AktionTyp;
use Hwkdo\IntranetAppBestellungen\Enums\BestellungStatus;
use Hwkdo\IntranetAppBestellungen\Models\Art;
use Hwkdo\IntranetAppBestellungen\Models\Bestellung;
use Hwkdo\IntranetAppBestellungen\Models\KostenstelleCache;
use Hwkdo\IntranetAppBestellungen\Models\LieferantCache;
use Hwkdo\IntranetAppBestellungen\Models\Position;
use Hwkdo\IntranetAppBestellungen\Services\AngebotsregelService;
use Hwkdo\IntranetAppBestellungen\Services\BenNumberService;
use Hwkdo\IntranetAppBestellungen\Services\BestellungWorkflow;
use Hwkdo\IntranetAppBestellungen\Services\Stammdaten\StammdatenSyncService;
use Hwkdo\IntranetAppBestellungen\Services\WertgrenzenService;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithFileUploads;

#[Title('Bestellungen – Neue Bestellung')]
class Erstellen extends Component
{
    use WithFileUploads;

    public ?string $lieferantennummer = null;

    public ?string $lieferantenname = null;

    public ?string $kostenstelle = null;

    public int $haushaltsjahr;

    public ?string $betreff = null;

    public ?string $begruendung = null;

    /** @var array<int, array<string, mixed>> */
    public array $positionen = [];

    /** @var array<int, array<string, mixed>> */
    public array $kontierung = [];

    /** @var array<int, mixed> */
    public array $positionPdfs = [];

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
    }

    public function rules(): array
    {
        return [
            'lieferantenname' => ['required', 'string', 'max:255'],
            'kostenstelle' => ['required', 'string', 'max:50'],
            'haushaltsjahr' => ['required', 'integer', 'min:2000', 'max:2100'],
            'betreff' => ['nullable', 'string', 'max:255'],
            'begruendung' => ['nullable', 'string'],
            'positionen' => ['required', 'array', 'min:1'],
            'positionen.*.bezeichnung' => ['required', 'string', 'max:255'],
            'positionen.*.menge' => ['required', 'numeric', 'min:0.01'],
            'positionen.*.preis' => ['required', 'numeric', 'min:0'],
            'positionen.*.einheit' => ['nullable', 'string', 'max:20'],
            'positionen.*.art_id' => ['nullable', 'integer'],
            'positionen.*.art_nr' => ['nullable', 'string', 'max:100'],
            'positionPdfs' => ['array'],
            'positionPdfs.*' => ['nullable', 'file', 'mimes:pdf', 'max:10240'],
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
        $stufe = app(WertgrenzenService::class)->stufeFuerBetrag($this->gesamtbetrag());
        if (! $stufe) {
            return 'Keine passende Freigabestufe definiert. Bitte Admin kontaktieren.';
        }

        return sprintf(
            'Stufe "%s" – %s%s',
            $stufe->bezeichnung,
            $stufe->bisBetrag === null
                ? 'unbegrenzt'
                : 'bis '.number_format($stufe->bisBetrag, 2, ',', '.').' €',
            app(WertgrenzenService::class)->zweiteFreigabeNoetig($this->gesamtbetrag())
                ? ' · zweite Freigabe erforderlich'
                : '',
        );
    }

    public function addPosition(): void
    {
        $this->positionen[] = $this->emptyPosition(count($this->positionen) + 1);
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
        return ! empty($this->positionPdfs[$idx]);
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

        $service = app(WertgrenzenService::class);
        $regelService = app(AngebotsregelService::class);
        $workflow = app(BestellungWorkflow::class);

        $bestellung = DB::transaction(function () use ($service, $regelService, $workflow): Bestellung {
            $nummer = app(BenNumberService::class)->next(Auth::user(), $this->haushaltsjahr);

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
                'user_id' => Auth::id(),
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

            $bestellung->refresh();
            $bestellung->refreshGesamtbetrag();

            $stufe = $service->stufeFuerBetrag((float) $bestellung->gesamtbetrag);
            if ($stufe) {
                $erstFreigeber = $service->freigeberFuerBestellung($bestellung)->first();
                if ($erstFreigeber) {
                    $bestellung->freigeber_id = $erstFreigeber->getKey();
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
                text: 'BEN '.$bestellung->nummer.' wurde zur Freigabe weitergeleitet.',
                variant: 'success',
            );
        } catch (\Throwable $e) {
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
            ->when($term !== '', function ($q) use ($term): void {
                $like = '%'.$term.'%';
                $q->where(function ($inner) use ($like): void {
                    $inner->where('lieferantenname', 'like', $like)
                        ->orWhere('lieferantennummer', 'like', $like);
                });
            })
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
}
