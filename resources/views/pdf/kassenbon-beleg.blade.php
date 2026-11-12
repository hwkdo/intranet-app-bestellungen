<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <title>Kassenbon {{ $nummer }}</title>
    <style>
        body { font-family: sans-serif; margin: 24px; }
        h1 { font-size: 18px; }
        img { max-width: 100%; height: auto; }
    </style>
</head>
<body>
    <h1>Bestellung {{ $nummer }}</h1>
    <p>Nachträgliche Bestellung – Beleg: Kassenbon {{ $belegnummer }}</p>
    @if (! empty($bildDataUri))
        <img src="{{ $bildDataUri }}" alt="Kassenbon">
    @endif
</body>
</html>
