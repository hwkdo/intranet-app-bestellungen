<?php

declare(strict_types=1);

namespace Hwkdo\IntranetAppBestellungen\Livewire\Apps\Bestellungen\Admin;

use Illuminate\Contracts\View\View;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;

#[Title('Bestellungen – Administration')]
class Index extends Component
{
    #[Url(as: 'tab')]
    public string $activeTab = 'wertgrenzen';

    public function render(): View
    {
        return view('intranet-app-bestellungen::livewire.apps.bestellungen.admin.index');
    }
}
