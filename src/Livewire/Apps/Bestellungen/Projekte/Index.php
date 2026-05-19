<?php

declare(strict_types=1);

namespace Hwkdo\IntranetAppBestellungen\Livewire\Apps\Bestellungen\Projekte;

use Flux\Flux;
use Hwkdo\IntranetAppBestellungen\Models\Projekt;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Title('Bestellungen – Projekte')]
class Index extends Component
{
    public string $name = '';

    public string $beschreibung = '';

    public function erstellen(): void
    {
        $this->validate([
            'name' => ['required', 'string', 'max:255'],
            'beschreibung' => ['nullable', 'string', 'max:1000'],
        ]);

        Projekt::create([
            'name' => $this->name,
            'beschreibung' => $this->beschreibung ?: null,
            'user_id' => Auth::id(),
        ]);

        $this->reset('name', 'beschreibung');

        Flux::modal('projekt-erstellen')->close();

        Flux::toast(text: 'Projekt wurde angelegt.', variant: 'success');
    }

    public function render(): View
    {
        $projekte = Projekt::query()
            ->forUser(Auth::id())
            ->with(['ersteller', 'mitglieder'])
            ->withCount('bestellungen')
            ->withSum('bestellungen', 'gesamtbetrag')
            ->latest()
            ->get();

        return view('intranet-app-bestellungen::livewire.apps.bestellungen.projekte.index', [
            'projekte' => $projekte,
        ]);
    }
}
