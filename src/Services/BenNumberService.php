<?php

declare(strict_types=1);

namespace Hwkdo\IntranetAppBestellungen\Services;

use App\Models\User;
use App\Services\IntranetLegacyService;
use Hwkdo\IntranetAppBestellungen\Models\Bestellung;
use Hwkdo\IntranetAppBestellungen\Models\IntranetAppBestellungenSettings;
use Illuminate\Support\Facades\DB;

class BenNumberService
{
    public function __construct(private readonly IntranetLegacyService $legacyService) {}

    /**
     * Erzeugt eine BEN-Nummer im Legacy-Format der HWK Dortmund.
     *
     * Aufbau (rein numerisch, ohne Trennzeichen):
     *   <Praefix><HwkdoNummer><JJ><NNN>
     *
     * - Praefix: 1 Ziffer (Default "3" für Produktion, "1" für lokal/Test) – aus AppSettings
     * - HwkdoNummer: User-Kennung (aus username `hwkdo<n>` bzw. Fallback)
     * - JJ: zweistelliges Jahr des Haushaltsjahres
     * - NNN: dreistellige laufende Nummer pro User und Haushaltsjahr (001, 002, …)
     *
     * Die nächste Sequenznummer wird systemübergreifend ermittelt: Es wird das Maximum
     * aus dem lokalen Zähler (neue DB) und dem höchsten Legacy-Wert verwendet,
     * damit BEN-Nummern zwischen neuem und Legacy-Intranet eindeutig bleiben.
     *
     * Beispiel: "3" + "1234" + "26" + "001" => "312342601"
     */
    public function next(User $user, ?int $haushaltsjahr = null): string
    {
        $jahr = $haushaltsjahr ?? (int) date('Y');
        $jahrKurz = substr((string) $jahr, -2);
        $appSettings = IntranetAppBestellungenSettings::resolvedAppSettings();
        $praefix = $appSettings->benNummerPrefix;
        $hwkdoNummer = $this->extractHwkdoNummer($user);
        $legacyMax = $appSettings->legacyBenPruefungAktiv
            ? $this->legacyService->getMaxSequenceFromLegacy($hwkdoNummer, $jahrKurz)
            : 0;

        return DB::transaction(function () use ($user, $jahr, $jahrKurz, $praefix, $hwkdoNummer, $legacyMax): string {
            $prefixUserJahr = $praefix.$hwkdoNummer.$jahrKurz;

            $localMax = Bestellung::query()
                ->where('user_id', $user->id)
                ->where('haushaltsjahr', $jahr)
                ->where('nummer', 'like', $prefixUserJahr.'%')
                ->lockForUpdate()
                ->selectRaw('MAX(CAST(RIGHT(nummer, 3) AS UNSIGNED)) as max_seq')
                ->value('max_seq') ?? 0;

            $nextSeq = max((int) $localMax, $legacyMax) + 1;

            return $prefixUserJahr.sprintf('%03d', $nextSeq);
        });
    }

    /**
     * Extrahiert die HWK-Dortmund-Nummer aus dem User analog zum Legacy-Verhalten:
     * Beginnt der `username` mit "hwkdo", wird der Rest verwendet (z. B. "hwkdo1234" => "1234").
     * Fallbacks: personalnr, andernfalls die User-ID, damit die BEN immer eindeutig bleibt.
     */
    private function extractHwkdoNummer(User $user): string
    {
        $username = (string) ($user->username ?? '');
        if ($username !== '' && str_starts_with($username, 'hwkdo')) {
            $rest = substr($username, 5);
            if ($rest !== '' && ctype_digit($rest)) {
                return $rest;
            }
        }

        $personalnr = $user->personalnr ?? null;
        if (! empty($personalnr)) {
            return (string) $personalnr;
        }

        return (string) $user->id;
    }
}
