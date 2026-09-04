<?php

declare(strict_types=1);

namespace Hwkdo\IntranetAppBestellungen\Livewire\Apps\Bestellungen;

use Hwkdo\IntranetAppBestellungen\Search\BestellungSearch;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;

#[Title('Bestellungen – Suche')]
class Search extends Component
{
    #[Url(as: 'q')]
    public string $searchQuery = '';

    #[Computed]
    public function results(): Collection
    {
        $user = Auth::user();
        if ($user === null) {
            return collect();
        }

        return BestellungSearch::query($this->searchQuery, $user);
    }

    public function render(): View
    {
        return view('intranet-app-bestellungen::livewire.apps.bestellungen.search');
    }
}
