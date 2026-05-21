<?php

declare(strict_types=1);

namespace Hwkdo\IntranetAppBestellungen\Services\Lieferant;

use App\Models\User;
use Hwkdo\IntranetAppBestellungen\Mail\FehlenderLieferantGemeldetMail;
use Hwkdo\IntranetAppBestellungen\Models\IntranetAppBestellungenSettings;
use Illuminate\Support\Facades\Mail;
use InvalidArgumentException;

class FehlenderLieferantMeldungService
{
    public function send(
        User $melder,
        string $name,
        ?string $adresse = null,
        ?string $iban = null,
        ?string $webseite = null,
    ): void {
        $empfaenger = trim(IntranetAppBestellungenSettings::resolvedAppSettings()->fehlenderLieferantEmpfaengerEmail);

        if ($empfaenger === '') {
            throw new InvalidArgumentException(
                'E-Mail-Empfänger für fehlende Lieferanten ist in den App-Einstellungen nicht konfiguriert.',
            );
        }

        Mail::to($empfaenger)->queue(new FehlenderLieferantGemeldetMail(
            melder: $melder,
            lieferantName: $name,
            adresse: filled($adresse) ? $adresse : null,
            iban: filled($iban) ? $iban : null,
            webseite: filled($webseite) ? $webseite : null,
        ));
    }
}
