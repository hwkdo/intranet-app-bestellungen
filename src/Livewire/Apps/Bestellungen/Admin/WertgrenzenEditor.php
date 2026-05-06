<?php

declare(strict_types=1);

namespace Hwkdo\IntranetAppBestellungen\Livewire\Apps\Bestellungen\Admin;

use App\Models\Gvp;
use App\Models\User;
use Flux\Flux;
use Hwkdo\IntranetAppBestellungen\Data\AppSettings;
use Hwkdo\IntranetAppBestellungen\Models\IntranetAppBestellungenSettings;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Computed;
use Livewire\Component;
use Spatie\Permission\Models\Role;

class WertgrenzenEditor extends Component
{
    /** @var array<int, array<string, mixed>> */
    public array $stufen = [];

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

    public function speichern(): void
    {
        $this->validate();

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

        Flux::toast(
            heading: 'Wertgrenzen gespeichert',
            text: count($this->stufen).' Stufen aktiv.',
            variant: 'success',
        );
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
