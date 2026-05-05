<?php

declare(strict_types=1);

namespace Hwkdo\IntranetAppBestellungen\Livewire\Apps\Bestellungen;

use Hwkdo\IntranetAppBestellungen\Enums\BestellungStatus;
use Hwkdo\IntranetAppBestellungen\Models\Bestellung;
use Hwkdo\IntranetAppBestellungen\Models\IntranetAppBestellungenSettings;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

#[Title('Bestellungen – Meine')]
class Meine extends Component
{
    use WithPagination;

    #[Url(as: 'q')]
    public string $search = '';

    #[Url(as: 'status')]
    public string $statusFilter = '';

    public function updatingSearch(): void
    {
        $this->resetPage();
    }

    public function updatingStatusFilter(): void
    {
        $this->resetPage();
    }

    public function statusOptions(): array
    {
        return ['' => 'Alle Status'] + BestellungStatus::options();
    }

    public function render(): View
    {
        $perPage = IntranetAppBestellungenSettings::resolvedAppSettings()->maxItemsPerPage;

        $bestellungen = Bestellung::query()
            ->with(['user', 'freigeber', 'besteller'])
            ->where('user_id', Auth::id())
            ->when($this->statusFilter, fn (Builder $q): Builder => $q->where('status', $this->statusFilter))
            ->when($this->search, function (Builder $q): void {
                $q->where(function (Builder $q): void {
                    $q->where('nummer', 'like', '%'.$this->search.'%')
                        ->orWhere('lieferantenname', 'like', '%'.$this->search.'%')
                        ->orWhere('betreff', 'like', '%'.$this->search.'%');
                });
            })
            ->latest()
            ->paginate($perPage);

        return view('intranet-app-bestellungen::livewire.apps.bestellungen.meine', [
            'bestellungen' => $bestellungen,
        ]);
    }
}
