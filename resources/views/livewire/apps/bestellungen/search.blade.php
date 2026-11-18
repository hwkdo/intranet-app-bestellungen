<div>
    <x-intranet-app-bestellungen::bestellungen-layout heading="Bestellungen suchen" subheading="BEN, Betreff, Projekt und Positionsbezeichnungen">
        <div class="space-y-4">
            <div class="glass-card sticky top-3 z-20 p-3" style="--glass-opacity: 0.92">
                <flux:input
                    wire:model.live.debounce.300ms="searchQuery"
                    placeholder="BEN, Betreff, Projekt, Position…"
                    icon="magnifying-glass"
                    class="w-full"
                    aria-label="Bestellungen durchsuchen"
                />
            </div>

            @if (trim($searchQuery) === '' || mb_strlen(trim($searchQuery)) < 2)
                <x-intranet-app-bestellungen::hwk-empty icon="magnifying-glass" title="Suche starten">
                    Geben Sie mindestens 2 Zeichen ein, um die Suche zu starten.
                </x-intranet-app-bestellungen::hwk-empty>
            @elseif ($this->results->isEmpty())
                <x-intranet-app-bestellungen::hwk-empty icon="magnifying-glass" title="Keine Treffer">
                    Keine Bestellungen gefunden. Bitte einen anderen Suchbegriff versuchen.
                </x-intranet-app-bestellungen::hwk-empty>
            @else
                <x-intranet-app-bestellungen::hwk-card title="Treffer" icon="magnifying-glass" :description="$this->results->count().' Bestellungen'">
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
                </x-intranet-app-bestellungen::hwk-card>
            @endif
        </div>
    </x-intranet-app-bestellungen::bestellungen-layout>
</div>
