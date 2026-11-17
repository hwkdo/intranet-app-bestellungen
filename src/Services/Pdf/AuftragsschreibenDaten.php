<?php

declare(strict_types=1);

namespace Hwkdo\IntranetAppBestellungen\Services\Pdf;

use App\Models\User;
use Hwkdo\IntranetAppBestellungen\Models\Angebot;
use Hwkdo\IntranetAppBestellungen\Models\Auftragszeile;
use Hwkdo\IntranetAppBestellungen\Models\Bestellung;
use Hwkdo\IntranetAppBestellungen\Models\LieferantCache;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

class AuftragsschreibenDaten
{
    /**
     * Platzhalter für das Auftragsschreiben. Die Anschrift entspricht der Auflösung im Bestellschein.
     *
     * @return array{
     *     empfaengerZeilen: list<string>,
     *     auftragnehmerEmail: ?string,
     *     datum: string,
     *     bezug: string,
     *     betreff: string,
     *     anrede: string,
     *     angebotswert: string,
     *     mehrereAngebote: bool,
     *     frist: string,
     *     zeichen: string,
     *     ansprechpartnerName: string,
     *     telefon: ?string,
     *     telefax: ?string,
     *     email: ?string,
     *     unterzeichnerName: string,
     *     funktion: ?string,
     *     anlage: string
     * }
     */
    public function fuer(Bestellung $bestellung): array
    {
        $bestellung->loadMissing(['auftragszeilen.angebot', 'besteller', 'user']);

        $positionen = $this->positionen($bestellung);
        $lieferant = $this->lieferant($bestellung);
        $kontakt = $this->vorbelegungAusAngeboten($bestellung);
        $anrede = $bestellung->auftragnehmer_anrede === null
            ? $kontakt['anrede']
            : $this->normalisierteAnrede($bestellung->auftragnehmer_anrede);
        $ansprechpartner = $bestellung->auftragnehmer_ansprechpartner === null
            ? $kontakt['ansprechpartner']
            : $this->text($bestellung->auftragnehmer_ansprechpartner);
        $person = $bestellung->besteller ?? $bestellung->user;
        $brutto = $this->bruttoSumme($positionen);
        $mehrere = $positionen->count() > 1;

        return [
            'empfaengerZeilen' => $this->empfaengerZeilen($lieferant, $anrede, $ansprechpartner),
            'auftragnehmerEmail' => $bestellung->auftragnehmer_email === null
                ? $kontakt['email']
                : $this->text($bestellung->auftragnehmer_email),
            'datum' => ($bestellung->bestellt_at ?? now())->locale('de')->translatedFormat('j. F Y'),
            'bezug' => $this->bezugszeile($positionen),
            'betreff' => $this->text($bestellung->betreff) ?? '',
            'anrede' => $this->anredeSatz($anrede, $ansprechpartner),
            'angebotswert' => $brutto === null ? '–' : $this->euro($brutto),
            'mehrereAngebote' => $mehrere,
            'frist' => $bestellung->ausfuehrungsfrist?->format('d.m.Y') ?? '–',
            'zeichen' => $person instanceof User ? mb_strtoupper($person->initials()) : '',
            'ansprechpartnerName' => $this->personenName($person),
            'telefon' => $person instanceof User ? $this->text($person->telefon) : null,
            'telefax' => $person instanceof User ? $this->text($person->fax) : null,
            'email' => $person instanceof User ? $this->text($person->email) : null,
            'unterzeichnerName' => $this->personenName($person),
            'funktion' => $person instanceof User ? $this->text($person->title) : null,
            'anlage' => $mehrere ? 'Angebote' : 'Angebot',
        ];
    }

    /**
     * Erste Kontaktdaten aus den Angeboten der Auftragszeilen.
     *
     * @return array{anrede: ?string, ansprechpartner: ?string, email: ?string}
     */
    public function vorbelegungAusAngeboten(Bestellung $bestellung): array
    {
        $bestellung->loadMissing('auftragszeilen.angebot');

        $leer = [
            'anrede' => null,
            'ansprechpartner' => null,
            'email' => null,
        ];

        $angebote = $bestellung->auftragszeilen
            ->map(fn (Auftragszeile $zeile): ?Angebot => $zeile->angebot)
            ->filter(fn (?Angebot $angebot): bool => $angebot instanceof Angebot);

        foreach ($angebote as $angebot) {
            $contact = $angebot->extraction_payload['contact'] ?? null;
            if (! is_array($contact)) {
                continue;
            }

            $anrede = $this->normalisierteAnrede($contact['salutation'] ?? null);
            $name = $this->text($contact['person'] ?? null);
            $email = $this->text($contact['email'] ?? null);

            if ($anrede === null && $name === null && $email === null) {
                continue;
            }

            return [
                'anrede' => $anrede,
                'ansprechpartner' => $name,
                'email' => $email,
            ];
        }

        return $leer;
    }

    /**
     * @return list<string>
     */
    public function fehlendeAngaben(Bestellung $bestellung): array
    {
        $bestellung->loadMissing('auftragszeilen.angebot');

        $fehlend = [];

        if ($bestellung->ausfuehrungsfrist === null) {
            $fehlend[] = 'Ausführungsfrist';
        }

        if (! filled($bestellung->betreff)) {
            $fehlend[] = 'Betreff';
        }

        $positionen = $this->positionen($bestellung);

        if ($positionen->isEmpty() || $positionen->contains(fn (array $position): bool => $position['brutto'] === null)) {
            $fehlend[] = 'Bruttobetrag';
        }

        $lieferant = $this->lieferant($bestellung);
        $strasse = trim(((string) ($lieferant['strasse'] ?? '')).' '.((string) ($lieferant['hausnummer'] ?? '')));
        $ort = trim(((string) ($lieferant['plz'] ?? '')).' '.((string) ($lieferant['ort'] ?? '')));

        if (! filled($lieferant['name']) || $strasse === '' || $ort === '') {
            $fehlend[] = 'Anschrift des Auftragnehmers';
        }

        return $fehlend;
    }

    /**
     * @param  array{name: ?string, strasse: ?string, hausnummer: ?string, plz: ?string, ort: ?string}  $lieferant
     * @return list<string>
     */
    private function empfaengerZeilen(array $lieferant, ?string $anrede, ?string $ansprechpartner): array
    {
        $zeilen = [];

        if (filled($lieferant['name'])) {
            $zeilen[] = (string) $lieferant['name'];
        }

        $zuHaenden = $this->zuHaenden($anrede, $ansprechpartner);
        if ($zuHaenden !== null) {
            $zeilen[] = $zuHaenden;
        }

        $strasse = trim(((string) ($lieferant['strasse'] ?? '')).' '.((string) ($lieferant['hausnummer'] ?? '')));
        if ($strasse !== '') {
            $zeilen[] = $strasse;
        }

        $ort = trim(((string) ($lieferant['plz'] ?? '')).' '.((string) ($lieferant['ort'] ?? '')));
        if ($ort !== '') {
            $zeilen[] = $ort;
        }

        return $zeilen;
    }

    /**
     * Nur übernommene Auftragszeilen. Der Bruttobetrag kommt aus der Zeile oder aus dem verknüpften Angebot.
     *
     * @return Collection<int, array{nummer: ?string, datum: ?Carbon, brutto: ?float}>
     */
    private function positionen(Bestellung $bestellung): Collection
    {
        $bestellung->loadMissing('auftragszeilen.angebot');

        return $bestellung->auftragszeilen
            ->map(function (Auftragszeile $zeile): array {
                $brutto = $zeile->betrag_brutto ?? $zeile->angebot?->betrag;

                return [
                    'nummer' => $this->text($zeile->angebotsnummer) ?? $this->text($zeile->angebot?->nummer),
                    'datum' => $zeile->angebotsdatum ?? $zeile->angebot?->angebotsdatum,
                    'brutto' => $brutto === null ? null : (float) $brutto,
                ];
            })
            ->values();
    }

    /**
     * @param  Collection<int, array{nummer: ?string, datum: ?Carbon, brutto: ?float}>  $positionen
     */
    private function bezugszeile(Collection $positionen): string
    {
        $teile = $positionen->map(function (array $position): string {
            $nummer = $position['nummer'] ?? '–';
            $datum = $position['datum']?->format('d.m.Y') ?? '–';

            return 'Nr. '.$nummer.' vom '.$datum;
        })->values();

        if ($teile->count() <= 1) {
            return 'Ihr Angebot mit der '.($teile->first() ?? 'Nr. – vom –');
        }

        $erster = (string) $teile->first();
        $weitere = $teile->slice(1)->implode(', ');

        return 'Ihre Angebote mit der '.$erster.', '.$weitere;
    }

    /**
     * @param  Collection<int, array{nummer: ?string, datum: ?Carbon, brutto: ?float}>  $positionen
     */
    private function bruttoSumme(Collection $positionen): ?float
    {
        if ($positionen->isEmpty() || $positionen->contains(fn (array $position): bool => $position['brutto'] === null)) {
            return null;
        }

        return round((float) $positionen->sum(fn (array $position): float => (float) $position['brutto']), 2);
    }

    private function anredeSatz(?string $anrede, ?string $name): string
    {
        if ($name === null || $anrede === null) {
            return 'Sehr geehrte Damen und Herren,';
        }

        return $anrede === 'herr'
            ? 'Sehr geehrter Herr '.$name.','
            : 'Sehr geehrte Frau '.$name.',';
    }

    private function zuHaenden(?string $anrede, ?string $name): ?string
    {
        if ($name === null) {
            return null;
        }

        return match ($anrede) {
            'herr' => 'Zu Hd. Herrn '.$name,
            'frau' => 'Zu Hd. Frau '.$name,
            default => 'Zu Hd. '.$name,
        };
    }

    private function personenName(mixed $person): string
    {
        if (! $person instanceof User) {
            return '';
        }

        $name = trim(((string) $person->vorname).' '.((string) $person->nachname));

        return $name !== '' ? $name : trim((string) $person->name);
    }

    private function euro(float $betrag): string
    {
        return number_format($betrag, 2, ',', '.').' EURO';
    }

    private function normalisierteAnrede(mixed $anrede): ?string
    {
        $wert = $this->text($anrede);

        return in_array($wert, ['herr', 'frau'], true) ? $wert : null;
    }

    private function text(mixed $value): ?string
    {
        if (! is_string($value) && ! is_numeric($value)) {
            return null;
        }

        $trimmed = trim((string) $value);

        return $trimmed === '' ? null : $trimmed;
    }

    /**
     * @return array{name: ?string, strasse: ?string, hausnummer: ?string, plz: ?string, ort: ?string}
     */
    private function lieferant(Bestellung $bestellung): array
    {
        $bestellung->loadMissing('auftragszeilen.angebot');

        $anschrift = is_array($bestellung->lieferanschrift) ? $bestellung->lieferanschrift : [];
        $name = $this->text($bestellung->lieferantenname);
        $nummer = $this->text($bestellung->lieferantennummer);

        if ($name === null || $nummer === null || empty($anschrift['strasse'])) {
            $angebot = $bestellung->auftragszeilen
                ->map(fn (Auftragszeile $zeile): ?Angebot => $zeile->angebot)
                ->first(fn (?Angebot $angebot): bool => $angebot instanceof Angebot && (filled($angebot->lieferantennummer) || filled($angebot->lieferantenname)));

            if ($angebot instanceof Angebot) {
                $nummer ??= $this->text($angebot->lieferantennummer);
                $name ??= $this->text($angebot->lieferantenname);
            }
        }

        if (empty($anschrift['strasse']) && $nummer !== null) {
            $cache = LieferantCache::query()
                ->where('lieferantennummer', $nummer)
                ->first();

            if ($cache instanceof LieferantCache) {
                $anschrift = [
                    'strasse' => $cache->strasse,
                    'hausnummer' => $cache->hausnummer,
                    'plz' => $cache->plz,
                    'ort' => $cache->ort,
                ];
                $name ??= $this->text($cache->lieferantenname);
            }
        }

        return [
            'name' => $name,
            'strasse' => $anschrift['strasse'] ?? null,
            'hausnummer' => $anschrift['hausnummer'] ?? null,
            'plz' => $anschrift['plz'] ?? null,
            'ort' => $anschrift['ort'] ?? null,
        ];
    }
}
