<?php

declare(strict_types=1);

namespace Hwkdo\IntranetAppBestellungen\Livewire\Apps\Bestellungen\Admin;

use Flux\Flux;
use Hwkdo\IntranetAppBestellungen\Data\AppSettings;
use Hwkdo\IntranetAppBestellungen\Models\IntranetAppBestellungenSettings;
use Illuminate\Contracts\View\View;
use Livewire\Component;

class AngebotsregelnEditor extends Component
{
    /** @var array<int, array<string, mixed>> */
    public array $regeln = [];

    public function mount(): void
    {
        $this->regeln = IntranetAppBestellungenSettings::resolvedAppSettings()->angebotsRegeln;
    }

    public function rules(): array
    {
        return [
            'regeln' => ['required', 'array', 'min:1'],
            'regeln.*.abBetrag' => ['required', 'numeric', 'min:0'],
            'regeln.*.mindestAngebote' => ['required', 'integer', 'min:0'],
            'regeln.*.begruendungErlaubt' => ['boolean'],
            'regeln.*.hinweisText' => ['nullable', 'string'],
        ];
    }

    public function addRegel(): void
    {
        $this->regeln[] = [
            'abBetrag' => 0,
            'mindestAngebote' => 0,
            'begruendungErlaubt' => true,
            'hinweisText' => null,
        ];
    }

    public function removeRegel(int $idx): void
    {
        unset($this->regeln[$idx]);
        $this->regeln = array_values($this->regeln);
    }

    public function speichern(): void
    {
        $this->validate();

        $current = IntranetAppBestellungenSettings::current();
        if ($current) {
            $appSettings = $current->settings instanceof AppSettings ? $current->settings : new AppSettings;
            $newSettings = AppSettings::from(array_merge($appSettings->toArray(), [
                'angebotsRegeln' => $this->regeln,
            ]));
            $current->settings = $newSettings;
            $current->save();
        } else {
            IntranetAppBestellungenSettings::create([
                'version' => 1,
                'settings' => AppSettings::from(['angebotsRegeln' => $this->regeln])->toArray(),
            ]);
        }

        Flux::toast(heading: 'Angebotsregeln gespeichert', text: 'Die Angebotsregeln wurden erfolgreich gespeichert.', variant: 'success');
    }

    public function render(): View
    {
        return view('intranet-app-bestellungen::livewire.apps.bestellungen.admin.angebotsregeln-editor');
    }
}
