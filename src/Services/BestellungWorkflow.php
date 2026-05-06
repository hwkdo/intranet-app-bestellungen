<?php

declare(strict_types=1);

namespace Hwkdo\IntranetAppBestellungen\Services;

use App\Models\User;
use Hwkdo\IntranetAppBestellungen\Enums\AktionTyp;
use Hwkdo\IntranetAppBestellungen\Enums\BestellungStatus;
use Hwkdo\IntranetAppBestellungen\Exceptions\WorkflowException;
use Hwkdo\IntranetAppBestellungen\Jobs\PushBestellscheinToD3Job;
use Hwkdo\IntranetAppBestellungen\Models\Aktion;
use Hwkdo\IntranetAppBestellungen\Models\Bestellung;
use Hwkdo\IntranetAppBestellungen\Models\IntranetAppBestellungenSettings;
use Illuminate\Support\Facades\DB;

class BestellungWorkflow
{
    public function __construct(
        private readonly WertgrenzenService $wertgrenzen,
        private readonly AngebotsregelService $angebotsregeln,
    ) {}

    public function einreichen(Bestellung $bestellung, User $user): Bestellung
    {
        $this->ensureStatus($bestellung, [BestellungStatus::Entwurf]);

        // Prüfen ob der User in dieser Betragsklasse bestellen darf
        if (! $this->wertgrenzen->darfBestellen($user, (float) $bestellung->gesamtbetrag)) {
            throw new WorkflowException(
                'Sie sind nicht berechtigt, in dieser Betragsklasse zu bestellen.',
            );
        }

        if (! $this->angebotsregeln->istFreigabeReady($bestellung)) {
            throw new WorkflowException('Es fehlen erforderliche Vergleichsangebote oder eine Begründung.');
        }

        if (! $bestellung->freigeber_id) {
            $erstFreigeber = $this->wertgrenzen
                ->freigeber1FuerBestellung($bestellung)
                ->first();

            if ($erstFreigeber) {
                $bestellung->freigeber_id = $erstFreigeber->getKey();
                $bestellung->save();
            }
        }

        // Wenn kein Freigeber nötig (z. B. Stufe a mit direkt-berechtigter Rolle) ist das ok
        // Wenn jedoch ein Freigeber erwartet wird (Pool nicht leer war, aber keiner gesetzt) → Exception
        $pool = $this->wertgrenzen->freigeber1FuerBestellung($bestellung);
        if (! $bestellung->freigeber_id && $pool->isNotEmpty()) {
            throw new WorkflowException(
                'Die Bestellung kann nicht eingereicht werden: Für den Betrag ist kein Freigeber in den Wertgrenzen konfiguriert.',
            );
        }

        return $this->transition($bestellung, $user, BestellungStatus::ZurFreigabe, AktionTyp::ZurFreigabeEingereicht);
    }

    public function weiterleiten(Bestellung $bestellung, User $user, int $neuerFreigeberId, ?string $nachricht = null): Bestellung
    {
        $this->ensureStatus($bestellung, [BestellungStatus::ZurFreigabe, BestellungStatus::ZurZweitenFreigabe]);

        return DB::transaction(function () use ($bestellung, $user, $neuerFreigeberId, $nachricht): Bestellung {
            $bestellung->freigeber_id = $neuerFreigeberId;
            $bestellung->save();

            $this->logAktion($bestellung, $user, AktionTyp::Weitergeleitet, $nachricht, [
                'neuer_freigeber_id' => $neuerFreigeberId,
            ]);

            return $bestellung->refresh();
        });
    }

    public function freigeben(Bestellung $bestellung, User $user, ?string $nachricht = null): Bestellung
    {
        $this->ensureStatus($bestellung, [BestellungStatus::ZurFreigabe, BestellungStatus::ZurZweitenFreigabe]);

        return DB::transaction(function () use ($bestellung, $user, $nachricht): Bestellung {
            if (
                $bestellung->status === BestellungStatus::ZurFreigabe
                && $this->wertgrenzen->zweiteFreigabeNoetig((float) $bestellung->gesamtbetrag)
            ) {
                $this->transition($bestellung, $user, BestellungStatus::ZurZweitenFreigabe, AktionTyp::ErstFreigegeben, $nachricht);

                // Freigeber 2 automatisch zuweisen
                $freigeber2 = $this->wertgrenzen->freigeber2FuerBestellung($bestellung)->first();
                if ($freigeber2) {
                    $bestellung->freigeber_id = $freigeber2->getKey();
                    $bestellung->save();
                } else {
                    $bestellung->freigeber_id = null;
                    $bestellung->save();
                }

                return $bestellung->refresh();
            }

            return $this->transition($bestellung, $user, BestellungStatus::Freigegeben, AktionTyp::Freigegeben, $nachricht);
        });
    }

    public function ablehnen(Bestellung $bestellung, User $user, string $nachricht): Bestellung
    {
        $this->ensureStatus($bestellung, [BestellungStatus::ZurFreigabe, BestellungStatus::ZurZweitenFreigabe]);

        return $this->transition($bestellung, $user, BestellungStatus::Abgelehnt, AktionTyp::Abgelehnt, $nachricht);
    }

    public function bestellen(Bestellung $bestellung, User $user, ?string $nachricht = null): Bestellung
    {
        $this->ensureStatus($bestellung, [BestellungStatus::Freigegeben]);

        return DB::transaction(function () use ($bestellung, $user, $nachricht): Bestellung {
            $bestellung->besteller_id = $user->getKey();
            $bestellung->bestellt_at = now();
            $bestellung->save();

            $this->transition($bestellung, $user, BestellungStatus::Bestellt, AktionTyp::Bestellt, $nachricht);

            if (IntranetAppBestellungenSettings::resolvedAppSettings()->autoPushBeiBestellt) {
                PushBestellscheinToD3Job::dispatch($bestellung->getKey())->afterCommit();
            }

            return $bestellung->refresh();
        });
    }

    public function logAktion(
        Bestellung $bestellung,
        ?User $user,
        AktionTyp $typ,
        ?string $nachricht = null,
        ?array $payload = null,
    ): Aktion {
        return Aktion::create([
            'bestellung_id' => $bestellung->getKey(),
            'user_id' => $user?->getKey(),
            'typ' => $typ->value,
            'von_status' => $bestellung->getOriginal('status') ?: $bestellung->status?->value,
            'nach_status' => $bestellung->status?->value,
            'nachricht' => $nachricht,
            'payload' => $payload,
        ]);
    }

    /**
     * @param  array<int, BestellungStatus>  $erlaubte
     */
    private function ensureStatus(Bestellung $bestellung, array $erlaubte): void
    {
        if (! in_array($bestellung->status, $erlaubte, true)) {
            throw new WorkflowException(sprintf(
                'Aktion im Status "%s" nicht erlaubt.',
                $bestellung->status?->value ?? 'unbekannt',
            ));
        }
    }

    private function transition(
        Bestellung $bestellung,
        User $user,
        BestellungStatus $neuerStatus,
        AktionTyp $aktion,
        ?string $nachricht = null,
    ): Bestellung {
        return DB::transaction(function () use ($bestellung, $user, $neuerStatus, $aktion, $nachricht): Bestellung {
            $vorherStatus = $bestellung->status?->value;

            $bestellung->status = $neuerStatus;
            $bestellung->save();

            Aktion::create([
                'bestellung_id' => $bestellung->getKey(),
                'user_id' => $user->getKey(),
                'typ' => $aktion->value,
                'von_status' => $vorherStatus,
                'nach_status' => $neuerStatus->value,
                'nachricht' => $nachricht,
            ]);

            return $bestellung->refresh();
        });
    }
}
