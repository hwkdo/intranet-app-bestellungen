<?php

declare(strict_types=1);

namespace Hwkdo\IntranetAppBestellungen\Services\D3;

use App\Models\User;
use Hwkdo\IntranetAppBestellungen\Enums\AktionTyp;
use Hwkdo\IntranetAppBestellungen\Models\Bestellung;

class D3BenutzerResolver
{
    /**
     * D3-Pflichtfeld „Benutzer“ (propertyFieldId 79): LDAP-Displayname-Format, mehrere Beteiligte.
     *
     * @return array<int, string>
     */
    public function resolve(Bestellung $bestellung): array
    {
        $bestellung->loadMissing(['user', 'besteller', 'freigeber', 'aktionen.user']);

        $werte = collect([
            $this->wert($bestellung->user),
            $this->wert($bestellung->besteller),
            $this->wert($bestellung->freigeber),
        ]);

        if (! $bestellung->freigeber_id) {
            $freigegebenDurch = $bestellung->aktionen
                ->firstWhere('typ', AktionTyp::Freigegeben->value)?->user;

            $werte->push($this->wert($freigegebenDurch));
        }

        return $werte
            ->map(fn ($wert) => is_string($wert) ? trim($wert) : null)
            ->filter()
            ->unique()
            ->values()
            ->all();
    }

    /**
     * Einzelwert im von D3 erwarteten Format (z. B. „Nachname, Vorname“), nicht „Vorname Nachname“.
     */
    public function wert(?User $user): ?string
    {
        if (! $user) {
            return null;
        }

        $displayName = trim((string) ($user->ldap_displayname ?? ''));

        return $displayName !== '' ? $displayName : null;
    }
}
