<?php

declare(strict_types=1);

namespace Hwkdo\IntranetAppBestellungen\Livewire\Apps\Bestellungen;

use App\Models\User;
use Flux\Flux;
use Hwkdo\IntranetAppBestellungen\Enums\AktionTyp;
use Hwkdo\IntranetAppBestellungen\Enums\BestellungStatus;
use Hwkdo\IntranetAppBestellungen\Models\Angebot;
use Hwkdo\IntranetAppBestellungen\Models\Bestellung;
use Hwkdo\IntranetAppBestellungen\Models\Notiz;
use Hwkdo\IntranetAppBestellungen\Models\Position;
use Hwkdo\IntranetAppBestellungen\Services\BenNumberService;
use Hwkdo\IntranetAppBestellungen\Services\BestellungWorkflow;
use Hwkdo\IntranetAppBestellungen\Services\D3\AngebotD3Service;
use Hwkdo\IntranetAppBestellungen\Services\WertgrenzenService;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithFileUploads;

#[Title('Bestellung Detail')]
class Detail extends Component
{
    use WithFileUploads;

    public Bestellung $bestellung;

    #[Url(as: 'aktion')]
    public ?string $aktionParam = null;

    public string $activeTab = 'positionen';

    public ?string $freigabeNachricht = null;

    public ?string $ablehnenGrund = null;

    public ?int $weiterleitenAnUserId = null;

    public ?string $weiterleitenNachricht = null;

    public string $notizText = '';

    public string $angebotTyp = 'angebot';

    public ?string $angebotLieferant = null;

    public ?string $angebotNummer = null;

    public ?float $angebotBetrag = null;

    public ?string $angebotBegruendung = null;

    public $angebotPdf = null;

    public function mount(Bestellung $bestellung): void
    {
        $this->bestellung = $bestellung->load(['user', 'freigeber', 'besteller', 'positionen.art', 'positionen.media', 'angebote.user', 'notizen.user', 'aktionen.user']);

        if ($this->aktionParam === 'freigeben' && $this->kannFreigeben()) {
            Flux::modal('freigeben-modal')->show();
        }
        if ($this->aktionParam === 'ablehnen' && $this->kannFreigeben()) {
            Flux::modal('ablehnen-modal')->show();
        }
    }

    public function kannFreigeben(): bool
    {
        if (! $this->bestellung->status?->isFreigabePending()) {
            return false;
        }

        return app(WertgrenzenService::class)->darfFreigeben(Auth::user(), $this->bestellung);
    }

    public function kannBestellen(): bool
    {
        return $this->bestellung->status === BestellungStatus::Freigegeben
            && Auth::user()?->can('manage-app-bestellungen');
    }

    public function kannBearbeiten(): bool
    {
        return $this->bestellung->user_id === Auth::id()
            && in_array($this->bestellung->status, [BestellungStatus::Entwurf, BestellungStatus::Abgelehnt], true);
    }

    public function kannEinreichen(): bool
    {
        return $this->bestellung->user_id === Auth::id()
            && $this->bestellung->status === BestellungStatus::Entwurf;
    }

    public function freigeben(): void
    {
        if (! $this->kannFreigeben()) {
            return;
        }
        try {
            app(BestellungWorkflow::class)->freigeben($this->bestellung, Auth::user(), $this->freigabeNachricht);
            Flux::modal('freigeben-modal')->close();
            Flux::toast(heading: 'Freigegeben', text: 'Die Bestellung wurde freigegeben.', variant: 'success');
            $this->refreshBestellung();
        } catch (\Throwable $e) {
            Flux::toast(heading: 'Fehler', text: $e->getMessage(), variant: 'error');
        }
    }

    public function ablehnen(): void
    {
        $this->validate(['ablehnenGrund' => ['required', 'string', 'min:3']]);
        try {
            app(BestellungWorkflow::class)->ablehnen($this->bestellung, Auth::user(), (string) $this->ablehnenGrund);
            Flux::modal('ablehnen-modal')->close();
            Flux::toast(heading: 'Abgelehnt', text: 'Die Bestellung wurde abgelehnt.', variant: 'success');
            $this->refreshBestellung();
        } catch (\Throwable $e) {
            Flux::toast(heading: 'Fehler', text: $e->getMessage(), variant: 'error');
        }
    }

    public function weiterleiten(): void
    {
        $this->validate(['weiterleitenAnUserId' => ['required', 'integer']]);
        try {
            app(BestellungWorkflow::class)->weiterleiten(
                $this->bestellung,
                Auth::user(),
                (int) $this->weiterleitenAnUserId,
                $this->weiterleitenNachricht,
            );
            Flux::modal('weiterleiten-modal')->close();
            Flux::toast(heading: 'Weitergeleitet', text: 'Die Bestellung wurde weitergeleitet.', variant: 'success');
            $this->refreshBestellung();
        } catch (\Throwable $e) {
            Flux::toast(heading: 'Fehler', text: $e->getMessage(), variant: 'error');
        }
    }

    public function bestellen(): void
    {
        if (! $this->kannBestellen()) {
            return;
        }
        try {
            app(BestellungWorkflow::class)->bestellen($this->bestellung, Auth::user());
            Flux::toast(heading: 'Bestellt', text: 'Die Bestellung wurde an D3 übergeben.', variant: 'success');
            $this->refreshBestellung();
        } catch (\Throwable $e) {
            Flux::toast(heading: 'Fehler', text: $e->getMessage(), variant: 'error');
        }
    }

    public function einreichen(): void
    {
        if (! $this->kannEinreichen()) {
            return;
        }

        try {
            app(BestellungWorkflow::class)->einreichen($this->bestellung, Auth::user());
            Flux::toast(heading: 'Zur Freigabe eingereicht', text: 'Die Bestellung wurde zur Freigabe weitergeleitet.', variant: 'success');
            $this->refreshBestellung();
        } catch (\Throwable $e) {
            Flux::toast(heading: 'Fehler', text: $e->getMessage(), variant: 'error');
        }
    }

    public function wiederholen(): void
    {
        $alt = $this->bestellung;

        $neu = DB::transaction(function () use ($alt): Bestellung {
            $nummer = app(BenNumberService::class)->next(Auth::user(), (int) date('Y'));

            $kopie = $alt->replicate(['nummer', 'd3id', 'd3_pushed_at', 'bestellt_at', 'status', 'freigeber_id', 'besteller_id']);
            $kopie->nummer = $nummer;
            $kopie->status = BestellungStatus::Entwurf;
            $kopie->haushaltsjahr = (int) date('Y');
            $kopie->user_id = Auth::id();
            $kopie->freigeber_id = null;
            $kopie->besteller_id = null;
            $kopie->d3id = null;
            $kopie->d3_pushed_at = null;
            $kopie->bestellt_at = null;
            $kopie->wiederholt_von_id = $alt->getKey();
            $kopie->save();

            foreach ($alt->positionen as $pos) {
                $newPosition = Position::create([
                    'bestellung_id' => $kopie->getKey(),
                    'art_id' => $pos->art_id,
                    'nr' => $pos->nr,
                    'menge' => $pos->menge,
                    'einheit' => $pos->einheit,
                    'art_nr' => $pos->art_nr,
                    'oberbegriff' => $pos->oberbegriff,
                    'bezeichnung' => $pos->bezeichnung,
                    'preis' => $pos->preis,
                    'file' => $pos->file,
                ]);

                $positionPdf = $pos->getFirstMedia('position_pdf');
                if ($positionPdf) {
                    $copiedMedia = $positionPdf->copy($newPosition, 'position_pdf');
                    $newPosition->forceFill(['file' => $copiedMedia->getPathRelativeToRoot()])->save();
                }
            }

            $kopie->refreshGesamtbetrag();

            app(BestellungWorkflow::class)->logAktion(
                $kopie,
                Auth::user(),
                AktionTyp::Wiederholt,
                payload: ['vorlage_id' => $alt->getKey(), 'vorlage_nummer' => $alt->nummer],
            );

            return $kopie;
        });

        Flux::toast(
            heading: 'Bestellung dupliziert',
            text: 'BEN '.$neu->nummer.' wurde aus '.$alt->nummer.' erzeugt.',
            variant: 'success',
        );

        $this->redirectRoute('apps.bestellungen.detail', ['bestellung' => $neu->getKey()], navigate: true);
    }

    public function notizSpeichern(): void
    {
        $this->validate(['notizText' => ['required', 'string', 'min:1']]);

        $notiz = Notiz::create([
            'bestellung_id' => $this->bestellung->getKey(),
            'user_id' => Auth::id(),
            'text' => $this->notizText,
        ]);

        app(BestellungWorkflow::class)->logAktion(
            $this->bestellung,
            Auth::user(),
            AktionTyp::NotizHinzugefuegt,
            payload: ['notiz_id' => $notiz->getKey()],
        );

        $this->notizText = '';
        $this->refreshBestellung();

        Flux::toast(heading: 'Notiz hinzugefügt', variant: 'success');
    }

    public function angebotSpeichern(): void
    {
        $this->validate([
            'angebotTyp' => ['required', 'in:angebot,begruendung'],
            'angebotLieferant' => ['nullable', 'string', 'max:255'],
            'angebotNummer' => ['nullable', 'string', 'max:100'],
            'angebotBetrag' => ['nullable', 'numeric'],
            'angebotBegruendung' => ['nullable', 'string'],
            'angebotPdf' => ['nullable', 'file', 'mimes:pdf', 'max:10240'],
        ]);

        $relPath = null;
        if ($this->angebotPdf) {
            $relPath = $this->angebotPdf->store('bestellungen/angebote/'.$this->bestellung->getKey(), 'local');
        }

        $angebot = Angebot::create([
            'bestellung_id' => $this->bestellung->getKey(),
            'user_id' => Auth::id(),
            'typ' => $this->angebotTyp,
            'lieferantenname' => $this->angebotLieferant,
            'nummer' => $this->angebotNummer,
            'betrag' => $this->angebotBetrag,
            'begruendung' => $this->angebotBegruendung,
            'pdf_path' => $relPath,
        ]);

        if ($this->angebotTyp === 'begruendung' && filled($this->angebotBegruendung)) {
            app(AngebotD3Service::class)->generateBegruendungPdf($angebot);
        }

        app(BestellungWorkflow::class)->logAktion(
            $this->bestellung,
            Auth::user(),
            AktionTyp::AngebotHinzugefuegt,
            payload: ['angebot_id' => $angebot->getKey(), 'typ' => $this->angebotTyp],
        );

        $this->reset(['angebotLieferant', 'angebotNummer', 'angebotBetrag', 'angebotBegruendung', 'angebotPdf']);
        $this->refreshBestellung();

        Flux::toast(heading: 'Angebot/Begründung hinzugefügt', variant: 'success');
    }

    public function moeglicheFreigeberOptions(): array
    {
        return User::query()
            ->where('id', '!=', Auth::id())
            ->orderBy('nachname')
            ->orderBy('vorname')
            ->limit(200)
            ->get(['id', 'vorname', 'nachname'])
            ->mapWithKeys(fn (User $u): array => [$u->id => $u->name])
            ->all();
    }

    /**
     * @return array<int, string>
     */
    public function aktuelleFreigeberNamen(): array
    {
        if (! $this->bestellung->status?->isFreigabePending()) {
            return [];
        }

        if ($this->bestellung->freigeber) {
            return [$this->bestellung->freigeber->name];
        }

        return app(WertgrenzenService::class)
            ->freigeberFuerBestellung($this->bestellung)
            ->map(fn (User $user): string => $user->name)
            ->values()
            ->all();
    }

    public function render(): View
    {
        return view('intranet-app-bestellungen::livewire.apps.bestellungen.detail');
    }

    private function refreshBestellung(): void
    {
        $this->bestellung = $this->bestellung->fresh([
            'user', 'freigeber', 'besteller', 'positionen.art', 'positionen.media', 'angebote.user', 'notizen.user', 'aktionen.user',
        ]);
    }
}
