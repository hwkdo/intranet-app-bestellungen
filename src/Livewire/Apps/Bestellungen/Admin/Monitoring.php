<?php

declare(strict_types=1);

namespace Hwkdo\IntranetAppBestellungen\Livewire\Apps\Bestellungen\Admin;

use Flux\Flux;
use Hwkdo\IntranetAppBestellungen\Jobs\PushBestellscheinToD3Job;
use Hwkdo\IntranetAppBestellungen\Models\Bestellung;
use Hwkdo\IntranetAppBestellungen\Services\D3\BestellscheinD3Service;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Collection;
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
        PushBestellscheinToD3Job::dispatch($bestellungId, rePush: true);
        Flux::toast(heading: 'D3 Re-Push gestartet', variant: 'success');
    }

    public function quasiDelete(int $bestellungId): void
    {
        $bestellung = Bestellung::find($bestellungId);
        if (! $bestellung || ! $bestellung->d3id) {
            Flux::toast(heading: 'Keine D3-ID hinterlegt', variant: 'warning');

            return;
        }
        try {
            \Hwkdo\D3RestLaravel\Facades\D3RestLaravel::quasiDeleteDoc($bestellung->d3id);
            $bestellung->forceFill(['d3id' => null, 'd3_pushed_at' => null])->save();
            Flux::toast(heading: 'D3-Eintrag quasi-gelöscht', variant: 'success');
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
            Flux::toast(heading: 'Keine Bestellung mit dieser Nummer gefunden', variant: 'warning');

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
