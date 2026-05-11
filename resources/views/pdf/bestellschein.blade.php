{{-- Bestellschein im Corporate Design der Handwerkskammer Dortmund --}}
<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <title>Bestellung {{ $bestellung->nummer }}</title>
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
            max-width: 250px;
            max-height: 50px;
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

        table.data {
            width: 100%;
            border-collapse: collapse;
        }

        table.data th,
        table.data td {
            text-align: left;
            padding: 3pt 4pt;
            vertical-align: top;
        }

        table.data thead th {
            border-bottom: 1pt solid #000;
            font-weight: 700;
        }

        table.data tbody tr.position-row td {
            padding-top: 4pt;
            padding-bottom: 4pt;
            border-bottom: 0.5pt solid #999;
        }

        .right {
            text-align: right;
        }

        .center {
            text-align: center;
        }

        .nowrap {
            white-space: nowrap;
        }

        .summary {
            width: 100%;
            margin-top: 6pt;
        }

        .summary td {
            padding: 3pt 4pt;
        }

        .summary .label {
            text-align: right;
            font-weight: 700;
        }

        .summary .value {
            text-align: right;
            font-weight: 700;
            width: 100pt;
        }

        .rechnungshinweis {
            border: 1pt solid #000;
            padding: 6pt 8pt;
            margin-bottom: 10pt;
            text-align: center;
        }

        .verlauf {
            font-size: 9pt;
        }

        .verlauf div {
            margin-bottom: 2pt;
        }

        .small {
            font-size: 8.5pt;
            color: #444;
        }
    </style>
</head>
<body>

@php
    /** @var \Hwkdo\IntranetAppBestellungen\Models\Bestellung $bestellung */
    $typ = $typ ?? 'intern';
    $istIntern = $typ === 'intern';
    $istExternMitPreisen = $typ === 'extern_mit_preise';
    $zeigtPreise = $istIntern || $istExternMitPreisen;

    $besteller = $bestellung->besteller ?? $bestellung->user;
    $lieferanschriftUser = $bestellung->lieferanschriftUser ?? $besteller;
    $bestelldatum = optional($bestellung->bestellt_datum ?? $bestellung->created_at)->format('d.m.Y');
    $kontierung = is_array($bestellung->kontierung) ? $bestellung->kontierung : [];
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
                @if (! empty($lieferant['nummer']))
                    <span class="small">Lieferantennummer: {{ $lieferant['nummer'] }}</span>
                @endif
            </div>
        </td>
        <td>
            <div class="inner-bordered">
                <strong>Lieferanschrift</strong><br>
                <br>
                @if ($lieferanschriftUser)
                    {{ trim(($lieferanschriftUser->vorname ?? '').' '.($lieferanschriftUser->nachname ?? '')) }}<br>
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
                    {{ trim(($besteller->vorname ?? '').' '.($besteller->nachname ?? '')) }}<br>
                    @if (! empty($besteller->telefon))
                        Tel.: {{ $besteller->telefon }}<br>
                    @endif
                @endif
                Bestelldatum: {{ $bestelldatum }}
            </div>
        </td>
    </tr>
</table>

@if ($istIntern && ! empty($kontierung))
    <div class="section">
        <div class="section-title">Interne Bestellangaben</div>
        <table class="data">
            <thead>
                <tr>
                    <th style="width: 25%">Kostenstelle</th>
                    <th style="width: 25%">Kursnummer</th>
                    <th style="width: 25%">Verw.-Ort</th>
                    <th style="width: 25%" class="right">%-Aufteilung</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($kontierung as $kont)
                    <tr>
                        <td>{{ $kont['kostenstelle'] ?? '' }}</td>
                        <td>{{ $kont['kursnummer'] ?? '' }}</td>
                        <td>{{ $kont['raum'] ?? '' }}</td>
                        <td class="right">{{ number_format((float) ($kont['aufteilung'] ?? 0), 2, ',', '.') }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>
@endif

@if ($istIntern && filled($bestellung->begruendung))
    <div class="section">
        <div class="section-title">Begründung:</div>
        {!! nl2br(e($bestellung->begruendung)) !!}
    </div>
@endif

@if ($typ === 'extern' || $typ === 'extern_mit_preise')
    <div class="rechnungshinweis">
        Bei der Rechnungsstellung bitte immer die folgende Rechnungsanschrift beachten:<br>
        <strong>Ardeystrasse 93, 44139 Dortmund</strong><br>
        Bitte die Rechnung per E-Mail an <strong>rechnungen@hwk-do.de</strong> senden lassen unter Verwendung unserer Bestellnummer <strong>{{ $bestellung->nummer }}</strong>.
    </div>
@endif

<div class="section">
    <div class="section-title">Bestellung:</div>
    <table class="data">
        <thead>
            <tr>
                <th style="width: 8%">Position</th>
                <th style="width: 12%">Menge</th>
                <th style="width: 15%">Art.-Nr.</th>
                <th style="width: {{ $zeigtPreise ? '40%' : '57%' }}">Bezeichnung</th>
                @if ($zeigtPreise)
                    <th style="width: 17%" class="right">Einzelpreis<br><span class="small">(netto vor USt.)</span></th>
                @endif
                <th style="width: 8%">BE-Art</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($bestellung->positionen as $position)
                <tr class="position-row">
                    <td>{{ $position->nr }}</td>
                    <td>{{ rtrim(rtrim(number_format((float) $position->menge, 2, ',', '.'), '0'), ',') }}{{ $position->einheit ? ' '.$position->einheit : '' }}</td>
                    @if ($position->hasPositionPdf() || filled($position->file))
                        <td><em>Siehe Anhang</em></td>
                        <td><em>Siehe Anhang</em></td>
                    @else
                        <td>{{ $position->art_nr }}</td>
                        <td>{{ $position->bezeichnung }}</td>
                    @endif
                    @if ($zeigtPreise)
                        <td class="right nowrap">{{ number_format((float) $position->preis, 2, ',', '.') }} €</td>
                    @endif
                    <td>{{ optional($position->art)->id_intern }}</td>
                </tr>

                @if ($istIntern && filled($position->oberbegriff ?? null))
                    <tr>
                        <td colspan="2"><span class="small">Beschreibung/Oberbegriff zu Pos {{ $position->nr }}:</span></td>
                        <td colspan="{{ $zeigtPreise ? 4 : 3 }}"><span class="small">{{ $position->oberbegriff }}</span></td>
                    </tr>
                @endif

                @if ($istIntern && ! empty($position->anlagen))
                    <tr>
                        <td colspan="2"><span class="small">Anlage zu Position {{ $position->nr }}:</span></td>
                        <td colspan="{{ $zeigtPreise ? 4 : 3 }}">
                            @foreach ($position->anlagen as $key => $value)
                                @if (! is_null($value) && $value !== '')
                                    <span class="small">{{ $key }}: {{ is_scalar($value) ? $value : json_encode($value) }}</span><br>
                                @endif
                            @endforeach
                        </td>
                    </tr>
                @endif
            @endforeach
        </tbody>
    </table>

    @if ($zeigtPreise)
        <table class="summary">
            <tr>
                <td class="label">Gesamtpreis (netto vor USt.):</td>
                <td class="value nowrap">{{ number_format((float) $bestellung->gesamtbetrag, 2, ',', '.') }} €</td>
            </tr>
        </table>
    @endif
</div>

@if ($istIntern && $bestellung->aktionen->isNotEmpty())
    <div class="section verlauf">
        <div class="section-title">Verlauf:</div>
        @foreach ($bestellung->aktionen as $aktion)
            @php
                $aktionsZeit = $aktion->created_at;
                $aktionsLabel = $aktion->typ?->label() ?? $aktion->typ?->value;
                $aktionsUser = $aktion->user;
            @endphp
            <div>
                {{ optional($aktionsZeit)->translatedFormat('d. F Y') }}
                {{ optional($aktionsZeit)->format('H:i:s') }}:
                {{ trim(($aktionsUser->vorname ?? '').' '.($aktionsUser->nachname ?? '')) ?: 'System' }}
                hat Bestellung
                <strong>{{ $aktionsLabel }}</strong>@if (filled($aktion->nachricht)) – {{ $aktion->nachricht }}@endif.
            </div>
        @endforeach
    </div>
@endif

</body>
</html>
