<?php

declare(strict_types=1);

namespace Hwkdo\IntranetAppBestellungen\Livewire\Apps\Bestellungen;

use Hwkdo\IntranetAppBestellungen\Enums\BestellungStatus;
use Hwkdo\IntranetAppBestellungen\Models\Bestellung;
use Hwkdo\IntranetAppBestellungen\Services\WertgrenzenService;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Title('Bestellungen – Übersicht')]
class Index extends Component
{
    #[Computed]
    public function meineBestellungen(): \Illuminate\Database\Eloquent\Collection
    {
        return Bestellung::query()
            ->with(['user', 'freigeber'])
            ->where('user_id', Auth::id())
            ->latest()
            ->limit(10)
            ->get();
    }

    #[Computed]
    public function offeneFreigaben(): \Illuminate\Database\Eloquent\Collection
    {
        $service = app(WertgrenzenService::class);

        return Bestellung::query()
            ->with(['user'])
            ->freigabePending()
            ->where(function (Builder $q): void {
                $q->where('freigeber_id', Auth::id())
                    ->orWhereNull('freigeber_id');
            })
            ->latest()
            ->get()
            ->filter(fn (Bestellung $b): bool => $service->darfFreigeben(Auth::user(), $b))
            ->values();
    }

    #[Computed]
    public function statistik(): array
    {
        $userId = Auth::id();

        return [
            'gesamt' => Bestellung::query()->where('user_id', $userId)->count(),
            'offen' => Bestellung::query()->where('user_id', $userId)
                ->whereIn('status', [
                    BestellungStatus::ZurFreigabe->value,
                    BestellungStatus::ZurZweitenFreigabe->value,
                ])->count(),
            'bestellt' => Bestellung::query()->where('user_id', $userId)
                ->where('status', BestellungStatus::Bestellt->value)->count(),
            'abgelehnt' => Bestellung::query()->where('user_id', $userId)
                ->where('status', BestellungStatus::Abgelehnt->value)->count(),
        ];
    }

    public function render(): View
    {
        return view('intranet-app-bestellungen::livewire.apps.bestellungen.index');
    }
}
