<?php

declare(strict_types=1);

namespace Hwkdo\IntranetAppBestellungen\Livewire\Apps\Bestellungen;

use Hwkdo\IntranetAppBestellungen\Models\Bestellung;
use Hwkdo\IntranetAppBestellungen\Services\WertgrenzenService;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Title('Bestellungen – Freigaben')]
class Freigaben extends Component
{
    public function render(): View
    {
        $service = app(WertgrenzenService::class);
        $user = Auth::user();

        $bestellungen = Bestellung::query()
            ->with(['user', 'freigeber'])
            ->freigabePending()
            ->where(function (Builder $q): void {
                $q->where('freigeber_id', Auth::id())->orWhereNull('freigeber_id');
            })
            ->latest()
            ->get()
            ->filter(fn (Bestellung $b): bool => $service->darfFreigeben($user, $b))
            ->values();

        return view('intranet-app-bestellungen::livewire.apps.bestellungen.freigaben', [
            'bestellungen' => $bestellungen,
        ]);
    }
}
