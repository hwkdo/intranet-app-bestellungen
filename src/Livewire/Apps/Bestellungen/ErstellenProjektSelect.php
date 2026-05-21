<?php

declare(strict_types=1);

namespace Hwkdo\IntranetAppBestellungen\Livewire\Apps\Bestellungen;

use Hwkdo\IntranetAppBestellungen\Models\Projekt;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Modelable;
use Livewire\Component;

class ErstellenProjektSelect extends Component
{
    #[Modelable]
    public ?int $projektId = null;

    public function mount(): void
    {
        if (! request()->filled('projekt')) {
            $this->projektId = null;
        }
    }

    #[Computed]
    public function userHasProjekte(): bool
    {
        return Projekt::query()->forUser((int) Auth::id())->exists();
    }

    #[Computed]
    public function projektSuggestions(): Collection
    {
        return Projekt::query()->forUser((int) Auth::id())->orderBy('name')->get();
    }

    public function render(): View
    {
        return view('intranet-app-bestellungen::livewire.apps.bestellungen.erstellen-projekt-select');
    }
}
