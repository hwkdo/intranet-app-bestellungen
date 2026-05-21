<?php

declare(strict_types=1);

namespace Hwkdo\IntranetAppBestellungen\Livewire\Apps\Bestellungen;

use Hwkdo\IntranetAppBestellungen\Enums\BestellungTyp;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Title('Bestellungen – Art wählen')]
class ErstellenStart extends Component
{
    public function internUrl(): string
    {
        return $this->formularUrl(BestellungTyp::Intern->value);
    }

    public function externUrl(): string
    {
        return $this->formularUrl(BestellungTyp::Extern->value);
    }

    private function formularUrl(string $typ): string
    {
        $params = ['typ' => $typ];

        if (request()->filled('projekt')) {
            $params['projekt'] = request()->query('projekt');
        }

        return route('apps.bestellungen.erstellen.form', $params);
    }

    public function render(): View
    {
        return view('intranet-app-bestellungen::livewire.apps.bestellungen.erstellen-start');
    }
}
