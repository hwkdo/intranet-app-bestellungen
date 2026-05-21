<?php

declare(strict_types=1);

namespace Hwkdo\IntranetAppBestellungen\Livewire\Apps\Bestellungen;

use Hwkdo\IntranetAppBestellungen\Models\Bestellung;
use Hwkdo\IntranetAppBestellungen\Services\InterneBestellerService;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Title('Bestellungen – Interne Bestellungen')]
class InterneBestellungen extends Component
{
    public function mount(): void
    {
        abort_unless(
            app(InterneBestellerService::class)->istMitglied(Auth::user()),
            403,
        );
    }

    public function render(): View
    {
        $bestellungen = Bestellung::query()
            ->with(['user'])
            ->fuerInternenEmpfaenger((int) Auth::id())
            ->latest()
            ->get();

        return view('intranet-app-bestellungen::livewire.apps.bestellungen.interne-bestellungen', [
            'bestellungen' => $bestellungen,
        ]);
    }
}
