<div>
    <x-intranet-app-bestellungen::bestellungen-layout heading="Bestellungen suchen" subheading="BEN, Betreff, Projekt und Positionsbezeichnungen">
        <div class="space-y-6">
            <div class="space-y-2">
                <flux:heading size="lg">Bestellungen durchsuchen</flux:heading>
                <flux:text class="text-zinc-600 dark:text-zinc-400">
                    Suche nach Bestellnummer (BEN), Betreff, Projektname oder Positionsbezeichnung.
                </flux:text>
            </div>

            <flux:input
                wire:model.live.debounce.300ms="searchQuery"
                placeholder="BEN, Betreff, Projekt, Position…"
                icon="magnifying-glass"
                class="w-full"
            />

            @if (trim($searchQuery) !== '' && mb_strlen(trim($searchQuery)) < 2)
                <flux:callout variant="info">
                    Geben Sie mindestens 2 Zeichen ein, um die Suche zu starten.
                </flux:callout>
            @endif

            @if (trim($searchQuery) !== '' && mb_strlen(trim($searchQuery)) >= 2)
                <div class="flex items-center gap-2">
                    <flux:heading size="md">Treffer</flux:heading>
                    <flux:badge variant="outline">{{ $this->results->count() }}</flux:badge>
                </div>

                @if ($this->results->isEmpty())
                    <flux:callout variant="info">
                        Keine Bestellungen gefunden. Bitte einen anderen Suchbegriff versuchen.
                    </flux:callout>
                @else
                    <flux:table>
                        <flux:table.columns>
                            <flux:table.column>BEN</flux:table.column>
                            <flux:table.column>Betreff</flux:table.column>
                            <flux:table.column>Projekt</flux:table.column>
                            <flux:table.column>Status</flux:table.column>
                            <flux:table.column></flux:table.column>
                        </flux:table.columns>
                        <flux:table.rows>
                            @foreach ($this->results as $bestellung)
                                <flux:table.row :key="$bestellung->id">
                                    <flux:table.cell>{{ $bestellung->nummer }}</flux:table.cell>
                                    <flux:table.cell>{{ $bestellung->betreff ?: '—' }}</flux:table.cell>
                                    <flux:table.cell>{{ $bestellung->projekt?->name ?? '—' }}</flux:table.cell>
                                    <flux:table.cell>
                                        <flux:badge :color="$bestellung->status?->color()" size="sm">
                                            {{ $bestellung->status?->label() }}
                                        </flux:badge>
                                    </flux:table.cell>
                                    <flux:table.cell>
                                        <flux:button
                                            size="sm"
                                            variant="ghost"
                                            icon="eye"
                                            :href="route('apps.bestellungen.detail', $bestellung)"
                                            wire:navigate
                                        >
                                            Öffnen
                                        </flux:button>
                                    </flux:table.cell>
                                </flux:table.row>
                            @endforeach
                        </flux:table.rows>
                    </flux:table>
                @endif
            @elseif (trim($searchQuery) === '')
                <flux:callout variant="info">
                    Geben Sie mindestens 2 Zeichen ein, um die Suche zu starten.
                </flux:callout>
            @endif
        </div>
    </x-intranet-app-bestellungen::bestellungen-layout>
</div>
