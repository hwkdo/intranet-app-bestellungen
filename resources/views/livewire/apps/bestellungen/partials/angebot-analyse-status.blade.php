@if ($angebot->typ === 'begruendung')
    <span class="text-zinc-400">—</span>
@else
    @php
        $status = $angebot->analyseStatus();
        $label = match ($status) {
            'laeuft' => 'Analyse läuft',
            'fehlgeschlagen' => 'Analyse fehlgeschlagen. Angaben von Hand eintragen oder die Analyse erneut starten.',
            'abgeschlossen' => 'Analyse abgeschlossen',
            default => 'Keine Analyse',
        };
    @endphp
    <span class="inline-flex items-center justify-center gap-0.5">
        <flux:tooltip :content="$label">
            @if ($status === 'laeuft')
                <flux:icon.clock variant="mini" class="text-amber-600 dark:text-amber-400" />
            @elseif ($status === 'fehlgeschlagen')
                <flux:icon.x-circle variant="mini" class="text-red-600 dark:text-red-400" />
            @elseif ($status === 'abgeschlossen')
                <flux:icon.check-circle variant="mini" class="text-emerald-600 dark:text-emerald-400" />
            @else
                <flux:icon.question-mark-circle variant="mini" class="text-zinc-400" />
            @endif
        </flux:tooltip>
        @if ($status === 'fehlgeschlagen' && $this->kannAngeboteErfassen())
            <flux:button
                size="xs"
                variant="ghost"
                icon="arrow-path"
                tooltip="Analyse erneut starten"
                wire:click.stop="angebotAnalyseErneutStarten({{ $angebot->id }})"
            />
        @endif
    </span>
@endif
