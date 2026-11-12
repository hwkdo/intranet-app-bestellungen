<?php

declare(strict_types=1);

namespace Hwkdo\IntranetAppBestellungen\Livewire\Apps\Bestellungen;

use Flux\Flux;
use Hwkdo\D3RestLaravel\Facades\D3RestLaravel;
use Hwkdo\IntranetAppBestellungen\Data\NachtraeglicheBestellungEingabe;
use Hwkdo\IntranetAppBestellungen\Enums\Auszahlungsart;
use Hwkdo\IntranetAppBestellungen\Enums\AuszahlungsEmpfaenger;
use Hwkdo\IntranetAppBestellungen\Exceptions\WorkflowException;
use Hwkdo\IntranetAppBestellungen\Jobs\ExtractKassenbonJob;
use Hwkdo\IntranetAppBestellungen\Livewire\Apps\Bestellungen\Concerns\MeldetFehlendenLieferant;
use Hwkdo\IntranetAppBestellungen\Models\IntranetAppBestellungenSettings;
use Hwkdo\IntranetAppBestellungen\Models\Kassenbon;
use Hwkdo\IntranetAppBestellungen\Models\KostenstelleCache;
use Hwkdo\IntranetAppBestellungen\Models\LieferantCache;
use Hwkdo\IntranetAppBestellungen\Models\Projekt;
use Hwkdo\IntranetAppBestellungen\Services\Lieferant\LieferantSuggestionsService;
use Hwkdo\IntranetAppBestellungen\Services\NachtraeglicheBestellungService;
use Hwkdo\IntranetAppBestellungen\Services\Stammdaten\StammdatenSyncService;
use Hwkdo\IntranetAppBestellungen\Services\WertgrenzenService;
use Hwkdo\IntranetAppBestellungen\Support\D3GruppenOptionSort;
use Illuminate\Contracts\View\View;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;
use Livewire\WithFileUploads;

#[Title('Bestellungen – Nachträgliche Bestellung')]
class ErstellenNachtraeglich extends Component
{
    use MeldetFehlendenLieferant {
        meldeFehlendenLieferant as meldeFehlendenLieferantAusDemBestand;
    }
    use WithFileUploads;

    private const KOSTENSTELLEN_LIMIT = 30;

    public ?int $kassenbonId = null;

    public ?TemporaryUploadedFile $kassenbonDatei = null;

    public ?string $extractionStatus = null;

    public ?string $extractionSchritt = null;

    public ?string $extractionDateiname = null;

    public ?int $extractionGestartetAm = null;

    public ?string $extractionError = null;

    public bool $uebernommen = false;

    public bool $summenAbweichungBestaetigt = false;

    /** @var list<string> */
    public array $erkannt = [];

    public ?string $lieferantennummer = null;

    public ?string $lieferantenname = null;

    public string $lieferantSearch = '';

    public ?string $erkannterLieferantenname = null;

    public ?string $auszahlungsEmpfaenger = null;

    public ?string $auszahlungsArt = null;

    public bool $auszahlungBestaetigt = false;

    public string $begruendungVorlage = '';

    public string $fehlenderLieferantName = '';

    public string $fehlenderLieferantAdresse = '';

    public string $fehlenderLieferantIban = '';

    public string $fehlenderLieferantWebseite = '';

    public string $belegnummer = '0';

    public ?string $belegdatum = null;

    public ?string $nettobetrag = null;

    public ?string $steuerbetrag = null;

    public ?string $bruttobetrag = null;

    public ?string $zahlungsart = null;

    public ?string $betreff = null;

    public ?string $begruendung = null;

    public ?string $kostenstelle = null;

    public string $kostenstelleSearch = '';

    public int $haushaltsjahr;

    public ?int $projektId = null;

    /** @var array<int, array<string, mixed>> */
    public array $positionen = [];

    /** @var array<int, array<string, mixed>> */
    public array $kontierung = [];

    /** @var array<int, string> */
    public array $d3GruppenAuswahl = [];

    /** @var array<int, string> */
    public array $d3GruppenOptionen = [];

    public function mount(?string $beleg = null): void
    {
        app(StammdatenSyncService::class)->syncIfEmpty();

        $this->haushaltsjahr = (int) date('Y');
        $this->positionen = [$this->leerePosition()];
        $this->kontierung = [$this->leereKontierung()];
        $this->loadD3GruppenAuswahl();
        $this->resolveProjektIdFromRequest();

        if ($beleg !== null && $beleg !== '') {
            $this->kassenbonId = (int) $beleg;
            $this->ladeKassenbon();
        }
    }

    public function updatedKassenbonDatei(): void
    {
        if ($this->kassenbonDatei instanceof UploadedFile) {
            $this->hochladen();
        }
    }

    public function hochladen(): void
    {
        if ($this->kassenbonId !== null) {
            return;
        }

        $maxKb = (int) config('intranet-app-bestellungen.api.max_upload_kb', 10240);

        $this->validate([
            'kassenbonDatei' => ['required', 'file', 'mimes:pdf,jpg,jpeg,png,webp', 'max:'.$maxKb],
        ]);

        $kassenbon = Kassenbon::query()->create([
            'user_id' => Auth::id(),
            'datei_pfad' => 'pending',
            'extraction_status' => 'pending',
        ]);

        $stored = $this->kassenbonDatei->store('bestellungen/kassenbons/'.$kassenbon->getKey(), 'local');
        $kassenbon->forceFill([
            'datei_pfad' => $stored,
            'original_name' => $this->kassenbonDatei->getClientOriginalName(),
            'mime' => $this->kassenbonDatei->getMimeType(),
        ])->save();

        ExtractKassenbonJob::dispatch($kassenbon->getKey());

        $this->redirectRoute('apps.bestellungen.erstellen.nachtraeglich', ['beleg' => $kassenbon->getKey()]);
    }

    public function pruefeAnalyse(): void
    {
        $this->ladeKassenbon();
    }

    public function auszahlungUebernehmen(): void
    {
        if ($this->auszahlungsEmpfaenger === AuszahlungsEmpfaenger::Lieferant->value) {
            $this->auszahlungsArt = Auszahlungsart::Ueberweisung->value;
        }

        $this->validate([
            'auszahlungsEmpfaenger' => ['required', Rule::enum(AuszahlungsEmpfaenger::class)],
            'auszahlungsArt' => ['required', Rule::enum(Auszahlungsart::class)],
        ], [
            'auszahlungsEmpfaenger.required' => 'Bitte auswählen, wer das Geld erhält.',
            'auszahlungsArt.required' => 'Bitte Barauszahlung oder Überweisung auswählen.',
        ]);

        $this->begruendungAusVorlageSetzen();
        $this->auszahlungBestaetigt = true;
        $this->speichereAuszahlung();
    }

    public function auszahlungAendern(): void
    {
        $this->auszahlungBestaetigt = false;

        if ($this->kassenbonId === null) {
            return;
        }

        Kassenbon::query()->whereKey($this->kassenbonId)->update([
            'auszahlungsempfaenger' => null,
            'auszahlungsart' => null,
        ]);
    }

    public function meldeFehlendenLieferant(): void
    {
        $this->meldeFehlendenLieferantAusDemBestand();
        $this->aktualisiereAuszahlungsBegruendung();
    }

    public function updatedLieferantennummer(?string $nummer): void
    {
        if (! $nummer) {
            $this->lieferantenname = null;
            $this->aktualisiereAuszahlungsBegruendung();

            return;
        }

        $this->fillLieferantennameFromCache($nummer);
        $this->aktualisiereAuszahlungsBegruendung();
    }

    public function updatedBelegdatum(?string $datum): void
    {
        if (filled($datum) && strlen((string) $datum) >= 4) {
            $jahr = (int) substr((string) $datum, 0, 4);
            if ($jahr >= 2000 && $jahr <= 2100) {
                $this->haushaltsjahr = $jahr;
            }
        }
    }

    public function addPosition(): void
    {
        $this->positionen[] = $this->leerePosition();
    }

    public function removePosition(int $index): void
    {
        unset($this->positionen[$index]);
        $this->positionen = array_values($this->positionen);
        if ($this->positionen === []) {
            $this->positionen = [$this->leerePosition()];
        }
    }

    public function abschliessen(): void
    {
        $this->normalizeBetraege();
        $this->ensureLieferantennameFromCache();
        $this->validate($this->rules());

        if ($this->summenWeichenAb() && ! $this->summenAbweichungBestaetigt) {
            $this->addError('summenAbweichungBestaetigt', 'Die Summen weichen ab. Bitte prüfen oder die Abweichung bestätigen.');

            return;
        }

        $user = Auth::user();
        if ($user === null || ! app(WertgrenzenService::class)->darfBestellen($user, (float) $this->bruttobetrag)) {
            $this->addError('bruttobetrag', 'Sie sind nicht berechtigt, in dieser Betragsklasse zu bestellen.');

            return;
        }

        $kassenbon = $this->kassenbonOderAbort();

        try {
            $bestellung = app(NachtraeglicheBestellungService::class)->abschliessen(
                $kassenbon,
                $user,
                $this->eingabe(),
            );
        } catch (WorkflowException $e) {
            $kassenbon->refresh();
            Flux::toast(heading: 'D3-Übertragung', text: $e->getMessage(), variant: 'error');
            if ($kassenbon->bestellung_id) {
                $this->redirectRoute('apps.bestellungen.detail', ['bestellung' => $kassenbon->bestellung_id]);
            }

            return;
        }

        Flux::toast(text: 'Nachträgliche Bestellung abgeschlossen.', variant: 'success');
        $this->redirectRoute('apps.bestellungen.detail', ['bestellung' => $bestellung]);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'auszahlungsEmpfaenger' => ['required', Rule::enum(AuszahlungsEmpfaenger::class)],
            'auszahlungsArt' => [
                'required',
                Rule::enum(Auszahlungsart::class),
                function (string $attribute, mixed $value, \Closure $fail): void {
                    if ($this->auszahlungsEmpfaenger === AuszahlungsEmpfaenger::Lieferant->value && $value === Auszahlungsart::Barauszahlung->value) {
                        $fail('An den Lieferanten wird nur per Überweisung gezahlt.');
                    }
                },
            ],
            'lieferantennummer' => [
                'required',
                'string',
                'max:50',
                function (string $attribute, mixed $value, \Closure $fail): void {
                    if (! filled($value)) {
                        return;
                    }

                    $nummer = (string) $value;
                    $bekannt = LieferantCache::query()->where('lieferantennummer', $nummer)->exists();
                    $platzhalter = $nummer === (string) IntranetAppBestellungenSettings::resolvedAppSettings()->unbekannterLieferantennummer;

                    if (! $bekannt && ! $platzhalter) {
                        $fail('Dieser Name steht nicht in den Lieferanten. Bitte einen Eintrag aus der Liste wählen oder „Lieferant fehlt“ verwenden.');
                    }
                },
            ],
            'lieferantenname' => ['required', 'string', 'max:255'],
            'belegnummer' => ['required', 'string', 'max:100'],
            'belegdatum' => ['required', 'date'],
            'nettobetrag' => ['required', 'numeric'],
            'steuerbetrag' => ['required', 'numeric'],
            'bruttobetrag' => ['required', 'numeric'],
            'zahlungsart' => ['nullable', 'string', 'max:50'],
            'betreff' => ['nullable', 'string', 'max:255'],
            'begruendung' => ['required', 'string', 'min:10'],
            'kostenstelle' => ['required', 'string', 'max:50'],
            'haushaltsjahr' => ['required', 'integer', 'min:2000', 'max:2100'],
            'd3GruppenAuswahl' => ['required', 'array', 'min:1'],
            'd3GruppenAuswahl.*' => ['required', 'string', 'max:255'],
            'positionen' => ['required', 'array', 'min:1'],
            'positionen.*.bezeichnung' => ['required', 'string', 'max:255'],
            'positionen.*.menge' => ['required', 'numeric', 'not_in:0'],
            'positionen.*.preis' => ['required', 'numeric'],
            'positionen.*.einheit' => ['nullable', 'string', 'max:20'],
            'positionen.*.art_nr' => ['nullable', 'string', 'max:100'],
            'projektId' => [
                'nullable',
                'integer',
                function (string $attribute, mixed $value, \Closure $fail): void {
                    if ($value === null || $value === '') {
                        return;
                    }

                    $exists = Projekt::query()
                        ->forUser((int) Auth::id())
                        ->whereKey((int) $value)
                        ->exists();

                    if (! $exists) {
                        $fail('Das gewählte Projekt ist nicht verfügbar.');
                    }
                },
            ],
            'kontierung.*.kostenstelle' => ['nullable', 'string', 'max:50'],
            'kontierung.*.kursnummer' => ['nullable', 'string', 'max:100'],
            'kontierung.*.raum' => ['nullable', 'string', 'max:100'],
            'kontierung.*.aufteilung' => ['nullable', 'numeric', 'min:0', 'max:100'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'lieferantennummer.required' => 'Bitte einen Lieferanten wählen. Wenn er nicht in der Liste steht, „Lieferant fehlt“ verwenden.',
            'auszahlungsEmpfaenger.required' => 'Bitte auswählen, wer das Geld erhält.',
            'auszahlungsArt.required' => 'Bitte Barauszahlung oder Überweisung auswählen.',
        ];
    }

    public function summenWeichenAb(): bool
    {
        $brutto = round((float) $this->bruttobetrag, 2);
        $nettoSteuer = round((float) $this->nettobetrag + (float) $this->steuerbetrag, 2);
        $positionen = round($this->positionenSumme(), 2);

        return abs($positionen - $brutto) > 0.05 || abs($nettoSteuer - $brutto) > 0.05;
    }

    public function positionenSumme(): float
    {
        return (float) collect($this->positionen)
            ->sum(fn (array $position): float => (float) ($position['menge'] ?? 0) * (float) ($position['preis'] ?? 0));
    }

    public function feldErkannt(string $feld): bool
    {
        return in_array($feld, $this->erkannt, true);
    }

    #[Computed]
    public function analyseLaeuft(): bool
    {
        return in_array($this->extractionStatus, ['pending', 'processing'], true);
    }

    /**
     * @return list<array{key: string, label: string, hinweis: string, zustand: string}>
     */
    #[Computed]
    public function analyseSchritte(): array
    {
        $reihenfolge = ['wartet', 'lesen', 'erkennen', 'lieferant'];
        $aktuell = $this->extractionStatus === 'pending'
            ? 'wartet'
            : (in_array($this->extractionSchritt, ['lesen', 'erkennen', 'lieferant'], true) ? $this->extractionSchritt : 'lesen');
        $aktuellIndex = (int) array_search($aktuell, $reihenfolge, true);

        $texte = [
            'wartet' => ['In der Warteschlange', 'Die Datei ist angekommen und wartet auf den Start.'],
            'lesen' => ['Bon wird gelesen', 'Text und Beträge werden vom Beleg gelesen. Bei einem Foto dauert das am längsten.'],
            'erkennen' => ['Angaben werden erkannt', 'Lieferant, Datum, Positionen und Summen werden zusammengestellt.'],
            'lieferant' => ['Lieferant wird gesucht', 'Der erkannte Name wird mit den vorhandenen Lieferanten abgeglichen.'],
        ];

        $schritte = [];
        foreach ($reihenfolge as $index => $key) {
            $schritte[] = [
                'key' => $key,
                'label' => $texte[$key][0],
                'hinweis' => $texte[$key][1],
                'zustand' => $index < $aktuellIndex ? 'fertig' : ($index === $aktuellIndex ? 'aktiv' : 'offen'),
            ];
        }

        return $schritte;
    }

    #[Computed]
    public function lieferantenSuggestions(): Collection
    {
        return app(LieferantSuggestionsService::class)->suche($this->lieferantSearch, $this->lieferantennummer);
    }

    #[Computed]
    public function kostenstellenSuggestions(): Collection
    {
        $term = trim($this->kostenstelleSearch);

        $results = KostenstelleCache::query()
            ->aktiv()
            ->when($term !== '', function ($query) use ($term): void {
                $like = '%'.$term.'%';
                $query->where(function ($inner) use ($like): void {
                    $inner->where('kostenstelle', 'like', $like)
                        ->orWhere('bezeichnung', 'like', $like);
                });
            })
            ->orderBy('kostenstelle')
            ->limit(self::KOSTENSTELLEN_LIMIT)
            ->get();

        if ($this->kostenstelle && ! $results->firstWhere('kostenstelle', $this->kostenstelle)) {
            $selected = KostenstelleCache::query()->where('kostenstelle', $this->kostenstelle)->first();
            if ($selected) {
                $results->prepend($selected);
            }
        }

        return $results;
    }

    #[Computed]
    public function d3GruppenOptionenSortiert(): array
    {
        return D3GruppenOptionSort::mitAuswahlZuerst($this->d3GruppenOptionen, $this->d3GruppenAuswahl);
    }

    #[Computed]
    public function userHasProjekte(): bool
    {
        return Projekt::query()->forUser((int) Auth::id())->exists();
    }

    /**
     * @return Collection<int, Projekt>
     */
    #[Computed]
    public function projektSuggestions(): Collection
    {
        return Projekt::query()->forUser((int) Auth::id())->orderBy('name')->get();
    }

    public function render(): View
    {
        return view('intranet-app-bestellungen::livewire.apps.bestellungen.erstellen-nachtraeglich');
    }

    private function ladeKassenbon(): void
    {
        if ($this->kassenbonId === null) {
            return;
        }

        $kassenbon = Kassenbon::query()->find($this->kassenbonId);
        if (! $kassenbon || (int) $kassenbon->user_id !== (int) Auth::id()) {
            abort(404);
        }

        if ($kassenbon->bestellung_id) {
            $this->redirectRoute('apps.bestellungen.detail', ['bestellung' => $kassenbon->bestellung_id]);

            return;
        }

        $this->extractionStatus = $kassenbon->extraction_status;
        $this->extractionSchritt = $kassenbon->extraction_payload['schritt'] ?? null;
        $this->extractionDateiname = $kassenbon->original_name;
        $this->extractionGestartetAm = $kassenbon->created_at?->getTimestamp();
        $this->extractionError = $kassenbon->extraction_error;

        if ($kassenbon->extraction_status === 'done' && ! $this->uebernommen) {
            $this->uebernimm($kassenbon);
            $this->uebernommen = true;
        }

        $this->stelleAuszahlungWiederHer($kassenbon);
    }

    private function kassenbonOderAbort(): Kassenbon
    {
        $kassenbon = Kassenbon::query()->find($this->kassenbonId);
        if (! $kassenbon || (int) $kassenbon->user_id !== (int) Auth::id()) {
            abort(404);
        }

        return $kassenbon;
    }

    private function uebernimm(Kassenbon $kassenbon): void
    {
        $payload = is_array($kassenbon->extraction_payload) ? $kassenbon->extraction_payload : [];
        $structured = is_array($payload['structured'] ?? null) ? $payload['structured'] : [];
        $lieferant = is_array($payload['lieferant'] ?? null) ? $payload['lieferant'] : [];

        $this->merke('lieferant', $lieferant['lieferantennummer'] ?? null);
        if (filled($lieferant['lieferantennummer'] ?? null)) {
            $this->lieferantennummer = (string) $lieferant['lieferantennummer'];
            $this->lieferantenname = (string) ($lieferant['lieferantenname'] ?? $structured['supplier_name'] ?? '');
        } else {
            $this->erkannterLieferantenname = filled($lieferant['erkannter_name'] ?? null)
                ? (string) $lieferant['erkannter_name']
                : (filled($structured['supplier_name'] ?? null) ? (string) $structured['supplier_name'] : null);
        }

        $this->setzeWennErkannt('belegnummer', (string) ($structured['receipt_number'] ?? '0'));
        $this->setzeWennErkannt('belegdatum', $structured['receipt_date'] ?? null);
        $this->setzeWennErkannt('nettobetrag', $this->alsEingabe($structured['net_amount'] ?? null));
        $this->setzeWennErkannt('steuerbetrag', $this->alsEingabe($structured['tax_amount'] ?? null));
        $this->setzeWennErkannt('bruttobetrag', $this->alsEingabe($structured['amount'] ?? null));
        $this->setzeWennErkannt('zahlungsart', $structured['payment_method'] ?? null);

        if (filled($this->belegdatum)) {
            $this->updatedBelegdatum($this->belegdatum);
        }

        $zeilen = [];
        foreach (is_array($structured['line_items'] ?? null) ? $structured['line_items'] : [] as $line) {
            if (! is_array($line) || ! filled($line['description'] ?? null)) {
                continue;
            }

            $menge = $line['quantity'] ?? 1;
            $preis = $line['unit_price'] ?? null;
            if ($preis === null && isset($line['line_total'], $menge) && (float) $menge !== 0.0) {
                $preis = round((float) $line['line_total'] / (float) $menge, 2);
            }

            $zeilen[] = [
                'bezeichnung' => (string) $line['description'],
                'menge' => $menge,
                'einheit' => $line['unit'] ?? 'Stk',
                'art_nr' => $line['article_number'] ?? null,
                'preis' => $preis ?? 0,
            ];
        }

        if ($zeilen !== []) {
            $this->positionen = $zeilen;
            $this->erkannt[] = 'positionen';
        }

        if ($this->belegnummer === '') {
            $this->belegnummer = '0';
        }
    }

    private function stelleAuszahlungWiederHer(Kassenbon $kassenbon): void
    {
        if ($kassenbon->auszahlungsart === null || $kassenbon->auszahlungsempfaenger === null) {
            return;
        }

        $this->auszahlungsEmpfaenger = $kassenbon->auszahlungsempfaenger->value;
        $this->auszahlungsArt = $kassenbon->auszahlungsart->value;
        $this->auszahlungBestaetigt = true;

        if (! filled($this->begruendung)) {
            $this->begruendungAusVorlageSetzen();
        }
    }

    private function begruendungAusVorlageSetzen(): void
    {
        $text = $this->auszahlungsBegruendung();
        if (! filled($this->begruendung) || $this->begruendung === $this->begruendungVorlage) {
            $this->begruendung = $text;
        }

        $this->begruendungVorlage = $text;
    }

    private function aktualisiereAuszahlungsBegruendung(): void
    {
        if (! $this->auszahlungBestaetigt || $this->auszahlungsEmpfaenger !== AuszahlungsEmpfaenger::Lieferant->value) {
            return;
        }

        if ($this->begruendung !== $this->begruendungVorlage) {
            return;
        }

        $this->begruendungAusVorlageSetzen();
    }

    private function auszahlungsBegruendung(): string
    {
        $empfaenger = AuszahlungsEmpfaenger::from((string) $this->auszahlungsEmpfaenger);
        $art = Auszahlungsart::from((string) $this->auszahlungsArt);

        if ($empfaenger === AuszahlungsEmpfaenger::Lieferant) {
            return 'Auszahlung per Überweisung an Lieferanten '.$this->lieferantenNameFuerAuszahlung().'.';
        }

        $name = $this->mitarbeiterName();

        return $art === Auszahlungsart::Barauszahlung
            ? 'Auszahlung per Barauszahlung an '.$name.'.'
            : 'Auszahlung per Überweisung auf das Konto von '.$name.'.';
    }

    private function mitarbeiterName(): string
    {
        $user = Auth::user();
        $name = trim(((string) ($user?->vorname ?? '')).' '.((string) ($user?->nachname ?? '')));

        return $name !== '' ? $name : trim((string) ($user?->username ?? ''));
    }

    private function lieferantenNameFuerAuszahlung(): string
    {
        if (filled($this->lieferantenname)) {
            return (string) $this->lieferantenname;
        }

        if (filled($this->erkannterLieferantenname)) {
            return (string) $this->erkannterLieferantenname;
        }

        return 'den Lieferanten';
    }

    private function speichereAuszahlung(): void
    {
        if ($this->kassenbonId === null) {
            return;
        }

        Kassenbon::query()->whereKey($this->kassenbonId)->update([
            'auszahlungsempfaenger' => $this->auszahlungsEmpfaenger,
            'auszahlungsart' => $this->auszahlungsArt,
        ]);
    }

    private function setzeWennErkannt(string $feld, mixed $wert): void
    {
        if ($wert === null || $wert === '') {
            return;
        }

        $this->{$feld} = is_string($wert) ? $wert : (string) $wert;
        $this->merke($feld, $wert);
    }

    private function merke(string $feld, mixed $wert): void
    {
        if ($wert === null || $wert === '') {
            return;
        }

        $this->erkannt[] = $feld;
    }

    private function alsEingabe(mixed $wert): ?string
    {
        if ($wert === null || $wert === '') {
            return null;
        }

        return (string) $wert;
    }

    private function normalizeBetraege(): void
    {
        foreach (['nettobetrag', 'steuerbetrag', 'bruttobetrag'] as $feld) {
            $this->{$feld} = str_replace(',', '.', (string) $this->{$feld});
        }

        foreach ($this->positionen as $index => $position) {
            $this->positionen[$index]['menge'] = str_replace(',', '.', (string) ($position['menge'] ?? ''));
            $this->positionen[$index]['preis'] = str_replace(',', '.', (string) ($position['preis'] ?? ''));
        }
    }

    private function eingabe(): NachtraeglicheBestellungEingabe
    {
        return new NachtraeglicheBestellungEingabe(
            lieferantennummer: (string) $this->lieferantennummer,
            lieferantenname: (string) $this->lieferantenname,
            belegnummer: $this->belegnummer !== '' ? $this->belegnummer : '0',
            belegdatum: (string) $this->belegdatum,
            netto: (float) $this->nettobetrag,
            steuer: (float) $this->steuerbetrag,
            brutto: (float) $this->bruttobetrag,
            zahlungsart: $this->zahlungsart,
            betreff: $this->betreff,
            begruendung: (string) $this->begruendung,
            kostenstelle: (string) $this->kostenstelle,
            haushaltsjahr: $this->haushaltsjahr,
            projektId: $this->projektId,
            gruppen: $this->normalizeD3GruppenAuswahl(),
            kontierung: $this->normalizeKontierung(),
            positionen: $this->positionen,
            lieferanschriftUserId: Auth::id(),
            auszahlungsEmpfaenger: $this->auszahlungsEmpfaenger,
            auszahlungsArt: $this->auszahlungsArt,
        );
    }

    /**
     * @return array<string, mixed>
     */
    private function leerePosition(): array
    {
        return [
            'bezeichnung' => '',
            'menge' => 1,
            'einheit' => 'Stk',
            'art_nr' => null,
            'preis' => 0,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function leereKontierung(): array
    {
        return [
            'kostenstelle' => $this->kostenstelle,
            'kursnummer' => null,
            'raum' => null,
            'aufteilung' => 100.0,
        ];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function normalizeKontierung(): array
    {
        $rows = collect($this->kontierung)
            ->map(fn (array $row): array => [
                'kostenstelle' => $row['kostenstelle'] ?: $this->kostenstelle,
                'kursnummer' => $row['kursnummer'] ?? null,
                'raum' => $row['raum'] ?? null,
                'aufteilung' => isset($row['aufteilung']) ? (float) $row['aufteilung'] : 100.0,
            ])
            ->filter(fn (array $row): bool => filled($row['kostenstelle']))
            ->values()
            ->all();

        if ($rows === [] && filled($this->kostenstelle)) {
            return [[
                'kostenstelle' => $this->kostenstelle,
                'kursnummer' => null,
                'raum' => null,
                'aufteilung' => 100.0,
            ]];
        }

        return $rows;
    }

    private function resolveProjektIdFromRequest(): void
    {
        $this->projektId = null;

        if (! request()->filled('projekt')) {
            return;
        }

        $id = (int) request()->query('projekt');
        if ($id <= 0) {
            return;
        }

        if (Projekt::query()->forUser((int) Auth::id())->whereKey($id)->exists()) {
            $this->projektId = $id;
            $projekt = Projekt::query()->find($id);
            if ($projekt && filled($projekt->begruendung) && ! filled($this->begruendung)) {
                $this->begruendung = (string) $projekt->begruendung;
            }
        }
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
            $userGroups = D3RestLaravel::getUserInGroupsSoapCached($username, $this->soapTtl('d3SoapUserGroupsCacheTtlStunden'));
        } catch (\Throwable $e) {
            Log::warning('bestellungen.kassenbon.d3_groups_user.failed', ['error' => $e->getMessage()]);
        }

        $allGroups = [];
        try {
            $allGroups = D3RestLaravel::getD3GroupsSoapCached($this->soapTtl('d3SoapAllGroupsCacheTtlStunden'));
        } catch (\Throwable $e) {
            Log::warning('bestellungen.kassenbon.d3_groups_all.failed', ['error' => $e->getMessage()]);
        }

        $normalizedUserGroups = collect($userGroups)
            ->map(fn ($group) => trim((string) $group))
            ->filter(fn (string $group): bool => $group !== '' && str_starts_with($group, '@'))
            ->values();

        if ($normalizedUserGroups->contains('@Dozenten') || $normalizedUserGroups->contains('@D3EDV')) {
            $normalizedUserGroups->prepend('@Rechnungen');
        }

        $normalizedUserGroups = $normalizedUserGroups->unique()->values();

        $this->d3GruppenOptionen = collect($allGroups)
            ->map(fn ($group) => trim((string) $group))
            ->filter(fn (string $group): bool => $group !== '' && str_starts_with($group, '@'))
            ->merge($normalizedUserGroups)
            ->unique()
            ->sort()
            ->values()
            ->all();
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

    private function soapTtl(string $setting): int
    {
        $hours = IntranetAppBestellungenSettings::resolvedAppSettings()->{$setting};

        return max(1, (int) $hours) * 3600;
    }
}
