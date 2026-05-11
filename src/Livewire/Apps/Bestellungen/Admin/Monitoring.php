<?php

declare(strict_types=1);

namespace Hwkdo\IntranetAppBestellungen\Livewire\Apps\Bestellungen\Admin;

use Flux\Flux;
use Hwkdo\IntranetAppBestellungen\Models\Bestellung;
use Hwkdo\IntranetAppBestellungen\Services\D3\BestellscheinD3Service;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

class Monitoring extends Component
{
    use WithPagination;

    #[Url(as: 'q')]
    public string $search = '';

    public string $d3SearchQuery = '';

    public ?Collection $d3SearchResults = null;

    public function updatingSearch(): void
    {
        $this->resetPage();
    }

    public function rePush(int $bestellungId): void
    {
        $bestellung = Bestellung::find($bestellungId);
        if (! $bestellung) {
            Flux::toast(heading: 'Bestellung nicht gefunden', text: 'Die Bestellung konnte nicht geladen werden.', variant: 'warning');

            return;
        }

        try {
            $newId = app(BestellscheinD3Service::class)->rePush($bestellung, Auth::user());
            if (! $newId) {
                Flux::toast(heading: 'D3 Re-Push fehlgeschlagen', text: 'D3 hat keine neue Dokument-ID zurückgegeben.', variant: 'error');

                return;
            }

            Flux::toast(heading: 'D3 Re-Push erfolgreich', text: 'Neue D3-ID: '.$newId, variant: 'success');
        } catch (\Throwable $e) {
            Flux::toast(heading: 'D3 Re-Push fehlgeschlagen', text: $e->getMessage(), variant: 'error');
        }
    }

    public function quasiDelete(int $bestellungId): void
    {
        $bestellung = Bestellung::find($bestellungId);
        if (! $bestellung || ! $bestellung->d3id) {
            Flux::toast(heading: 'Keine D3-ID hinterlegt', text: 'Für diese Bestellung ist keine D3-ID vorhanden.', variant: 'warning');

            return;
        }
        try {
            \Hwkdo\D3RestLaravel\Facades\D3RestLaravel::quasiDeleteDoc($bestellung->d3id);
            $bestellung->forceFill(['d3id' => null, 'd3_pushed_at' => null])->save();
            Flux::toast(heading: 'D3-Eintrag quasi-gelöscht', text: 'Die D3-ID wurde lokal entfernt.', variant: 'success');
        } catch (\Throwable $e) {
            Flux::toast(heading: 'Fehler', text: $e->getMessage(), variant: 'error');
        }
    }

    public function d3Search(): void
    {
        $bestellung = Bestellung::query()
            ->where('nummer', $this->d3SearchQuery)
            ->orWhere('id', (int) $this->d3SearchQuery)
            ->first();

        if (! $bestellung) {
            $this->d3SearchResults = collect();
            Flux::toast(heading: 'Keine Bestellung gefunden', text: 'Zu der eingegebenen Nummer wurde keine Bestellung gefunden.', variant: 'warning');

            return;
        }

        try {
            $this->d3SearchResults = app(BestellscheinD3Service::class)->search($bestellung);
        } catch (\Throwable $e) {
            $this->d3SearchResults = collect();
            Flux::toast(heading: 'D3-Suche fehlgeschlagen', text: $e->getMessage(), variant: 'error');
        }
    }

    public function render(): View
    {
        $bestellungen = Bestellung::query()
            ->with(['user', 'freigeber', 'besteller'])
            ->when($this->search, function ($q): void {
                $q->where(function ($q): void {
                    $q->where('nummer', 'like', '%'.$this->search.'%')
                        ->orWhere('lieferantenname', 'like', '%'.$this->search.'%')
                        ->orWhere('d3id', 'like', '%'.$this->search.'%');
                });
            })
            ->latest()
            ->paginate(25);

        return view('intranet-app-bestellungen::livewire.apps.bestellungen.admin.monitoring', [
            'bestellungen' => $bestellungen,
        ]);
    }
}
