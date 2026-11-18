<div>
    <x-intranet-app-bestellungen::bestellungen-layout heading="Meine Bestellungen" subheading="Alle von Ihnen erstellten Bestellungen">
        <div class="space-y-4">
            <div class="glass-card sticky top-3 z-20 flex flex-wrap items-center gap-3 p-3" style="--glass-opacity: 0.92">
                <flux:input
                    wire:model.live.debounce.300ms="search"
                    placeholder="Bestellnummer, Lieferant oder Betreff…"
                    icon="magnifying-glass"
                    class="min-w-[220px] flex-1"
                />
                <flux:select wire:model.live="statusFilter" placeholder="Status filtern">
                    @foreach ($this->statusOptions() as $value => $label)
                        <flux:select.option value="{{ $value }}">{{ $label }}</flux:select.option>
                    @endforeach
                </flux:select>
                <flux:button variant="primary" icon="plus" :href="route('apps.bestellungen.erstellen')" wire:navigate>
                    Neue Bestellung
                </flux:button>
            </div>

            @if ($bestellungen->isEmpty())
                <x-intranet-app-bestellungen::hwk-empty icon="document-text" title="Keine Bestellungen">
                    Keine Bestellungen gefunden.
                </x-intranet-app-bestellungen::hwk-empty>
            @else
                <x-intranet-app-bestellungen::hwk-card title="Bestellungen" icon="document-text" description="Wählen Sie eine Zeile, um die Bestellung zu öffnen.">
                    <flux:table>
                        <flux:table.columns>
                            <flux:table.column>BEN</flux:table.column>
                            <flux:table.column>Lieferant</flux:table.column>
                            <flux:table.column>Status</flux:table.column>
                            <flux:table.column>Erstellt</flux:table.column>
                            <flux:table.column class="text-right">Betrag</flux:table.column>
                        </flux:table.columns>
                        <flux:table.rows>
                            @foreach ($bestellungen as $bestellung)
                                <flux:table.row
                                    :key="$bestellung->id"
                                    wire:navigate
                                    :href="route('apps.bestellungen.detail', $bestellung)"
                                    class="cursor-pointer"
                                >
                                    <flux:table.cell>{{ $bestellung->nummer }}</flux:table.cell>
                                    <flux:table.cell>{{ $bestellung->lieferantenname }}</flux:table.cell>
                                    <flux:table.cell>
                                        <flux:badge :color="$bestellung->status?->color()" size="sm">
                                            {{ $bestellung->status?->label() }}
                                        </flux:badge>
                                    </flux:table.cell>
                                    <flux:table.cell>{{ optional($bestellung->created_at)->format('d.m.Y') }}</flux:table.cell>
                                    <flux:table.cell class="text-right">
                                        {{ number_format((float) $bestellung->gesamtbetrag, 2, ',', '.') }} €
                                    </flux:table.cell>
                                </flux:table.row>
                            @endforeach
                        </flux:table.rows>
                    </flux:table>
                    <div class="border-t border-[#d0e3f9] px-5 py-4 dark:border-white/10">{{ $bestellungen->links() }}</div>
                </x-intranet-app-bestellungen::hwk-card>
            @endif
        </div>
    </x-intranet-app-bestellungen::bestellungen-layout>
</div>
