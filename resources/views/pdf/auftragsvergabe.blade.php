{{-- Auftragsschein mit Angebotsbezug, Corporate Design der Handwerkskammer Dortmund --}}
<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <title>Auftrag {{ $bestellung->nummer }}</title>
    <style>
        @include('intranet-app-bestellungen::pdf.partials.styles')
    </style>
</head>
<body>

@php
    /** @var \Hwkdo\IntranetAppBestellungen\Models\Bestellung $bestellung */
    $auftraggeber = $bestellung->besteller ?? $bestellung->user;
    $auftragsdatum = optional($bestellung->bestellt_at ?? $bestellung->created_at)->format('d.m.Y');
    $zeilen = $bestellung->auftragszeilen;
    $summeNetto = (float) $zeilen->sum(fn ($zeile): float => (float) $zeile->betrag_netto);
    $zeigtBrutto = $zeilen->isNotEmpty() && $zeilen->every(fn ($zeile): bool => $zeile->betrag_brutto !== null);
    $summeBrutto = (float) $zeilen->sum(fn ($zeile): float => (float) $zeile->betrag_brutto);
    $name = trim(((optional($auftraggeber)->vorname ?? '').' '.(optional($auftraggeber)->nachname ?? '')));
@endphp

<table class="header">
    <tr>
        <td>
            <h1>Auftrag {{ $bestellung->nummer }}</h1>
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
                <strong>Auftragnehmer</strong><br>
                <br>
                {{ $lieferant['name'] ?? '' }}<br>
                {{ trim(($lieferant['strasse'] ?? '').' '.($lieferant['hausnummer'] ?? '')) }}<br>
                {{ trim(($lieferant['plz'] ?? '').' '.($lieferant['ort'] ?? '')) }}<br>
                @if (! empty($lieferant['nummer']))
                    <span class="small">Lieferantennummer: {{ $lieferant['nummer'] }}</span>
                @endif
            </div>
        </td>
        <td>
            <div class="inner-bordered">
                <strong>Auftraggeber</strong><br>
                <br>
                Handwerkskammer Dortmund<br>
                @if ($auftraggeber)
                    {{ $name }}<br>
                    @if (! empty($auftraggeber->extension2))
                        {{ $auftraggeber->extension2 }}<br>
                    @endif
                    {{ $auftraggeber->adresse ?? '' }}@if (! empty($auftraggeber->adresse) && (! empty($auftraggeber->plz) || ! empty($auftraggeber->ort))), @endif{{ trim(($auftraggeber->plz ?? '').' '.($auftraggeber->ort ?? '')) }}<br>
                    @if (! empty($auftraggeber->telefon))
                        Tel.: {{ $auftraggeber->telefon }}<br>
                    @endif
                @endif
                Datum: {{ $auftragsdatum }}
            </div>
        </td>
    </tr>
</table>

<div class="rechnungshinweis">
    Bei der Rechnungsstellung bitte immer die folgende Rechnungsanschrift beachten:<br>
    <strong>Ardeystrasse 93, 44139 Dortmund</strong><br>
    Bitte die Rechnung per E-Mail an <strong>rechnungen@hwk-do.de</strong> senden lassen unter Verwendung unserer Bestellnummer <strong>{{ $bestellung->nummer }}</strong>.
</div>

<div class="section">
    <div class="section-title">Auftrag:</div>
    <p>bezugnehmend auf Ihre nachstehenden Angebote erteilen wir Ihnen folgenden Auftrag:</p>
    <table class="data">
        <thead>
            <tr>
                <th style="width: 8%">Nr.</th>
                <th>Bezug</th>
                <th style="width: 18%" class="right">Netto</th>
                <th style="width: 18%" class="right">Brutto</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($zeilen as $zeile)
                <tr class="position-row keep">
                    <td>{{ $zeile->nr }}</td>
                    <td>
                        {{ $zeile->bezugszeile() }}
                        @if (filled($zeile->freitext))
                            <div class="freitext">{!! nl2br(e($zeile->freitext)) !!}</div>
                        @endif
                    </td>
                    <td class="right nowrap">
                        @if ($zeile->betrag_netto !== null)
                            {{ number_format((float) $zeile->betrag_netto, 2, ',', '.') }} €
                        @endif
                    </td>
                    <td class="right nowrap">
                        @if ($zeile->betrag_brutto !== null)
                            {{ number_format((float) $zeile->betrag_brutto, 2, ',', '.') }} €
                        @endif
                    </td>
                </tr>
            @endforeach
        </tbody>
    </table>

    <table class="summary">
        <tr>
            <td class="label">Gesamtpreis (netto):</td>
            <td class="value nowrap">{{ number_format($summeNetto, 2, ',', '.') }} €</td>
        </tr>
        @if ($zeigtBrutto)
            <tr>
                <td class="label">Gesamtpreis (brutto):</td>
                <td class="value nowrap">{{ number_format($summeBrutto, 2, ',', '.') }} €</td>
            </tr>
        @endif
    </table>
</div>

<table class="signature">
    <tr>
        <td>
            <div>Dortmund, den</div>
            <div class="sign-line"></div>
        </td>
        <td>
            <div>Unterschrift Auftraggeber</div>
            <div class="sign-line"></div>
            <strong>{{ $name }}</strong><br>
            @if (filled($auftraggeber?->title))
                {{ $auftraggeber->title }}<br>
            @endif
            Handwerkskammer Dortmund
        </td>
    </tr>
</table>

</body>
</html>
