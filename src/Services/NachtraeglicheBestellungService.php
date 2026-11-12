<?php

declare(strict_types=1);

namespace Hwkdo\IntranetAppBestellungen\Services;

use App\Models\User;
use Hwkdo\IntranetAppBestellungen\Data\NachtraeglicheBestellungEingabe;
use Hwkdo\IntranetAppBestellungen\Enums\AktionTyp;
use Hwkdo\IntranetAppBestellungen\Enums\BestellungStatus;
use Hwkdo\IntranetAppBestellungen\Enums\BestellungTyp;
use Hwkdo\IntranetAppBestellungen\Models\Bestellung;
use Hwkdo\IntranetAppBestellungen\Models\Kassenbon;
use Hwkdo\IntranetAppBestellungen\Models\LieferantNutzung;
use Hwkdo\IntranetAppBestellungen\Models\Position;
use Illuminate\Support\Facades\DB;

class NachtraeglicheBestellungService
{
    public function __construct(
        private readonly BenNumberService $benNumberService,
        private readonly BestellungWorkflow $workflow,
    ) {}

    public function abschliessen(Kassenbon $kassenbon, User $user, NachtraeglicheBestellungEingabe $eingabe): Bestellung
    {
        $bestellung = DB::transaction(function () use ($kassenbon, $user, $eingabe): Bestellung {
            $kassenbon->refresh();

            if ($kassenbon->bestellung_id !== null) {
                $bestehend = $kassenbon->bestellung;
                if ($bestehend instanceof Bestellung) {
                    return $bestehend;
                }
            }

            $bestellung = Bestellung::create([
                'nummer' => $this->benNumberService->next($user, $eingabe->haushaltsjahr),
                'status' => BestellungStatus::Entwurf,
                'typ' => BestellungTyp::Nachtraeglich,
                'lieferantennummer' => $eingabe->lieferantennummer,
                'lieferantenname' => $eingabe->lieferantenname,
                'kostenstelle' => $eingabe->kostenstelle,
                'haushaltsjahr' => $eingabe->haushaltsjahr,
                'betreff' => $eingabe->betreff,
                'begruendung' => $eingabe->begruendung,
                'kontierung' => $eingabe->kontierung,
                'gruppen' => $eingabe->gruppen,
                'lieferanschrift_user_id' => $eingabe->lieferanschriftUserId,
                'user_id' => $user->getKey(),
                'projekt_id' => $eingabe->projektId,
                'gesamtbetrag' => $eingabe->brutto,
            ]);

            foreach (array_values($eingabe->positionen) as $index => $position) {
                Position::create([
                    'bestellung_id' => $bestellung->getKey(),
                    'nr' => $index + 1,
                    'menge' => $position['menge'] ?? 1,
                    'einheit' => $position['einheit'] ?? null,
                    'art_nr' => $position['art_nr'] ?? null,
                    'bezeichnung' => $position['bezeichnung'] ?? '',
                    'preis' => $position['preis'] ?? 0,
                ]);
            }

            $kassenbon->forceFill([
                'bestellung_id' => $bestellung->getKey(),
                'belegnummer' => $eingabe->belegnummer !== '' ? $eingabe->belegnummer : '0',
                'belegdatum' => $eingabe->belegdatum,
                'nettobetrag' => $eingabe->netto,
                'steuerbetrag' => $eingabe->steuer,
                'bruttobetrag' => $eingabe->brutto,
                'zahlungsart' => $eingabe->zahlungsart,
            ])->save();

            if (filled($bestellung->lieferantennummer)) {
                LieferantNutzung::upsert(
                    [
                        'lieferantennummer' => $bestellung->lieferantennummer,
                        'v3_bestellungen_count' => 1,
                        'legacy_bestellungen_count' => 0,
                    ],
                    uniqueBy: ['lieferantennummer'],
                    update: ['v3_bestellungen_count' => DB::raw('v3_bestellungen_count + 1')],
                );
            }

            $this->workflow->logAktion(
                $bestellung,
                $user,
                AktionTyp::NachtraeglichErfasst,
                'Nachträgliche Bestellung aus Kassenbon erfasst.',
                ['kassenbon_id' => $kassenbon->getKey()],
            );

            return $bestellung->fresh(['kassenbon']) ?? $bestellung;
        });

        return $this->workflow->nachtraeglichAbschliessen($bestellung->fresh(['kassenbon']) ?? $bestellung, $user);
    }
}
