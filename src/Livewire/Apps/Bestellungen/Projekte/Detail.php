<?php

declare(strict_types=1);

namespace Hwkdo\IntranetAppBestellungen\Livewire\Apps\Bestellungen\Projekte;

use App\Models\User;
use Flux\Flux;
use Hwkdo\IntranetAppBestellungen\Models\Projekt;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Title('Bestellungen – Projekt')]
class Detail extends Component
{
    public Projekt $projekt;

    public ?int $mitgliedUserId = null;

    public function mount(Projekt $projekt): void
    {
        $this->projekt = $projekt->load(['ersteller', 'mitglieder']);

        abort_unless(
            $this->projekt->istMitgliedOderErsteller(Auth::id()),
            403,
            'Sie haben keinen Zugriff auf dieses Projekt.',
        );
    }

    #[Computed]
    public function userSuggestions(): Collection
    {
        $bereitsImProjekt = $this->projekt->mitglieder->pluck('id')
            ->push($this->projekt->user_id);

        return User::query()
            ->aktiv()
            ->whereNotIn('id', $bereitsImProjekt)
            ->orderBy('nachname')
            ->orderBy('vorname')
            ->get();
    }

    #[Computed]
    public function bestellungen(): Collection
    {
        return $this->projekt->bestellungen()
            ->with(['user', 'freigeber'])
            ->latest()
            ->get();
    }

    #[Computed]
    public function gesamtkosten(): float
    {
        return (float) $this->bestellungen->sum('gesamtbetrag');
    }

    public function istErsteller(): bool
    {
        return $this->projekt->user_id === Auth::id();
    }

    public function mitgliedHinzufuegen(): void
    {
        abort_unless($this->istErsteller(), 403);

        $this->validate([
            'mitgliedUserId' => ['required', 'integer', 'exists:users,id'],
        ]);

        $this->projekt->mitglieder()->syncWithoutDetaching([$this->mitgliedUserId]);
        $this->projekt->load('mitglieder');

        $this->reset('mitgliedUserId');
        unset($this->userSuggestions);

        Flux::toast(text: 'Mitglied wurde hinzugefügt.', variant: 'success');
    }

    public function mitgliedEntfernen(int $userId): void
    {
        abort_unless($this->istErsteller(), 403);

        $this->projekt->mitglieder()->detach($userId);
        $this->projekt->load('mitglieder');

        unset($this->userSuggestions);

        Flux::toast(text: 'Mitglied wurde entfernt.', variant: 'success');
    }

    public function render(): View
    {
        return view('intranet-app-bestellungen::livewire.apps.bestellungen.projekte.detail');
    }
}
