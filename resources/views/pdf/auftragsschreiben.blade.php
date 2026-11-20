{{-- Auftragsschreiben nach der HWK-Briefvorlage, Werte ersetzen die farbigen Platzhalter --}}
<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <title>Auftrag {{ $bestellung->nummer }}</title>
    <style>
        @page {
            size: A4;
            margin: 14mm 5mm 16mm 22mm;
        }

        * {
            box-sizing: border-box;
        }

        body {
            font-family: Arial, 'Liberation Sans', Helvetica, sans-serif;
            font-size: 10.5pt;
            line-height: 1.35;
            color: #000;
            margin: 0;
            padding: 0;
        }

        .kopf {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 8mm;
        }

        .kopf td {
            vertical-align: top;
            padding: 0;
        }

        .logo {
            text-align: right;
        }

        .logo img {
            width: 52mm;
            height: auto;
        }

        .absender {
            font-size: 8pt;
            line-height: 1.2;
            text-decoration: underline;
            margin: 0 0 2mm;
        }

        .anschrift {
            min-height: 24mm;
            font-size: 11pt;
            line-height: 1.35;
            margin: 0;
        }

        .empfaenger-email {
            margin: 8mm 0 0;
        }

        .brief {
            width: 100%;
            border-collapse: collapse;
            margin-top: 10mm;
        }

        .brief > tbody > tr > td {
            vertical-align: top;
            padding: 0;
        }

        .haupt {
            padding-right: 16mm;
        }

        .info {
            width: 1%;
            white-space: nowrap;
            font-size: 9pt;
            line-height: 1.35;
        }

        .auftrag-titel {
            font-size: 12pt;
            font-weight: 700;
            line-height: 1.2;
            margin: 0 0 12pt;
        }

        .datum {
            text-align: right;
            font-size: 10.5pt;
            line-height: 1.2;
            margin: 0 0 12pt;
        }

        .bezug {
            width: 100%;
            border-collapse: collapse;
            margin: 0 0 12pt;
        }

        .bezug td {
            vertical-align: top;
            padding: 0 0 2pt;
        }

        .bezug-label {
            width: 18mm;
            padding-right: 2mm;
        }

        .anrede {
            margin: 4pt 0 10pt;
        }

        p {
            margin: 0 0 10pt;
        }

        .abschluss {
            margin-top: 14pt;
        }

        .signatur {
            margin: 40pt 0 22pt;
        }

        .anlage {
            margin-top: 8pt;
        }

        .info-block div {
            margin: 0;
        }

        .info-luecke {
            height: 8pt;
        }
    </style>
</head>
<body>

<table class="kopf">
    <tr>
        <td></td>
        <td class="logo">
            @if (! empty($logoDataUri))
                <img src="{{ $logoDataUri }}" alt="Handwerkskammer Dortmund">
            @endif
        </td>
    </tr>
</table>

<div class="absender">Handwerkskammer Dortmund · Postfach 10 50 23 · 44047 Dortmund</div>

<div class="anschrift">
    @foreach ($schreiben['empfaengerZeilen'] as $zeile)
        {{ $zeile }}<br>
    @endforeach
</div>

@if (filled($schreiben['auftragnehmerEmail']))
    <p class="empfaenger-email">E-Mail: {{ $schreiben['auftragnehmerEmail'] }}</p>
@endif

<table class="brief">
    <tr>
        <td class="haupt">
            <div class="auftrag-titel">Auftrag</div>

            <table class="bezug">
                <tr>
                    <td class="bezug-label">Bezug:</td>
                    <td><strong>{{ $schreiben['bezug'] }}</strong></td>
                </tr>
                <tr>
                    <td class="bezug-label">hier:</td>
                    <td><strong>{{ $schreiben['betreff'] !== '' ? $schreiben['betreff'] : '–' }}</strong></td>
                </tr>
            </table>

            <p class="anrede">{{ $schreiben['anrede'] }}</p>

            <p>
                Sie erhalten hiermit den Auftrag zu <strong>{{ $schreiben['betreff'] !== '' ? $schreiben['betreff'] : '–' }}</strong>
                gemäß {{ $schreiben['mehrereAngebote'] ? 'Ihren oben genannten Angeboten' : 'Ihrem oben genannten Angebot' }}
                (Angebotswert gesamt: <strong>{{ $schreiben['angebotswert'] }}</strong> inkl. Umsatzsteuer).
            </p>

            <p>
                Die Ausführungsfrist für diesen Auftrag endet am <strong>{{ $schreiben['frist'] }}</strong>. Die
                Nichteinhaltung der Ausführungsfrist ohne unsere vorherige Zustimmung
                entbindet uns von der Abnahmeverpflichtung und bedeutet die Stornierung des
                Auftrages.
            </p>

            <p>
                Lieferschein und Rechnung müssen unbedingt die Verwendungsstelle bzw. den
                Einrichtungsbereich ausweisen.
            </p>

            <p class="abschluss">Handwerkskammer Dortmund</p>

            <p>Mit freundlichen Grüßen</p>

            <div class="signatur">
                @if ($schreiben['unterzeichnerName'] !== '')
                    <div>{{ $schreiben['unterzeichnerName'] }}</div>
                @endif
                @if (filled($schreiben['funktion']))
                    <div>{{ $schreiben['funktion'] }}</div>
                @endif
            </div>

            <p class="anlage">Anlage: <strong>{{ $schreiben['anlage'] }}</strong></p>
        </td>
        <td class="info">
            <div class="datum">{{ $schreiben['datum'] }}</div>
            <div class="info-block">
                <div>Ihr Zeichen:</div>
                <div>Unser Zeichen: {{ $schreiben['zeichen'] }}</div>
                <div class="info-luecke"></div>
                <div>Ansprechpartner:</div>
                @if ($schreiben['ansprechpartnerName'] !== '')
                    <div>{{ $schreiben['ansprechpartnerName'] }}</div>
                @endif
                <div>Telefon{{ filled($schreiben['telefon']) ? '  '.$schreiben['telefon'] : '' }}</div>
                <div>Telefax{{ filled($schreiben['telefax']) ? '  '.$schreiben['telefax'] : '' }}</div>
                @if (filled($schreiben['email']))
                    <div>{{ $schreiben['email'] }}</div>
                @endif
                <div class="info-luecke"></div>
                <div>Handwerkskammer Dortmund</div>
                <div>Ardeystraße 93</div>
                <div>44139 Dortmund</div>
                <div class="info-luecke"></div>
                <div>www.hwk-do.de</div>
            </div>
        </td>
    </tr>
</table>

</body>
</html>
