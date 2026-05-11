<?php

declare(strict_types=1);

namespace Hwkdo\IntranetAppBestellungen\Livewire\Apps\Bestellungen\Admin;

use Flux\Flux;
use Hwkdo\IntranetAppBestellungen\Models\Art;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Computed;
use Livewire\Component;

class ArtenEditor extends Component
{
    public ?int $editId = null;

    public string $bezeichnung = '';

    public ?string $icon = null;

    public bool $aktiv = true;

    public int $sortierung = 0;

    #[Computed]
    public function arten(): \Illuminate\Database\Eloquent\Collection
    {
        return Art::query()->orderBy('sortierung')->orderBy('bezeichnung')->get();
    }

    public function rules(): array
    {
        return [
            'bezeichnung' => ['required', 'string', 'max:100'],
            'icon' => ['nullable', 'string', 'max:50'],
            'aktiv' => ['boolean'],
            'sortierung' => ['integer'],
        ];
    }

    public function edit(int $id): void
    {
        $art = Art::findOrFail($id);
        $this->editId = $art->id;
        $this->bezeichnung = $art->bezeichnung;
        $this->icon = $art->icon;
        $this->aktiv = (bool) $art->aktiv;
        $this->sortierung = (int) $art->sortierung;
        Flux::modal('art-modal')->show();
    }

    public function neu(): void
    {
        $this->reset(['editId', 'bezeichnung', 'icon', 'aktiv', 'sortierung']);
        $this->aktiv = true;
        Flux::modal('art-modal')->show();
    }

    public function speichern(): void
    {
        $this->validate();

        Art::updateOrCreate(
            ['id' => $this->editId],
            [
                'bezeichnung' => $this->bezeichnung,
                'icon' => $this->icon,
                'aktiv' => $this->aktiv,
                'sortierung' => $this->sortierung,
            ],
        );

        Flux::modal('art-modal')->close();
        Flux::toast(heading: 'Art gespeichert', text: 'Die Art wurde erfolgreich gespeichert.', variant: 'success');
        unset($this->arten);
    }

    public function loeschen(int $id): void
    {
        Art::query()->whereKey($id)->delete();
        Flux::toast(heading: 'Art gelöscht', text: 'Die Art wurde erfolgreich gelöscht.', variant: 'success');
        unset($this->arten);
    }

    public function render(): View
    {
        return view('intranet-app-bestellungen::livewire.apps.bestellungen.admin.arten-editor');
    }
}
