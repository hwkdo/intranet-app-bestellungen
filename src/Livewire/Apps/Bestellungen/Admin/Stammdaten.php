<?php

declare(strict_types=1);

namespace Hwkdo\IntranetAppBestellungen\Livewire\Apps\Bestellungen\Admin;

use Flux\Flux;
use Hwkdo\IntranetAppBestellungen\Models\KostenstelleCache;
use Hwkdo\IntranetAppBestellungen\Models\LieferantCache;
use Hwkdo\IntranetAppBestellungen\Services\Stammdaten\LieferantNutzungSyncService;
use Hwkdo\IntranetAppBestellungen\Services\Stammdaten\StammdatenSyncService;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\Computed;
use Livewire\Component;

class Stammdaten extends Component
{
    public string $aktiveTab = 'lieferanten';

    public string $search = '';

    /** @var 'name'|'nummer'|'nutzung'|'legacy'|'v3' */
    public string $lieferantenSortBy = 'name';

    public string $lieferantenSortDir = 'asc';

    #[Computed]
    public function lieferanten()
    {
        $query = LieferantCache::query()
            ->from('intranet_app_bestellungen_lieferanten_cache')
            ->leftJoin(
                'intranet_app_bestellungen_lieferant_nutzung as ln',
                'ln.lieferantennummer',
                '=',
                'intranet_app_bestellungen_lieferanten_cache.lieferantennummer'
            )
            ->select([
                'intranet_app_bestellungen_lieferanten_cache.*',
                DB::raw('COALESCE(ln.legacy_bestellungen_count, 0) as legacy_nutzung'),
                DB::raw('COALESCE(ln.v3_bestellungen_count, 0) as v3_nutzung'),
                DB::raw('(COALESCE(ln.legacy_bestellungen_count, 0) + COALESCE(ln.v3_bestellungen_count, 0)) as nutzung_gesamt'),
            ])
            ->when($this->search, function ($q): void {
                $like = '%'.$this->search.'%';
                $q->where(function ($inner) use ($like): void {
                    $inner->where('intranet_app_bestellungen_lieferanten_cache.lieferantenname', 'like', $like)
                        ->orWhere('intranet_app_bestellungen_lieferanten_cache.lieferantennummer', 'like', $like);
                });
            });

        $dir = $this->lieferantenSortDir === 'desc' ? 'desc' : 'asc';

        match ($this->lieferantenSortBy) {
            'nummer' => $query->orderBy('intranet_app_bestellungen_lieferanten_cache.lieferantennummer', $dir),
            'nutzung' => $query->orderBy('nutzung_gesamt', $dir)
                ->orderBy('intranet_app_bestellungen_lieferanten_cache.lieferantenname'),
            'legacy' => $query->orderBy('legacy_nutzung', $dir)
                ->orderBy('intranet_app_bestellungen_lieferanten_cache.lieferantenname'),
            'v3' => $query->orderBy('v3_nutzung', $dir)
                ->orderBy('intranet_app_bestellungen_lieferanten_cache.lieferantenname'),
            default => $query->orderBy('intranet_app_bestellungen_lieferanten_cache.lieferantenname', $dir),
        };

        return $query->limit(200)->get();
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

    public function sortLieferantenBy(string $column): void
    {
        if (! in_array($column, ['name', 'nummer', 'nutzung', 'legacy', 'v3'], true)) {
            return;
        }

        if ($this->lieferantenSortBy === $column) {
            $this->lieferantenSortDir = $this->lieferantenSortDir === 'asc' ? 'desc' : 'asc';
        } else {
            $this->lieferantenSortBy = $column;
            $this->lieferantenSortDir = in_array($column, ['nutzung', 'legacy', 'v3'], true) ? 'desc' : 'asc';
        }

        unset($this->lieferanten);
    }

    public function syncLieferantenNutzungAusLegacy(): void
    {
        try {
            $result = app(LieferantNutzungSyncService::class)->syncFromLegacy();
            Flux::toast(
                heading: 'Legacy-Nutzung synchronisiert',
                text: $result['count'].' Lieferanten aktualisiert.',
                variant: 'success',
            );
        } catch (\Throwable $e) {
            Flux::toast(heading: 'Sync fehlgeschlagen', text: $e->getMessage(), variant: 'error');
        }

        unset($this->lieferanten);
    }

    public function render(): View
    {
        return view('intranet-app-bestellungen::livewire.apps.bestellungen.admin.stammdaten');
    }
}
