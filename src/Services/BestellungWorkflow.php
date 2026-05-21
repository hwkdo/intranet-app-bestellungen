<?php

declare(strict_types=1);

namespace Hwkdo\IntranetAppBestellungen\Services;

use App\Models\User;
use Hwkdo\IntranetAppBestellungen\Enums\AktionTyp;
use Hwkdo\IntranetAppBestellungen\Enums\BestellungStatus;
use Hwkdo\IntranetAppBestellungen\Exceptions\WorkflowException;
use Hwkdo\IntranetAppBestellungen\Models\Aktion;
use Hwkdo\IntranetAppBestellungen\Models\Bestellung;
use Hwkdo\IntranetAppBestellungen\Models\IntranetAppBestellungenSettings;
use Hwkdo\IntranetAppBestellungen\Services\D3\BestellscheinD3Service;
use Hwkdo\D3RestLaravel\Client as D3Client;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class BestellungWorkflow
{
    public function __construct(
        private readonly WertgrenzenService $wertgrenzen,
        private readonly AngebotsregelService $angebotsregeln,
        private readonly BestellscheinD3Service $bestellscheinD3Service,
    ) {}

    public function einreichen(Bestellung $bestellung, User $user, ?int $freigeberId = null): Bestellung
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

        if ($freigeberId !== null) {
            $bestellung->freigeber_id = $freigeberId;
            $bestellung->save();
        }

        $pool = $this->wertgrenzen->freigeber1FuerBestellung($bestellung);
        if ($pool->isNotEmpty()) {
            $zulaessigeIds = $this->zulaessigeEinreichFreigeberIds($bestellung, $pool);

            if (! $bestellung->freigeber_id) {
                if (count($zulaessigeIds) === 1) {
                    $bestellung->freigeber_id = $zulaessigeIds[0];
                    $bestellung->save();
                } else {
                    throw new WorkflowException(
                        'Es konnte kein eindeutiger Freigeber ermittel werden. Bitte Freigeber auswählen',
                    );
                }
            }

            $freigeberIstImPool = in_array((int) $bestellung->freigeber_id, $zulaessigeIds, true);

            if (! $freigeberIstImPool) {
                throw new WorkflowException(
                    'Der ausgewählte Freigeber ist für diese Bestellung nicht zulässig.',
                );
            }
        } elseif (! $bestellung->freigeber_id && $pool->isEmpty()) {
            if ($this->wertgrenzen->istFreigeber1NichtNoetig($bestellung)) {
                return $this->transition(
                    $bestellung,
                    $user,
                    BestellungStatus::Freigegeben,
                    AktionTyp::Freigegeben,
                    'Kein Freigeber nötig (laut Regelwerk). Bestellung automatisch freigegeben.',
                );
            }

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
        $this->ensureDarfBestellenAbschliessen($bestellung, $user);

        if (! IntranetAppBestellungenSettings::resolvedAppSettings()->autoPushBeiBestellt) {
            return DB::transaction(function () use ($bestellung, $user, $nachricht): Bestellung {
                $bestellung->besteller_id = $user->getKey();
                $bestellung->bestellt_at = now();
                $bestellung->save();

                $this->transition($bestellung, $user, BestellungStatus::Bestellt, AktionTyp::Bestellt, $nachricht);

                return $bestellung->refresh();
            });
        }

        try {
            $d3Id = $this->bestellscheinD3Service->push($bestellung, $user);
            if (! $d3Id) {
                throw new WorkflowException('Die D3-Übertragung ist fehlgeschlagen. Die Bestellung bleibt im Status "Freigegeben".');
            }
        } catch (WorkflowException $e) {
            throw $e;
        } catch (\Throwable $e) {
            throw new WorkflowException('Die D3-Übertragung ist fehlgeschlagen. Die Bestellung bleibt im Status "Freigegeben": '.$e->getMessage());
        }

        return DB::transaction(function () use ($bestellung, $user, $nachricht): Bestellung {
            $bestellung->besteller_id = $user->getKey();
            $bestellung->bestellt_at = now();
            $bestellung->save();

            $this->transition($bestellung, $user, BestellungStatus::Bestellt, AktionTyp::Bestellt, $nachricht);

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

    /**
     * @param  Collection<int, User>  $pool
     * @return array<int, int>
     */
    private function zulaessigeEinreichFreigeberIds(Bestellung $bestellung, Collection $pool): array
    {
        $besteller = $bestellung->user;
        $fallbackVorgesetzte = $besteller
            ? $besteller->getVorgesetzte()
                ->reject(fn (User $u): bool => $u->getKey() === $besteller->getKey())
                ->unique('id')
            : collect();

        $ids = collect();

        foreach ($pool as $kandidat) {
            $vertretung = $this->d3VertretungWennAbwesend($kandidat);

            if ($vertretung['is_absent'] === false) {
                $ids->push((int) $kandidat->getKey());
                continue;
            }

            if ($vertretung['deputy'] instanceof User) {
                $ids->push((int) $vertretung['deputy']->getKey());
                continue;
            }

            $ids = $ids->merge(
                $fallbackVorgesetzte->map(fn (User $u): int => (int) $u->getKey())
            );
        }

        // Defensive Fallback: serverseitig niemals enger validieren als der fachliche Kontext.
        // So vermeiden wir Fehlablehnungen bei kurzzeitigen D3-Schwankungen.
        $ids = $ids->merge(
            $pool->map(fn (User $u): int => (int) $u->getKey())
        );

        $ids = $ids->merge(
            $fallbackVorgesetzte->map(fn (User $u): int => (int) $u->getKey())
        );

        return $ids->unique()->values()->all();
    }

    /**
     * @return array{is_absent: bool, deputy: ?User}
     */
    private function d3VertretungWennAbwesend(User $kandidat): array
    {
        try {
            $client = app(D3Client::class);
            $d3UserId = $client->getUserIdByUsername($kandidat->username);

            if (! $d3UserId) {
                return ['is_absent' => false, 'deputy' => null];
            }

            $absence = $client->getUserAbsence($d3UserId);

            return [
                'is_absent' => (bool) $absence->abwesend,
                'deputy' => $absence->vertreter instanceof User ? $absence->vertreter : null,
            ];
        } catch (\Throwable) {
            return ['is_absent' => false, 'deputy' => null];
        }
    }

    private function ensureDarfBestellenAbschliessen(Bestellung $bestellung, User $user): void
    {
        if ($bestellung->istIntern()) {
            if ($bestellung->interner_empfaenger_user_id === null) {
                throw new WorkflowException('Für interne Bestellungen ist kein interner Empfänger hinterlegt.');
            }

            if ((int) $bestellung->interner_empfaenger_user_id !== (int) $user->getKey()) {
                throw new WorkflowException('Nur der interne Empfänger kann diese Bestellung abschließen und an D3 übergeben.');
            }

            if ($bestellung->benoetigtFinalenLieferantenVorD3()) {
                throw new WorkflowException(
                    'Bitte wählen Sie den tatsächlichen Lieferanten, bevor der Bestellschein nach D3 übertragen wird.',
                );
            }

            return;
        }

        if (! $user->can('manage-app-bestellungen')) {
            throw new WorkflowException('Sie sind nicht berechtigt, diese Bestellung als bestellt zu markieren.');
        }
    }
}
