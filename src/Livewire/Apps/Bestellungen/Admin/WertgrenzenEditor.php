<?php

declare(strict_types=1);

namespace Hwkdo\IntranetAppBestellungen\Livewire\Apps\Bestellungen\Admin;

use App\Models\Gvp;
use Flux\Flux;
use Hwkdo\IntranetAppBestellungen\Data\AppSettings;
use Hwkdo\IntranetAppBestellungen\Models\IntranetAppBestellungenSettings;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Validator;
use JsonException;
use Livewire\Attributes\Computed;
use Livewire\Component;
use Spatie\Permission\Models\Role;

class WertgrenzenEditor extends Component
{
    /** @var array<int, array<string, mixed>> */
    public array $stufen = [];

    /** JSON-Zeichenkette zum Import nur der Freigabe-Stufen (Dev → Prod). */
    public string $freigabeStufenJsonImport = '';

    public function mount(): void
    {
        $settings = IntranetAppBestellungenSettings::resolvedAppSettings();
        $this->stufen = $settings->freigabeStufen;
    }

    public function rules(): array
    {
        return [
            'stufen' => ['required', 'array', 'min:1'],
            'stufen.*.bezeichnung' => ['required', 'string', 'max:100'],
            'stufen.*.vonBetrag' => ['required', 'numeric', 'min:0'],
            'stufen.*.bisBetrag' => ['nullable', 'numeric', 'min:0'],
            'stufen.*.berechtigteAttribute' => ['array'],
            'stufen.*.berechtigteAttribute.*' => ['string'],
            'stufen.*.berechtigteRollen' => ['array'],
            'stufen.*.berechtigteRollen.*' => ['string'],
            'stufen.*.textBerechtigt' => ['nullable', 'string', 'max:255'],
            'stufen.*.textFreigeber1' => ['nullable', 'string', 'max:255'],
            'stufen.*.textFreigeber2' => ['nullable', 'string', 'max:255'],
            'stufen.*.freigabe1Regeln' => ['array'],
            'stufen.*.freigabe1Regeln.*.typ' => ['required', 'string', 'in:if_attribute,if_rolle,default'],
            'stufen.*.freigabe1Regeln.*.bedingung' => ['nullable', 'string'],
            'stufen.*.freigabe1Regeln.*.keinFreigeber' => ['boolean'],
            'stufen.*.freigabe1Regeln.*.quelleTyp' => ['required', 'string', 'in:single,multi,gruppe'],
            'stufen.*.freigabe1Regeln.*.quelle' => ['required', 'string'],
            'stufen.*.freigabe1Regeln.*.excludeAttribute' => ['array'],
            'stufen.*.freigabe2Regeln' => ['array'],
            'stufen.*.freigabe2Regeln.*.typ' => ['required', 'string', 'in:if_attribute,if_rolle,default'],
            'stufen.*.freigabe2Regeln.*.bedingung' => ['nullable', 'string'],
            'stufen.*.freigabe2Regeln.*.keinFreigeber' => ['boolean'],
            'stufen.*.freigabe2Regeln.*.quelleTyp' => ['required', 'string', 'in:single,multi,gruppe'],
            'stufen.*.freigabe2Regeln.*.quelle' => ['required', 'string'],
            'stufen.*.freigabe2Regeln.*.excludeAttribute' => ['array'],
        ];
    }

    public function addStufe(): void
    {
        $this->stufen[] = [
            'bezeichnung' => 'Neue Stufe',
            'vonBetrag' => 0,
            'bisBetrag' => null,
            'berechtigteAttribute' => [],
            'berechtigteRollen' => [],
            'textBerechtigt' => null,
            'textFreigeber1' => null,
            'textFreigeber2' => null,
            'freigabe1Regeln' => [],
            'freigabe2Regeln' => [],
        ];
    }

    public function removeStufe(int $idx): void
    {
        unset($this->stufen[$idx]);
        $this->stufen = array_values($this->stufen);
    }

    public function addFreigabe1Regel(int $stufenIdx): void
    {
        $this->stufen[$stufenIdx]['freigabe1Regeln'][] = $this->leerenRegelArray();
        $this->stufen = $this->stufen;
    }

    public function removeFreigabe1Regel(int $stufenIdx, int $regelIdx): void
    {
        unset($this->stufen[$stufenIdx]['freigabe1Regeln'][$regelIdx]);
        $this->stufen[$stufenIdx]['freigabe1Regeln'] = array_values($this->stufen[$stufenIdx]['freigabe1Regeln']);
        $this->stufen = $this->stufen;
    }

    public function addFreigabe2Regel(int $stufenIdx): void
    {
        $this->stufen[$stufenIdx]['freigabe2Regeln'][] = $this->leerenRegelArray();
        $this->stufen = $this->stufen;
    }

    public function removeFreigabe2Regel(int $stufenIdx, int $regelIdx): void
    {
        unset($this->stufen[$stufenIdx]['freigabe2Regeln'][$regelIdx]);
        $this->stufen[$stufenIdx]['freigabe2Regeln'] = array_values($this->stufen[$stufenIdx]['freigabe2Regeln']);
        $this->stufen = $this->stufen;
    }

    /**
     * Aktuelle Freigabe-Stufen als formatiertes JSON (nur dieses Array, kein vollständiges AppSettings).
     */
    public function freigabeStufenAlsFormatiertesJson(): string
    {
        return json_encode($this->stufen, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
    }

    public function freigabeStufenAusJsonUebernehmenUndSpeichern(): void
    {
        $this->resetErrorBag('freigabeStufenJsonImport');

        $trimmed = trim($this->freigabeStufenJsonImport);
        if ($trimmed === '') {
            $this->addError('freigabeStufenJsonImport', 'Bitte JSON einfügen.');
            Flux::toast(
                heading: 'Import',
                text: 'Das JSON-Feld ist leer.',
                variant: 'warning',
            );

            return;
        }

        try {
            $decoded = json_decode($trimmed, true, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException $e) {
            $this->addError('freigabeStufenJsonImport', 'Ungültiges JSON: '.$e->getMessage());
            Flux::toast(
                heading: 'JSON ungültig',
                text: $e->getMessage(),
                variant: 'danger',
            );

            return;
        }

        if (! is_array($decoded)) {
            $this->addError('freigabeStufenJsonImport', 'Das JSON muss ein Array von Freigabe-Stufen sein.');
            Flux::toast(
                heading: 'Import',
                text: 'Erwartet wird ein JSON-Array (Liste von Stufen).',
                variant: 'danger',
            );

            return;
        }

        if (! array_is_list($decoded)) {
            $this->addError('freigabeStufenJsonImport', 'Das JSON muss ein Array (Liste) sein, kein Objekt.');
            Flux::toast(
                heading: 'Import',
                text: 'Bitte ein JSON-Array exportieren, kein Objekt mit benannten Schlüsseln.',
                variant: 'danger',
            );

            return;
        }

        /** @var array<int, array<string, mixed>> $stufen */
        $stufen = $decoded;

        $validator = Validator::make(['stufen' => $stufen], $this->rules());
        if ($validator->fails()) {
            $first = $validator->errors()->first();
            $this->addError('freigabeStufenJsonImport', $first);
            Flux::toast(
                heading: 'Validierung',
                text: $first,
                variant: 'danger',
            );

            return;
        }

        /** @var array<int, array<string, mixed>> $validated */
        $validated = $validator->validated()['stufen'];
        $this->stufen = array_values($validated);
        $this->freigabeStufenJsonImport = '';

        $this->persistiereFreigabeStufenInSettings();

        Flux::toast(
            heading: 'Freigabe-Stufen importiert',
            text: count($this->stufen).' Stufen gespeichert. Rollen-Namen müssen in dieser Umgebung existieren.',
            variant: 'success',
        );
    }

    public function speichern(): void
    {
        $this->validate();

        $this->persistiereFreigabeStufenInSettings();

        Flux::toast(
            heading: 'Wertgrenzen gespeichert',
            text: count($this->stufen).' Stufen aktiv.',
            variant: 'success',
        );
    }

    private function persistiereFreigabeStufenInSettings(): void
    {
        $current = IntranetAppBestellungenSettings::current();
        if ($current) {
            $appSettings = $current->settings instanceof AppSettings ? $current->settings : new AppSettings;
            $newSettings = AppSettings::from(array_merge($appSettings->toArray(), [
                'freigabeStufen' => $this->stufen,
            ]));
            $current->settings = $newSettings;
            $current->save();
        } else {
            IntranetAppBestellungenSettings::create([
                'version' => 1,
                'settings' => AppSettings::from(['freigabeStufen' => $this->stufen])->toArray(),
            ]);
        }
    }

    #[Computed]
    public function rollenOptions(): array
    {
        return Role::query()
            ->orderBy('name')
            ->pluck('name', 'name')
            ->all();
    }

    #[Computed]
    public function userAttributeOptions(): array
    {
        return [
            'ist_dozent' => 'ist_dozent – Dozent (LDAP-Gruppe)',
            'ist_fk' => 'ist_fk – Fachbereichsvorgesetzter (FB)',
            'ist_gl' => 'ist_gl – Gruppenleiter (G)',
            'ist_al' => 'ist_al – Abteilungsleiter (A/Stab)',
            'ist_gf' => 'ist_gf – Geschäftsführer (GB/HGF)',
            'ist_hgf' => 'ist_hgf – Hauptgeschäftsführer (HGF)',
            'ist_sb' => 'ist_sb – Sachbearbeiter',
            'ist_dozent_aber_kein_fbk' => 'ist_dozent_aber_kein_fbk – Dozent ohne FB/AL',
        ];
    }

    #[Computed]
    public function quelleOptions(): array
    {
        $basis = [
            'vorgesetzter' => 'vorgesetzter – Direkter Vorgesetzter (1 Person)',
            'getVorgesetzte' => 'getVorgesetzte – Gesamte Vorgesetztenkette',
            'getAlleVorgesetzte' => 'getAlleVorgesetzte – Alle GVP-Vorgesetzten',
        ];

        $gvpKuerzel = Gvp::select('kuerzel')
            ->distinct()
            ->orderBy('kuerzel')
            ->pluck('kuerzel')
            ->mapWithKeys(fn (string $k): array => [$k => "GVP-Gruppe: {$k}"])
            ->all();

        return array_merge($basis, $gvpKuerzel);
    }

    public function render(): View
    {
        return view('intranet-app-bestellungen::livewire.apps.bestellungen.admin.wertgrenzen-editor');
    }

    /** @return array<string, mixed> */
    private function leerenRegelArray(): array
    {
        return [
            'typ' => 'default',
            'bedingung' => null,
            'keinFreigeber' => false,
            'quelleTyp' => 'single',
            'quelle' => 'vorgesetzter',
            'excludeAttribute' => [],
        ];
    }
}
