<?php

declare(strict_types=1);

namespace Hwkdo\IntranetAppBestellungen\Services;

use App\Models\User;
use Hwkdo\IntranetAppBestellungen\Enums\AktionTyp;
use Hwkdo\IntranetAppBestellungen\Exceptions\WorkflowException;
use Hwkdo\IntranetAppBestellungen\Models\Angebot;
use Hwkdo\IntranetAppBestellungen\Models\Auftragszeile;
use Hwkdo\IntranetAppBestellungen\Models\Bestellung;
use Hwkdo\IntranetAppBestellungen\Models\LieferantCache;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class AuftragszeilenService
{
    public function __construct(private readonly BestellungWorkflow $workflow) {}

    /**
     * @param  list<int>  $angebotIds
     */
    public function ausAngeboten(Bestellung $bestellung, array $angebotIds, ?User $user): int
    {
        $angebotIds = array_values(array_unique(array_filter(
            array_map(static fn (mixed $id): int => (int) $id, $angebotIds),
            static fn (int $id): bool => $id > 0,
        )));

        if ($angebotIds === []) {
            return 0;
        }

        return DB::transaction(function () use ($bestellung, $angebotIds, $user): int {
            $angebote = $bestellung->angebote()
                ->where('typ', 'angebot')
                ->whereIn('id', $angebotIds)
                ->whereDoesntHave('auftragszeile')
                ->orderBy('id')
                ->get();

            $unvollstaendig = $angebote->first(fn (Angebot $angebot): bool => ! $angebot->angabenVollstaendig());
            if ($unvollstaendig instanceof Angebot) {
                $fehlend = implode(', ', $unvollstaendig->fehlendeAngaben());

                throw new WorkflowException('Das Angebot ist unvollständig ('.$fehlend.'). Tragen Sie die Angaben ein, bevor Sie es übernehmen.');
            }

            $this->auftragnehmerAusAngeboten($bestellung, $angebote);

            $nr = (int) $bestellung->auftragszeilen()->max('nr');

            foreach ($angebote as $angebot) {
                $nr++;
                $this->zeileAusAngebot($bestellung, $angebot, $nr);
            }

            if ($angebote->isEmpty()) {
                return 0;
            }

            $bestellung->refreshGesamtbetrag();
            $this->workflow->logAktion(
                $bestellung,
                $user,
                AktionTyp::AuftragszeileHinzugefuegt,
                $angebote->count() === 1
                    ? 'Eine Auftragszeile aus einem Angebot übernommen.'
                    : $angebote->count().' Auftragszeilen aus Angeboten übernommen.',
                payload: ['angebot_ids' => $angebote->modelKeys()],
            );

            return $angebote->count();
        });
    }

    /**
     * @param  array{
     *     angebotsdatum: string,
     *     angebotsnummer: string,
     *     betrag_netto: float|int|string,
     *     betrag_brutto?: float|int|string|null,
     *     freitext?: string|null,
     *     lieferantennummer?: string|null
     * }  $daten
     */
    public function manuell(Bestellung $bestellung, array $daten, ?User $user): Auftragszeile
    {
        return DB::transaction(function () use ($bestellung, $daten, $user): Auftragszeile {
            if (! filled($bestellung->lieferantenname)) {
                $this->auftragnehmerAusStammdaten($bestellung, (string) ($daten['lieferantennummer'] ?? ''));
            }

            $zeile = Auftragszeile::query()->create([
                'bestellung_id' => $bestellung->getKey(),
                'angebot_id' => null,
                'nr' => (int) $bestellung->auftragszeilen()->max('nr') + 1,
                'angebotsdatum' => $daten['angebotsdatum'],
                'angebotsnummer' => $daten['angebotsnummer'],
                'betrag_netto' => $daten['betrag_netto'],
                'betrag_brutto' => $this->nullableBetrag($daten['betrag_brutto'] ?? null),
                'freitext' => $this->nullableText($daten['freitext'] ?? null),
            ]);

            $bestellung->refreshGesamtbetrag();
            $this->workflow->logAktion(
                $bestellung,
                $user,
                AktionTyp::AuftragszeileHinzugefuegt,
                'Auftragszeile ohne hinterlegtes Angebot erfasst.',
                payload: ['auftragszeile_id' => $zeile->getKey()],
            );

            return $zeile;
        });
    }

    /**
     * @param  array{
     *     angebotsdatum?: string|null,
     *     angebotsnummer?: string|null,
     *     betrag_netto?: float|int|string|null,
     *     betrag_brutto?: float|int|string|null,
     *     freitext?: string|null
     * }  $daten
     */
    public function aktualisieren(Auftragszeile $zeile, array $daten, ?User $user): Auftragszeile
    {
        return DB::transaction(function () use ($zeile, $daten, $user): Auftragszeile {
            $zeile->loadMissing('bestellung');
            $zeile->fill([
                'angebotsdatum' => $daten['angebotsdatum'] ?? null,
                'angebotsnummer' => $daten['angebotsnummer'] ?? null,
                'betrag_netto' => $daten['betrag_netto'] ?? null,
                'betrag_brutto' => $this->nullableBetrag($daten['betrag_brutto'] ?? null),
                'freitext' => $this->nullableText($daten['freitext'] ?? null),
            ])->save();

            $bestellung = $zeile->bestellung;
            $bestellung->refreshGesamtbetrag();
            $this->workflow->logAktion(
                $bestellung,
                $user,
                AktionTyp::AuftragszeileGeaendert,
                'Auftragszeile '.$zeile->nr.' aktualisiert.',
                payload: ['auftragszeile_id' => $zeile->getKey()],
            );

            return $zeile;
        });
    }

    public function entfernen(Auftragszeile $zeile, ?User $user): void
    {
        DB::transaction(function () use ($zeile, $user): void {
            $zeile->loadMissing('bestellung');
            $bestellung = $zeile->bestellung;
            $id = $zeile->getKey();
            $nr = $zeile->nr;
            $zeile->delete();
            $this->neuNummerieren($bestellung);
            $this->auftragnehmerLoesenWennKeineZeilen($bestellung);
            $bestellung->refreshGesamtbetrag();
            $this->workflow->logAktion(
                $bestellung,
                $user,
                AktionTyp::AuftragszeileEntfernt,
                'Auftragszeile '.$nr.' entfernt.',
                payload: ['auftragszeile_id' => $id],
            );
        });
    }

    public function verschieben(Auftragszeile $zeile, int $richtung, ?User $user): void
    {
        if (! in_array($richtung, [-1, 1], true)) {
            return;
        }

        DB::transaction(function () use ($zeile, $richtung, $user): void {
            $zeile->loadMissing('bestellung');
            $bestellung = $zeile->bestellung;
            $nachbar = $bestellung->auftragszeilen()
                ->where('nr', $richtung < 0 ? '<' : '>', $zeile->nr)
                ->reorder('nr', $richtung < 0 ? 'desc' : 'asc')
                ->first();

            if (! $nachbar instanceof Auftragszeile) {
                return;
            }

            $eigeneNr = $zeile->nr;
            $zeile->update(['nr' => $nachbar->nr]);
            $nachbar->update(['nr' => $eigeneNr]);

            $this->workflow->logAktion(
                $bestellung,
                $user,
                AktionTyp::AuftragszeileGeaendert,
                'Reihenfolge der Auftragszeilen geändert.',
                payload: ['auftragszeile_id' => $zeile->getKey()],
            );
        });
    }

    public function neuNummerieren(Bestellung $bestellung): void
    {
        $zeilen = $bestellung->auftragszeilen()->reorder()->orderBy('nr')->orderBy('id')->get();

        foreach ($zeilen as $index => $zeile) {
            $ziel = $index + 1;
            if ((int) $zeile->nr !== $ziel) {
                $zeile->update(['nr' => $ziel]);
            }
        }
    }

    private function zeileAusAngebot(Bestellung $bestellung, Angebot $angebot, int $nr): Auftragszeile
    {
        return Auftragszeile::query()->create([
            'bestellung_id' => $bestellung->getKey(),
            'angebot_id' => $angebot->getKey(),
            'nr' => $nr,
            'angebotsdatum' => $angebot->angebotsdatum,
            'angebotsnummer' => $angebot->nummer,
            'betrag_netto' => $angebot->betrag_netto,
            'betrag_brutto' => $angebot->betrag,
            'freitext' => null,
        ]);
    }

    /**
     * @param  Collection<int, Angebot>  $angebote
     */
    private function auftragnehmerAusAngeboten(Bestellung $bestellung, Collection $angebote): void
    {
        $nummern = $angebote
            ->map(fn (Angebot $angebot): string => trim((string) $angebot->lieferantennummer))
            ->filter()
            ->unique()
            ->values();

        if ($nummern->count() > 1) {
            throw new WorkflowException('Die ausgewählten Angebote sind unterschiedlichen Lieferanten zugeordnet. Übernehmen Sie nur Angebote eines Auftragnehmers.');
        }

        if ($nummern->count() === 1) {
            $this->auftragnehmerAusNummer($bestellung, (string) $nummern->first());

            return;
        }

        $namen = $angebote
            ->map(fn (Angebot $angebot): string => $this->normalisierterName($angebot->lieferantenname))
            ->filter()
            ->unique()
            ->values();

        if ($namen->count() > 1) {
            throw new WorkflowException('Die ausgewählten Angebote stammen von unterschiedlichen Anbietern. Übernehmen Sie nur Angebote eines Auftragnehmers.');
        }

        $angebot = $angebote->first(fn (Angebot $angebot): bool => filled($angebot->lieferantenname));
        if (! $angebot instanceof Angebot) {
            return;
        }

        $dieserName = $this->normalisierterName($angebot->lieferantenname);
        $bestehend = $this->normalisierterName($bestellung->lieferantenname);

        if ($bestehend !== '' && $bestehend !== $dieserName) {
            throw new WorkflowException('Dieses Angebot stammt nicht vom Auftragnehmer '.$bestellung->lieferantenname.'.');
        }

        if ($bestehend === '') {
            $this->auftragnehmerSetzen($bestellung, trim((string) $angebot->lieferantenname));
        }
    }

    public function auftragnehmerAusNummer(Bestellung $bestellung, string $lieferantennummer): void
    {
        $lieferant = LieferantCache::query()->where('lieferantennummer', $lieferantennummer)->first();
        if (! $lieferant instanceof LieferantCache) {
            throw new WorkflowException('Der zugeordnete Lieferant ist im Stammdaten-Cache nicht vorhanden.');
        }

        $bestehend = trim((string) $bestellung->lieferantennummer);
        if ($bestehend !== '' && $bestehend !== $lieferant->lieferantennummer) {
            throw new WorkflowException('Dieses Angebot ist nicht dem Auftragnehmer '.$bestellung->lieferantenname.' zugeordnet.');
        }

        if ($bestehend === '') {
            $this->auftragnehmerSetzen($bestellung, (string) $lieferant->lieferantenname, $lieferant);
        }
    }

    public function auftragnehmerAusNamen(Bestellung $bestellung, string $name): void
    {
        $name = trim($name);
        if ($name === '') {
            return;
        }

        $bestehend = $this->normalisierterName($bestellung->lieferantenname);
        $dieserName = $this->normalisierterName($name);

        if ($bestehend !== '' && $bestehend !== $dieserName) {
            throw new WorkflowException('Der Auftragnehmer ist bereits '.$bestellung->lieferantenname.'.');
        }

        if ($bestehend === '') {
            $this->auftragnehmerSetzen($bestellung, $name);
        }
    }

    private function auftragnehmerAusStammdaten(Bestellung $bestellung, string $lieferantennummer): void
    {
        $lieferant = LieferantCache::query()->where('lieferantennummer', $lieferantennummer)->first();
        if (! $lieferant instanceof LieferantCache) {
            throw new WorkflowException('Bitte wählen Sie den Auftragnehmer aus den Stammdaten.');
        }

        $this->auftragnehmerSetzen($bestellung, (string) $lieferant->lieferantenname, $lieferant);
    }

    private function auftragnehmerSetzen(Bestellung $bestellung, string $name, ?LieferantCache $lieferant = null): void
    {
        $lieferant ??= $this->eindeutigerLieferant($name);

        $bestellung->forceFill([
            'lieferantenname' => $lieferant?->lieferantenname ?? $name,
            'lieferantennummer' => $lieferant?->lieferantennummer,
            'lieferanschrift' => $lieferant instanceof LieferantCache ? [
                'strasse' => $lieferant->strasse,
                'hausnummer' => $lieferant->hausnummer,
                'plz' => $lieferant->plz,
                'ort' => $lieferant->ort,
            ] : null,
        ])->save();
    }

    private function eindeutigerLieferant(string $name): ?LieferantCache
    {
        $treffer = LieferantCache::query()
            ->where('lieferantenname', $name)
            ->limit(2)
            ->get();

        return $treffer->count() === 1 ? $treffer->first() : null;
    }

    private function auftragnehmerLoesenWennKeineZeilen(Bestellung $bestellung): void
    {
        if (! $bestellung->istAuftragsvergabe() || $bestellung->auftragszeilen()->exists()) {
            return;
        }

        $bestellung->forceFill([
            'lieferantenname' => null,
            'lieferantennummer' => null,
            'lieferanschrift' => null,
        ])->save();
    }

    private function normalisierterName(mixed $name): string
    {
        return mb_strtolower(trim((string) $name));
    }

    private function nullableBetrag(mixed $value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        return is_numeric($value) ? (string) $value : null;
    }

    private function nullableText(mixed $value): ?string
    {
        if (! is_string($value)) {
            return null;
        }

        $trimmed = trim($value);

        return $trimmed === '' ? null : $trimmed;
    }
}
