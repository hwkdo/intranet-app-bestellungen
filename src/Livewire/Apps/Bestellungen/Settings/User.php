<?php

declare(strict_types=1);

namespace Hwkdo\IntranetAppBestellungen\Livewire\Apps\Bestellungen\Settings;

use Illuminate\Contracts\View\View;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Title('Bestellungen – Meine Einstellungen')]
class User extends Component
{
    public function render(): View
    {
        return view('intranet-app-bestellungen::livewire.apps.bestellungen.settings.user');
    }
}
