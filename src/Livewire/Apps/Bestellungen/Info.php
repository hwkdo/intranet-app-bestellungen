<?php

declare(strict_types=1);

namespace Hwkdo\IntranetAppBestellungen\Livewire\Apps\Bestellungen;

use Illuminate\Contracts\View\View;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Title('Bestellungen – App-Info')]
class Info extends Component
{
    public function render(): View
    {
        return view('intranet-app-bestellungen::livewire.apps.bestellungen.info');
    }
}
