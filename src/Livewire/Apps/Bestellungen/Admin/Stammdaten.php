<?php

declare(strict_types=1);

namespace Hwkdo\IntranetAppBestellungen\Livewire\Apps\Bestellungen\Admin;

use Flux\Flux;
use Hwkdo\IntranetAppBestellungen\Models\KostenstelleCache;
use Hwkdo\IntranetAppBestellungen\Models\LieferantCache;
use Hwkdo\IntranetAppBestellungen\Services\Stammdaten\StammdatenSyncService;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Computed;
use Livewire\Component;

class Stammdaten extends Component
{
    public string $aktiveTab = 'lieferanten';

    public string $search = '';

    #[Computed]
    public function lieferanten()
    {
        return LieferantCache::query()
            ->when($this->search, fn ($q) => $q->where('lieferantenname', 'like', '%'.$this->search.'%'))
            ->orderBy('lieferantenname')
            ->limit(200)
            ->get();
    }

    #[Computed]
    public function kostenstellen()
    {
        return KostenstelleCache::query()
            ->when($this->search, fn ($q) => $q->where('bezeichnung', 'like', '%'.$this->search.'%'))
            ->orderBy('kostenstelle')
            ->limit(200)
            ->get();
    }

    public function syncJetzt(string $typ): void
    {
        $service = app(StammdatenSyncService::class);

        try {
            if ($typ === 'lieferanten') {
                $result = $service->syncLieferanten();
                Flux::toast(heading: 'Lieferanten aktualisiert', text: $result['count'].' Einträge.', variant: 'success');
            }
            if ($typ === 'kostenstellen') {
                $result = $service->syncKostenstellen();
                Flux::toast(heading: 'Kostenstellen aktualisiert', text: $result['count'].' Einträge.', variant: 'success');
            }
        } catch (\Throwable $e) {
            Flux::toast(heading: 'Sync fehlgeschlagen', text: $e->getMessage(), variant: 'error');
        }

        unset($this->lieferanten, $this->kostenstellen);
    }

    public function render(): View
    {
        return view('intranet-app-bestellungen::livewire.apps.bestellungen.admin.stammdaten');
    }
}
