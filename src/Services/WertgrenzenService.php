<?php

declare(strict_types=1);

namespace Hwkdo\IntranetAppBestellungen\Services;

use App\Models\User;
use Hwkdo\IntranetAppBestellungen\Data\AppSettings;
use Hwkdo\IntranetAppBestellungen\Data\FreigabeStufe;
use Hwkdo\IntranetAppBestellungen\Models\Bestellung;
use Hwkdo\IntranetAppBestellungen\Models\IntranetAppBestellungenSettings;
use Illuminate\Support\Collection;

class WertgrenzenService
{
    public function __construct(
        private readonly ?AppSettings $settingsOverride = null,
    ) {}

    public function settings(): AppSettings
    {
        return $this->settingsOverride ?? IntranetAppBestellungenSettings::resolvedAppSettings();
    }

    /**
     * Liefert alle Stufen aufsteigend nach `bisBetrag` (null = unendlich, kommt zuletzt).
     *
     * @return array<int, FreigabeStufe>
     */
    public function stufenSortiert(): array
    {
        $stufen = $this->settings()->freigabeStufenObjekte();

        usort(
            $stufen,
            static fn (FreigabeStufe $a, FreigabeStufe $b): int => match (true) {
                $a->bisBetrag === null && $b->bisBetrag === null => 0,
                $a->bisBetrag === null => 1,
                $b->bisBetrag === null => -1,
                default => $a->bisBetrag <=> $b->bisBetrag,
            },
        );

        return $stufen;
    }

    /**
     * Ermittelt die zutreffende Stufe für einen Betrag.
     */
    public function stufeFuerBetrag(float $betrag): ?FreigabeStufe
    {
        foreach ($this->stufenSortiert() as $stufe) {
            if ($stufe->bisBetrag === null) {
                return $stufe;
            }
            if ($betrag <= $stufe->bisBetrag) {
                return $stufe;
            }
        }

        return null;
    }

    /**
     * Brauchen wir zwei Freigaben?
     */
    public function zweiteFreigabeNoetig(float $betrag): bool
    {
        $stufe = $this->stufeFuerBetrag($betrag);
        if (! $stufe) {
            return false;
        }
        if ($stufe->zweiteFreigabeErforderlich) {
            return true;
        }
        if ($stufe->zweiteFreigabeAb !== null && $betrag >= $stufe->zweiteFreigabeAb) {
            return true;
        }

        return false;
    }

    /**
     * Resolves all freigeber users for a Bestellung based on the matching Stufe.
     *
     * @return Collection<int, User>
     */
    public function freigeberFuerBestellung(Bestellung $bestellung): Collection
    {
        $betrag = (float) $bestellung->gesamtbetrag;
        $stufe = $this->stufeFuerBetrag($betrag);
        if (! $stufe) {
            return collect();
        }

        $bestellung->loadMissing(['user.gvp.parent.vorgesetzter', 'user.gvp.vorgesetzter']);

        $hierarchieFreigeber = $bestellung->user?->getVorgesetzte() ?? collect();
        $konfigFreigeber = $this->resolveUsers($stufe);

        return $hierarchieFreigeber
            ->merge($konfigFreigeber)
            ->unique('id')
            ->reject(fn (User $user): bool => $user->getKey() === $bestellung->user_id)
            ->values();
    }

    /**
     * @return Collection<int, User>
     */
    public function resolveUsers(FreigabeStufe $stufe): Collection
    {
        $byId = User::query()
            ->whereIn('id', array_filter($stufe->freigeberUserIds))
            ->get();

        $byRole = collect();
        if (! empty($stufe->freigeberRollen)) {
            $byRole = User::query()
                ->whereHas('roles', fn ($q) => $q->whereIn('name', $stufe->freigeberRollen))
                ->get();
        }

        return $byId->merge($byRole)->unique('id')->values();
    }

    /**
     * Prüft, ob ein User eine Bestellung freigeben darf (auf der aktuellen Stufe).
     */
    public function darfFreigeben(User $user, Bestellung $bestellung): bool
    {
        return $this->freigeberFuerBestellung($bestellung)
            ->contains(fn (User $u): bool => $u->getKey() === $user->getKey());
    }
}
