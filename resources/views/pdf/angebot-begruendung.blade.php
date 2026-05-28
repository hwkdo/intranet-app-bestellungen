{{-- Ausnahme-Begründung im Layout des Legacy-Templates ausnahmeangebot_pdf.blade.php --}}
<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <title>Ausnahme-Begründung zur Bestellung {{ $bestellung->nummer }}</title>
    <style>
        @page {
            size: A4;
            margin: 12mm 14mm 14mm 14mm;
        }

        * {
            box-sizing: border-box;
        }

        body {
            font-family: 'Helvetica', 'Arial', sans-serif;
            font-size: 10pt;
            color: #000;
            margin: 0;
            padding: 0;
        }

        h1 {
            font-size: 16pt;
            margin: 0;
            color: #000;
        }

        strong {
            font-weight: 700;
        }

        .header {
            width: 100%;
            margin-bottom: 8pt;
        }

        .header td {
            vertical-align: middle;
            padding: 0;
        }

        .header .logo {
            text-align: right;
        }

        .header .logo img {
            max-width: 300px;
            max-height: none;
        }

        .layout-table {
            width: 100%;
            border-collapse: collapse;
            border: 2pt solid #000;
            margin-bottom: 10pt;
        }

        .layout-table > tbody > tr > td {
            vertical-align: top;
            padding: 0;
            width: 50%;
        }

        .inner-box {
            padding: 6pt 8pt;
        }

        .inner-bordered {
            border: 1pt solid #000;
            padding: 6pt 8pt;
            margin-bottom: 6pt;
        }

        .section {
            border: 1pt solid #000;
            padding: 6pt 8pt;
            margin-bottom: 10pt;
        }

        .section-title {
            font-weight: 700;
            margin-bottom: 4pt;
        }

        .begruendung-text {
            white-space: pre-wrap;
            line-height: 1.5;
        }
    </style>
</head>
<body>

@php
    /** @var \Hwkdo\IntranetAppBestellungen\Models\Bestellung $bestellung */
    $besteller = $bestellung->besteller ?? $bestellung->user;
    $lieferanschriftUser = $bestellung->lieferanschriftUser ?? $besteller;
    $bestelldatum = optional($bestellung->bestellt_at ?? $bestellung->created_at)->format('d.m.Y');
@endphp

<table class="header">
    <tr>
        <td>
            <h1>Bestellung {{ $bestellung->nummer }}</h1>
        </td>
        <td class="logo">
            @if (! empty($logoDataUri))
                <img src="{{ $logoDataUri }}" alt="Handwerkskammer Dortmund">
            @endif
        </td>
    </tr>
</table>

<table class="layout-table">
    <tr>
        <td>
            <div class="inner-box">
                <strong>Lieferantenanschrift</strong><br>
                <br>
                {{ $lieferant['name'] ?? '' }}<br>
                {{ trim(($lieferant['strasse'] ?? '').' '.($lieferant['hausnummer'] ?? '')) }}<br>
                {{ trim(($lieferant['plz'] ?? '').' '.($lieferant['ort'] ?? '')) }}<br>
            </div>
        </td>
        <td>
            <div class="inner-bordered">
                <strong>Lieferanschrift</strong><br>
                <br>
                @if ($lieferanschriftUser)
                    {{ $lieferanschriftUser->name }}<br>
                    @if (! empty($lieferanschriftUser->extension2))
                        {{ $lieferanschriftUser->extension2 }}<br>
                    @endif
                    Handwerkskammer Dortmund<br>
                    {{ $lieferanschriftUser->adresse ?? '' }}@if (! empty($lieferanschriftUser->adresse) && (! empty($lieferanschriftUser->plz) || ! empty($lieferanschriftUser->ort))), @endif{{ trim(($lieferanschriftUser->plz ?? '').' '.($lieferanschriftUser->ort ?? '')) }}<br>
                @endif
            </div>
            <div class="inner-bordered">
                <strong>Besteller:</strong><br>
                @if ($besteller)
                    {{ $besteller->name }}<br>
                    @if (! empty($besteller->telefon))
                        Tel.: {{ $besteller->telefon }}<br>
                    @endif
                @endif
                Bestelldatum: {{ $bestelldatum }}
            </div>
        </td>
    </tr>
</table>

<div class="section">
    <div class="section-title">Ausnahmebegründung</div>
</div>

<div class="section">
    <div class="section-title">Begründung:</div>
    <br>
    <div class="begruendung-text">{{ $begruendung }}</div>
</div>

</body>
</html>
