<?php

declare(strict_types=1);

namespace Hwkdo\IntranetAppBestellungen\Services;

use App\Models\Gvp;
use App\Models\User;
use Hwkdo\IntranetAppBestellungen\Data\AppSettings;
use Hwkdo\IntranetAppBestellungen\Data\FreigebeRegel;
use Hwkdo\IntranetAppBestellungen\Data\FreigabeStufe;
use Hwkdo\IntranetAppBestellungen\Enums\BestellungStatus;
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
     * Gibt alle Stufen aufsteigend nach bisBetrag sortiert zurück (null = unendlich, kommt zuletzt).
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
     * Ermittelt die zutreffende Stufe für einen Betrag (prüft von + bis, analog Legacy).
     */
    public function stufeFuerBetrag(float $betrag): ?FreigabeStufe
    {
        foreach ($this->stufenSortiert() as $stufe) {
            if ($stufe->bisBetrag === null) {
                if ($betrag >= $stufe->vonBetrag) {
                    return $stufe;
                }
            } else {
                if ($betrag >= $stufe->vonBetrag && $betrag <= $stufe->bisBetrag) {
                    return $stufe;
                }
            }
        }

        return null;
    }

    /**
     * Prüft, ob ein User in der gegebenen Betragsklasse bestellen darf (analog Legacy darfBestellen).
     */
    public function darfBestellen(User $user, float $betrag): bool
    {
        $stufe = $this->stufeFuerBetrag($betrag);
        if (! $stufe) {
            return false;
        }

        foreach ($stufe->berechtigteAttribute as $attribut) {
            if ($user->{$attribut}) {
                return true;
            }
        }

        foreach ($stufe->berechtigteRollen as $rolle) {
            if ($user->hasRole($rolle)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Brauchen wir eine zweite Freigabe? Ja, wenn freigabe2Regeln nicht leer.
     */
    public function zweiteFreigabeNoetig(float $betrag): bool
    {
        $stufe = $this->stufeFuerBetrag($betrag);

        return $stufe?->hatZweiteFreigabe() ?? false;
    }

    /**
     * Liefert alle möglichen Freigeber 1 für eine Bestellung.
     *
     * @return Collection<int, User>
     */
    public function freigeber1FuerBestellung(Bestellung $bestellung): Collection
    {
        $stufe = $this->stufeFuerBetrag((float) $bestellung->gesamtbetrag);
        if (! $stufe) {
            return collect();
        }

        $user = $bestellung->user;
        if (! $user) {
            return collect();
        }

        return $this->resolveFreigeber($user, $stufe->freigabe1RegelObjekte())
            ->reject(fn (User $u): bool => $u->getKey() === $bestellung->user_id)
            ->unique('id')
            ->values();
    }

    /**
     * Liefert alle möglichen Freigeber 2 für eine Bestellung.
     *
     * @return Collection<int, User>
     */
    public function freigeber2FuerBestellung(Bestellung $bestellung): Collection
    {
        $stufe = $this->stufeFuerBetrag((float) $bestellung->gesamtbetrag);
        if (! $stufe || ! $stufe->hatZweiteFreigabe()) {
            return collect();
        }

        $user = $bestellung->user;
        if (! $user) {
            return collect();
        }

        return $this->resolveFreigeber($user, $stufe->freigabe2RegelObjekte())
            ->reject(fn (User $u): bool => $u->getKey() === $bestellung->user_id)
            ->unique('id')
            ->values();
    }

    /**
     * @deprecated Verwende freigeber1FuerBestellung() oder freigeber2FuerBestellung().
     *
     * @return Collection<int, User>
     */
    public function freigeberFuerBestellung(Bestellung $bestellung): Collection
    {
        return $this->freigeber1FuerBestellung($bestellung);
    }

    /**
     * Prüft, ob ein User die Bestellung auf ihrer aktuellen Stufe freigeben darf.
     * Status-bewusst: ZurFreigabe → Freigeber-1-Pool, ZurZweitenFreigabe → Freigeber-2-Pool.
     */
    public function darfFreigeben(User $user, Bestellung $bestellung): bool
    {
        return match ($bestellung->status) {
            BestellungStatus::ZurFreigabe => $this->freigeber1FuerBestellung($bestellung)
                ->contains(fn (User $u): bool => $u->getKey() === $user->getKey()),
            BestellungStatus::ZurZweitenFreigabe => $this->freigeber2FuerBestellung($bestellung)
                ->contains(fn (User $u): bool => $u->getKey() === $user->getKey()),
            default => false,
        };
    }

    /**
     * Kern der Freigeber-Auflösung – analog Legacy makeFreigeber().
     *
     * @param  array<int, FreigebeRegel>  $regeln
     * @return Collection<int, User>
     */
    private function resolveFreigeber(User $user, array $regeln): Collection
    {
        $freigeber = collect();

        foreach ($regeln as $regel) {
            if ($regel->typ === 'if_attribute') {
                $attribut = $regel->bedingung;
                if ($attribut && $user->{$attribut}) {
                    if ($regel->keinFreigeber) {
                        return collect();
                    }

                    $freigeber = $freigeber->merge($this->resolveQuelle($user, $regel->quelleTyp, $regel->quelle));
                }
            } elseif ($regel->typ === 'if_rolle') {
                $rolle = $regel->bedingung;
                if ($rolle && $user->hasRole($rolle)) {
                    if ($regel->keinFreigeber) {
                        return collect();
                    }

                    $freigeber = $freigeber->merge($this->resolveQuelle($user, $regel->quelleTyp, $regel->quelle));
                }
            } elseif ($regel->typ === 'default') {
                if ($regel->keinFreigeber) {
                    return $freigeber;
                }

                // Ausnahme-Attribute: User mit diesen Attributen überspringen den default-Zweig
                foreach ($regel->excludeAttribute as $attr) {
                    if ($user->{$attr}) {
                        return $freigeber;
                    }
                }

                $freigeber = $freigeber->merge($this->resolveQuelle($user, $regel->quelleTyp, $regel->quelle));
            }
        }

        return $freigeber;
    }

    /**
     * Löst eine Quelle auf: single (eine Person), multi (Methode die Collection liefert),
     * oder gruppe (alle Vorgesetzten aller GVP-Einträge mit dem Kürzel).
     *
     * @return Collection<int, User>
     */
    private function resolveQuelle(User $user, string $quelleTyp, string $quelle): Collection
    {
        return match ($quelleTyp) {
            'single' => $this->resolveSingle($user, $quelle),
            'multi' => $this->resolveMulti($user, $quelle),
            'gruppe' => $this->resolveGruppe($quelle),
            default => collect(),
        };
    }

    /**
     * @return Collection<int, User>
     */
    private function resolveSingle(User $user, string $quelle): Collection
    {
        $result = method_exists($user, $quelle)
            ? $user->{$quelle}()
            : $user->{$quelle};

        if ($result instanceof User) {
            return collect([$result]);
        }

        return collect();
    }

    /**
     * @return Collection<int, User>
     */
    private function resolveMulti(User $user, string $quelle): Collection
    {
        $result = $user->{$quelle}();

        if ($result instanceof Collection) {
            return $result->filter();
        }

        return collect();
    }

    /**
     * Löst eine GVP-Gruppe auf: alle Vorgesetzten (und ggf. Stellvertreter) von GVP-Einträgen
     * mit dem angegebenen Kürzel (z. B. 'GB' für GF, 'HGF' für HGF).
     *
     * @return Collection<int, User>
     */
    private function resolveGruppe(string $kuerzel): Collection
    {
        return Gvp::where('kuerzel', $kuerzel)
            ->with('vorgesetzter')
            ->get()
            ->pluck('vorgesetzter')
            ->filter()
            ->unique('id')
            ->values();
    }
}
