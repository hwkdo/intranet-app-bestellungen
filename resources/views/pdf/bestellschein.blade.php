{{-- Bestellschein im Corporate Design der Handwerkskammer Dortmund --}}
<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <title>Bestellung {{ $bestellung->nummer }}</title>
    <style>
        @include('intranet-app-bestellungen::pdf.partials.styles')
    </style>
</head>
<body>

@php
    /** @var \Hwkdo\IntranetAppBestellungen\Models\Bestellung $bestellung */
    $typ = $typ ?? 'intern';
    $istIntern = $typ === 'intern';
    $istExternMitPreisen = $typ === 'extern_mit_preise';
    $istNachtraeglich = $typ === 'nachtraeglich';
    $zeigtPreise = $istIntern || $istExternMitPreisen || $istNachtraeglich;

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

@if ($istNachtraeglich)
    <div class="section">
        <div class="section-title">Hinweis:</div>
        Nachträgliche Bestellung – Beleg: Kassenbon
        @if ($bestellung->kassenbon)
            Nr. {{ $bestellung->kassenbon->belegnummer ?: '0' }}
            @if ($bestellung->kassenbon->belegdatum)
                vom {{ $bestellung->kassenbon->belegdatum->format('d.m.Y') }}
            @endif
            @if ($bestellung->kassenbon->bruttobetrag !== null)
                · Brutto {{ number_format((float) $bestellung->kassenbon->bruttobetrag, 2, ',', '.') }} €
            @endif
        @endif
    </div>
@endif

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

@if (($istIntern || $istNachtraeglich) && filled($bestellung->begruendung))
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
                    <th style="width: 17%" class="right">Einzelpreis<br><span class="small">{{ $istNachtraeglich ? '(brutto)' : '(netto vor USt.)' }}</span></th>
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
