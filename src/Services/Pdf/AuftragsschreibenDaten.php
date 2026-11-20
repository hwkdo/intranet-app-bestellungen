<?php

declare(strict_types=1);

namespace Hwkdo\IntranetAppBestellungen\Services\Pdf;

use App\Models\User;
use Hwkdo\IntranetAppBestellungen\Enums\AuftragsvorlageVariable;
use Hwkdo\IntranetAppBestellungen\Enums\UnterschriftArt;
use Hwkdo\IntranetAppBestellungen\Models\Angebot;
use Hwkdo\IntranetAppBestellungen\Models\Auftragsvorlage;
use Hwkdo\IntranetAppBestellungen\Models\Auftragszeile;
use Hwkdo\IntranetAppBestellungen\Models\Bestellung;
use Hwkdo\IntranetAppBestellungen\Models\LieferantCache;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Storage;

class AuftragsschreibenDaten
{
    public function __construct(
        private readonly AuftragsvorlageRenderer $renderer,
    ) {}

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
     *     anredeAutomatisch: bool,
     *     brieftext: string,
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
        $bestellung->loadMissing(['auftragszeilen.angebot', 'besteller', 'user', 'unterzeichner']);

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
        $unterzeichner = $bestellung->unterzeichner ?? $person;
        $brutto = $this->bruttoSumme($positionen);
        $mehrere = $positionen->count() > 1;
        $vorlage = $this->vorlageFuer($bestellung);
        $angebotswert = $brutto === null ? '–' : $this->euro($brutto);
        $anredeSatz = $this->anredeSatz($anrede, $ansprechpartner);
        $betreff = $this->text($bestellung->betreff) ?? '';
        $frist = $bestellung->ausfuehrungsfrist?->format('d.m.Y') ?? '–';

        return [
            'empfaengerZeilen' => $this->empfaengerZeilen($lieferant, $anrede, $ansprechpartner),
            'auftragnehmerEmail' => $bestellung->auftragnehmer_email === null
                ? $kontakt['email']
                : $this->text($bestellung->auftragnehmer_email),
            'datum' => ($bestellung->bestellt_at ?? now())->locale('de')->translatedFormat('j. F Y'),
            'bezug' => $this->bezugszeile($positionen),
            'betreff' => $betreff,
            'anrede' => $anredeSatz,
            'anredeAutomatisch' => $vorlage?->anrede_automatisch ?? true,
            'brieftext' => $this->brieftext($bestellung, $vorlage, $anredeSatz, $betreff, $angebotswert, $mehrere, $frist),
            'angebotswert' => $angebotswert,
            'mehrereAngebote' => $mehrere,
            'frist' => $frist,
            'zeichen' => $person instanceof User ? mb_strtoupper($person->initials()) : '',
            'ansprechpartnerName' => $this->personenName($person),
            'telefon' => $person instanceof User ? $this->text($person->telefon) : null,
            'telefax' => $person instanceof User ? $this->text($person->fax) : null,
            'email' => $person instanceof User ? $this->text($person->email) : null,
            'unterzeichnerName' => $this->personenName($unterzeichner),
            'funktion' => $unterzeichner instanceof User ? $this->text($unterzeichner->title) : null,
            'unterschriftDataUri' => $this->unterschriftDataUri($bestellung),
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
        $vorlage = $this->vorlageFuer($bestellung);

        if ($vorlage?->verwendet(AuftragsvorlageVariable::Ausfuehrungsfrist) && $bestellung->ausfuehrungsfrist === null) {
            $fehlend[] = AuftragsvorlageVariable::Ausfuehrungsfrist->pflichtLabel();
        }

        $vorlageWerte = is_array($bestellung->vorlage_werte) ? $bestellung->vorlage_werte : [];

        if ($vorlage?->verwendet(AuftragsvorlageVariable::Freitext) && ! filled($vorlageWerte['freitext'] ?? null)) {
            $fehlend[] = AuftragsvorlageVariable::Freitext->pflichtLabel();
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

        return array_values(array_filter($fehlend, static fn (?string $eintrag): bool => filled($eintrag)));
    }

    public function vorlageFuer(Bestellung $bestellung): ?Auftragsvorlage
    {
        $bestellung->loadMissing('auftragsvorlage');

        if ($bestellung->auftragsvorlage instanceof Auftragsvorlage) {
            return $bestellung->auftragsvorlage;
        }

        if (! $bestellung->nutztAuftragsvorlage()) {
            return null;
        }

        return Auftragsvorlage::query()->where('schluessel', 'hausverwaltung')->first();
    }

    public function unterschriftDataUri(Bestellung $bestellung): ?string
    {
        if ($bestellung->unterschrift_art !== UnterschriftArt::Digital || ! filled($bestellung->unterschrift_bild_pfad)) {
            return null;
        }

        if (! Storage::disk('local')->exists($bestellung->unterschrift_bild_pfad)) {
            return null;
        }

        $bytes = Storage::disk('local')->get($bestellung->unterschrift_bild_pfad);
        if (! is_string($bytes) || $bytes === '') {
            return null;
        }

        return 'data:image/png;base64,'.base64_encode($bytes);
    }

    public function brieftextEinfrieren(Bestellung $bestellung): void
    {
        if (! $bestellung->nutztAuftragsvorlage()) {
            return;
        }

        $vorlage = $this->vorlageFuer($bestellung);
        if (! $vorlage instanceof Auftragsvorlage) {
            return;
        }

        $daten = $this->fuer($bestellung);
        $html = $this->renderer->html($vorlage, $this->platzhalterWerte(
            $daten['anrede'],
            $daten['betreff'],
            $daten['angebotswert'],
            $daten['mehrereAngebote'],
            $daten['frist'],
            $this->freitext($bestellung),
        ));

        $bestellung->forceFill(['auftragsschreiben_inhalt' => $html])->save();
    }

    private function brieftext(
        Bestellung $bestellung,
        ?Auftragsvorlage $vorlage,
        string $anredeSatz,
        string $betreff,
        string $angebotswert,
        bool $mehrere,
        string $frist,
    ): string {
        if (filled($bestellung->auftragsschreiben_inhalt)) {
            return (string) $bestellung->auftragsschreiben_inhalt;
        }

        if (! $vorlage instanceof Auftragsvorlage) {
            return '';
        }

        return $this->renderer->html($vorlage, $this->platzhalterWerte(
            $anredeSatz,
            $betreff,
            $angebotswert,
            $mehrere,
            $frist,
            $this->freitext($bestellung),
        ));
    }

    private function freitext(Bestellung $bestellung): string
    {
        $werte = is_array($bestellung->vorlage_werte) ? $bestellung->vorlage_werte : [];

        return $this->text($werte['freitext'] ?? null) ?? '';
    }

    /**
     * @return array<string, string>
     */
    private function platzhalterWerte(
        string $anrede,
        string $betreff,
        string $angebotswert,
        bool $mehrere,
        string $frist,
        string $freitext,
    ): array {
        return [
            'anrede' => $anrede,
            'betreff' => $betreff !== '' ? $betreff : '–',
            'angebotswert' => $angebotswert,
            'angebot_bezug' => $mehrere ? 'Ihren oben genannten Angeboten' : 'Ihrem oben genannten Angebot',
            'frist' => $frist,
            'freitext' => $freitext,
        ];
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
