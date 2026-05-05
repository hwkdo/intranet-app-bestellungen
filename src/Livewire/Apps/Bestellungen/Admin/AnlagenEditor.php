<?php

declare(strict_types=1);

namespace Hwkdo\IntranetAppBestellungen\Livewire\Apps\Bestellungen\Admin;

use Flux\Flux;
use Hwkdo\IntranetAppBestellungen\Models\Anlage;
use Hwkdo\IntranetAppBestellungen\Models\Art;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Collection;
use Livewire\Attributes\Computed;
use Livewire\Component;

class AnlagenEditor extends Component
{
    public ?int $editId = null;

    public string $bezeichnung = '';

    public ?string $beschreibung = null;

    public ?int $artId = null;

    public bool $aktiv = true;

    #[Computed]
    public function anlagen(): Collection
    {
        return Anlage::query()->with('art')->orderBy('bezeichnung')->get();
    }

    #[Computed]
    public function arten(): Collection
    {
        return Art::query()->orderBy('bezeichnung')->get();
    }

    public function rules(): array
    {
        return [
            'bezeichnung' => ['required', 'string', 'max:150'],
            'beschreibung' => ['nullable', 'string'],
            'artId' => ['nullable', 'integer'],
            'aktiv' => ['boolean'],
        ];
    }

    public function neu(): void
    {
        $this->reset(['editId', 'bezeichnung', 'beschreibung', 'artId']);
        $this->aktiv = true;
        Flux::modal('anlage-modal')->show();
    }

    public function edit(int $id): void
    {
        $anlage = Anlage::findOrFail($id);
        $this->editId = $anlage->id;
        $this->bezeichnung = $anlage->bezeichnung;
        $this->beschreibung = $anlage->beschreibung;
        $this->artId = $anlage->art_id;
        $this->aktiv = (bool) $anlage->aktiv;
        Flux::modal('anlage-modal')->show();
    }

    public function speichern(): void
    {
        $this->validate();

        Anlage::updateOrCreate(
            ['id' => $this->editId],
            [
                'art_id' => $this->artId,
                'bezeichnung' => $this->bezeichnung,
                'beschreibung' => $this->beschreibung,
                'aktiv' => $this->aktiv,
            ],
        );

        Flux::modal('anlage-modal')->close();
        Flux::toast(heading: 'Anlage gespeichert', variant: 'success');
        unset($this->anlagen);
    }

    public function loeschen(int $id): void
    {
        Anlage::query()->whereKey($id)->delete();
        Flux::toast(heading: 'Anlage gelöscht', variant: 'success');
        unset($this->anlagen);
    }

    public function render(): View
    {
        return view('intranet-app-bestellungen::livewire.apps.bestellungen.admin.anlagen-editor');
    }
}
