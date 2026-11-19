<x-mail::message>
# Fehlender Lieferant

**Betreff:** Fehlender Lieferant

**Organisation:** Handwerkskammer Dortmund

| | |
|---|---|
| **Kunde** | {{ $melder->vorname }} {{ $melder->nachname }} |
| **Standort** | {{ $standort }} |
| **Raum** | {{ $raum }} |
| **Telefon** | {{ $telefon }} |
| **Mail** | {{ $melder->email }} |

## Meldung

Fehlender Lieferant im Bestellprozess gemeldet.

**Name:** {{ $lieferantName }}

**Adresse:** {{ filled($adresse) ? $adresse : '—' }}

**IBAN:** {{ filled($iban) ? $iban : '—' }}

**Webseite:** {{ filled($webseite) ? $webseite : '—' }}

Wenn der Lieferant in der BÜ angelegt ist, aktualisiert der folgende Button den Lieferanten-Cache im Intranet. Die Bestellung selbst bleibt unverändert.

<x-mail::button :url="$cacheErneuernUrl">
Lieferant wurde angelegt
</x-mail::button>

---

Dies ist eine automatisch generierte Mail (Intranet). Der Link ist 30 Tage gültig.
</x-mail::message>
