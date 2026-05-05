<div>
    <x-intranet-app-bestellungen::bestellungen-layout heading="Meine Bestellungen" subheading="Alle von Ihnen erstellten Bestellungen">
        <div class="flex flex-wrap items-center gap-3 mb-4">
            <flux:input
                wire:model.live.debounce.300ms="search"
                placeholder="BEN-Nr., Lieferant oder Betreff…"
                icon="magnifying-glass"
                class="flex-1 min-w-[220px]"
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

        <flux:card>
            @if ($bestellungen->isEmpty())
                <flux:text class="text-zinc-500">Keine Bestellungen gefunden.</flux:text>
            @else
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
                <div class="mt-4">{{ $bestellungen->links() }}</div>
            @endif
        </flux:card>
    </x-intranet-app-bestellungen::bestellungen-layout>
</div>
