<?php

declare(strict_types=1);

namespace Hwkdo\IntranetAppBestellungen\Livewire\Apps\Bestellungen\Admin;

use Flux\Flux;
use Hwkdo\IntranetAppBestellungen\Models\KostenstelleCache;
use Hwkdo\IntranetAppBestellungen\Models\LieferantCache;
use Hwkdo\IntranetAppBestellungen\Services\Stammdaten\LieferantNutzungSyncService;
use Hwkdo\IntranetAppBestellungen\Services\Stammdaten\StammdatenSyncService;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\Computed;
use Livewire\Component;
use Livewire\WithPagination;

class Stammdaten extends Component
{
    use WithPagination;

    private const KOSTENSTELLEN_PER_PAGE = 25;

    private const KOSTENSTELLEN_PAGE_NAME = 'kostenstellenPage';

    public string $aktiveTab = 'lieferanten';

    public string $search = '';

    /** @var 'alle'|'aktiv'|'inaktiv' */
    public string $kostenstellenStatus = 'alle';

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
    public function kostenstellen(): LengthAwarePaginator
    {
        return $this->kostenstellenQuery()
            ->orderBy('kostenstelle')
            ->paginate(self::KOSTENSTELLEN_PER_PAGE, pageName: self::KOSTENSTELLEN_PAGE_NAME);
    }

    #[Computed]
    public function kostenstellenGesamt(): int
    {
        return KostenstelleCache::query()->count();
    }

    public function kostenstellenZaehlerText(): string
    {
        $gesamt = $this->kostenstellenGesamt;
        $gefiltert = $this->kostenstellen->total();
        $von = $this->kostenstellen->firstItem();
        $bis = $this->kostenstellen->lastItem();

        $gesamtFormatiert = number_format($gesamt, 0, ',', '.');

        if ($gefiltert === 0) {
            return sprintf('Keine Treffer · %s insgesamt', $gesamtFormatiert);
        }

        $bereich = sprintf(
            '%s–%s von %s',
            number_format((int) $von, 0, ',', '.'),
            number_format((int) $bis, 0, ',', '.'),
            number_format($gefiltert, 0, ',', '.'),
        );

        if ($this->search === '' && $this->kostenstellenStatus === 'alle') {
            return $bereich.' insgesamt';
        }

        return $bereich.' gefiltert · '.$gesamtFormatiert.' insgesamt';
    }

    public function updatedSearch(): void
    {
        $this->resetKostenstellenPage();
    }

    public function updatedKostenstellenStatus(): void
    {
        if (! in_array($this->kostenstellenStatus, ['alle', 'aktiv', 'inaktiv'], true)) {
            $this->kostenstellenStatus = 'alle';
        }

        $this->resetKostenstellenPage();
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
                Flux::toast(
                    heading: 'Kostenstellen aktualisiert',
                    text: $result['count'].' Einträge, '.$result['deactivated'].' als inaktiv markiert.',
                    variant: 'success',
                );
            }
        } catch (\Throwable $e) {
            Flux::toast(heading: 'Sync fehlgeschlagen', text: $e->getMessage(), variant: 'error');
        }

        unset($this->lieferanten, $this->kostenstellen, $this->kostenstellenGesamt);
        $this->resetKostenstellenPage();
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

    private function kostenstellenQuery(): Builder
    {
        return KostenstelleCache::query()
            ->when($this->kostenstellenStatus === 'aktiv', fn (Builder $q) => $q->aktiv())
            ->when($this->kostenstellenStatus === 'inaktiv', fn (Builder $q) => $q->inaktiv())
            ->when($this->search !== '', function (Builder $q): void {
                $like = '%'.$this->search.'%';
                $q->where(function (Builder $inner) use ($like): void {
                    $inner->where('bezeichnung', 'like', $like)
                        ->orWhere('kostenstelle', 'like', $like);
                });
            });
    }

    private function resetKostenstellenPage(): void
    {
        $this->resetPage(pageName: self::KOSTENSTELLEN_PAGE_NAME);
        unset($this->kostenstellen);
    }
}
