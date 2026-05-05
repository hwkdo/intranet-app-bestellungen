<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <title>Begründung zur Bestellung {{ $bestellung->nummer }}</title>
    <style>
        body { font-family: 'Helvetica', sans-serif; font-size: 11pt; color: #1f2937; }
        h1 { font-size: 16pt; margin: 0 0 6pt 0; }
        .meta { margin: 12pt 0; }
        .text { white-space: pre-wrap; line-height: 1.5; }
    </style>
</head>
<body>
    <h1>Ausnahme-Begründung</h1>
    <p class="meta">
        Bestellung <strong>{{ $bestellung->nummer }}</strong>
        — Lieferant: {{ $bestellung->lieferantenname }} ({{ $bestellung->lieferantennummer }})<br>
        Erstellt am {{ optional($bestellung->created_at)->format('d.m.Y') }} von {{ optional($bestellung->user)->name }}
    </p>
    <div class="text">{{ $begruendung }}</div>
</body>
</html>
