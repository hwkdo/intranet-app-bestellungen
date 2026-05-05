<?php

declare(strict_types=1);

namespace Hwkdo\IntranetAppBestellungen\Livewire\Apps\Bestellungen\Admin;

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
            'stufen.*.bisBetrag' => ['nullable', 'numeric', 'min:0'],
            'stufen.*.freigeberUserIds' => ['array'],
            'stufen.*.freigeberUserIds.*' => ['integer'],
            'stufen.*.freigeberRollen' => ['array'],
            'stufen.*.freigeberRollen.*' => ['string'],
            'stufen.*.zweiteFreigabeErforderlich' => ['boolean'],
            'stufen.*.zweiteFreigabeAb' => ['nullable', 'numeric', 'min:0'],
        ];
    }

    public function addStufe(): void
    {
        $this->stufen[] = [
            'bezeichnung' => 'Neue Stufe',
            'bisBetrag' => null,
            'freigeberUserIds' => [],
            'freigeberRollen' => [],
            'zweiteFreigabeErforderlich' => false,
            'zweiteFreigabeAb' => null,
        ];
    }

    public function removeStufe(int $idx): void
    {
        unset($this->stufen[$idx]);
        $this->stufen = array_values($this->stufen);
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
    public function userOptions(): array
    {
        return User::query()
            ->orderBy('nachname')
            ->orderBy('vorname')
            ->get(['id', 'vorname', 'nachname'])
            ->mapWithKeys(fn (User $u): array => [(string) $u->id => $u->name])
            ->all();
    }

    #[Computed]
    public function rollenOptions(): array
    {
        return Role::query()
            ->orderBy('name')
            ->pluck('name', 'name')
            ->all();
    }

    public function render(): View
    {
        return view('intranet-app-bestellungen::livewire.apps.bestellungen.admin.wertgrenzen-editor');
    }
}
