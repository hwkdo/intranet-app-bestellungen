@php
    $status = $angebot->analyseStatus();
    $hatNummer = filled($angebot->lieferantennummer);
    $hatName = filled($angebot->lieferantenname);

    if ($angebot->typ === 'begruendung') {
        $farbe = 'text-zinc-700 dark:text-zinc-200';
        $icon = null;
        $text = $hatName ? $angebot->lieferantenname : '—';
        $tooltip = $hatName ? $angebot->lieferantenname : null;
    } elseif ($hatNummer) {
        $farbe = 'text-emerald-700 dark:text-emerald-400';
        $icon = 'check';
        $text = $hatName ? $angebot->lieferantenname : $angebot->lieferantennummer;
        $tooltip = 'Lieferant '.$angebot->lieferantennummer;
    } elseif (in_array($status, ['abgeschlossen', 'fehlgeschlagen'], true)) {
        $farbe = 'text-amber-700 dark:text-amber-400';
        $icon = 'exclamation';
        $text = $hatName ? $angebot->lieferantenname : 'Lieferant wählen';
        $tooltip = 'Lieferant wählen';
    } else {
        $farbe = 'text-zinc-500 dark:text-zinc-400';
        $icon = null;
        $text = $hatName ? $angebot->lieferantenname : '—';
        $tooltip = $status === 'laeuft' ? 'Analyse läuft' : 'Kein Lieferant erkannt';
    }
@endphp

@if (filled($tooltip))
    <flux:tooltip :content="$tooltip" class="block min-w-0 max-w-full">
        <span class="inline-flex min-w-0 max-w-full items-center gap-1 {{ $farbe }}">
            @if ($icon === 'check')
                <flux:icon.check-circle variant="mini" class="shrink-0" />
            @elseif ($icon === 'exclamation')
                <flux:icon.exclamation-circle variant="mini" class="shrink-0" />
            @endif
            <span class="truncate">{{ $text }}</span>
        </span>
    </flux:tooltip>
@else
    <span class="inline-flex min-w-0 max-w-full items-center gap-1 {{ $farbe }}">
        <span class="truncate">{{ $text }}</span>
    </span>
@endif
