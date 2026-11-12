<?php

declare(strict_types=1);

namespace Hwkdo\IntranetAppBestellungen\Livewire\Apps\Bestellungen\Concerns;

use Flux\Flux;
use Hwkdo\IntranetAppBestellungen\Http\Requests\MeldeFehlendenLieferantRequest;
use Hwkdo\IntranetAppBestellungen\Models\IntranetAppBestellungenSettings;
use Hwkdo\IntranetAppBestellungen\Models\LieferantCache;
use Hwkdo\IntranetAppBestellungen\Services\Lieferant\FehlenderLieferantMeldungService;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

trait MeldetFehlendenLieferant
{
    public function meldeFehlendenLieferant(): void
    {
        $this->validate(
            MeldeFehlendenLieferantRequest::livewireValidationRules(),
            MeldeFehlendenLieferantRequest::validationMessages(),
            MeldeFehlendenLieferantRequest::validationAttributes(),
        );

        try {
            app(FehlenderLieferantMeldungService::class)->send(
                Auth::user(),
                $this->fehlenderLieferantName,
                $this->fehlenderLieferantAdresse ?: null,
                $this->fehlenderLieferantIban ?: null,
                $this->fehlenderLieferantWebseite ?: null,
            );
        } catch (\Throwable $e) {
            Flux::toast(
                heading: 'Fehler',
                text: $e->getMessage(),
                variant: 'error',
            );

            return;
        }

        $this->wendeUnbekanntenLieferantenAn();
        $this->resetFehlenderLieferantModal();
        Flux::modal('fehlender-lieferant')->close();
        Flux::toast(
            text: 'Fehlender Lieferant erfolgreich gemeldet',
            variant: 'success',
        );
    }

    protected function wendeUnbekanntenLieferantenAn(): void
    {
        $nummer = IntranetAppBestellungenSettings::resolvedAppSettings()->unbekannterLieferantennummer;
        $this->lieferantennummer = $nummer;

        $lieferant = LieferantCache::query()->where('lieferantennummer', $nummer)->first();

        if ($lieferant) {
            $this->lieferantenname = $lieferant->lieferantenname;

            return;
        }

        Log::warning('Platzhalter-Lieferant nicht im Stammdaten-Cache', ['lieferantennummer' => $nummer]);
        $this->lieferantenname = 'Unbekannter Lieferant';
    }

    protected function resetFehlenderLieferantModal(): void
    {
        $this->fehlenderLieferantName = '';
        $this->fehlenderLieferantAdresse = '';
        $this->fehlenderLieferantIban = '';
        $this->fehlenderLieferantWebseite = '';
    }

    protected function fillLieferantennameFromCache(string $nummer): void
    {
        $lieferant = LieferantCache::query()->where('lieferantennummer', $nummer)->first();
        if ($lieferant) {
            $this->lieferantenname = $lieferant->lieferantenname;
        }
    }

    protected function ensureLieferantennameFromCache(): void
    {
        if (! filled($this->lieferantennummer) || filled($this->lieferantenname)) {
            return;
        }

        $this->fillLieferantennameFromCache($this->lieferantennummer);
    }
}
