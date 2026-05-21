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

---

Dies ist eine automatisch generierte Mail (Intranet).
</x-mail::message>
