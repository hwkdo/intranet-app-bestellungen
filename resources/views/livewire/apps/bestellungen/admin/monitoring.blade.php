<div class="space-y-6">
    <flux:card>
        <flux:heading size="lg" class="mb-3">D3-Suche</flux:heading>
        <div class="flex gap-3">
            <flux:input wire:model="d3SearchQuery" placeholder="BEN-Nummer oder Bestellungs-ID" class="flex-1" />
            <flux:button icon="magnifying-glass" wire:click="d3Search">Suchen</flux:button>
        </div>
        @if ($d3SearchResults !== null)
            @if ($d3SearchResults->isEmpty())
                <flux:text class="text-zinc-500 mt-3">Keine Treffer in D3.</flux:text>
            @else
                <ul class="mt-3 space-y-1 text-sm">
                    @foreach ($d3SearchResults as $item)
                        <li>
                            <flux:badge color="sky" size="sm">{{ data_get($item, 'id') ?? data_get($item, 'documentId') }}</flux:badge>
                            {{ data_get($item, 'name') ?? json_encode($item) }}
                        </li>
                    @endforeach
                </ul>
            @endif
        @endif
    </flux:card>

    <flux:card>
        <div class="flex items-center justify-between mb-3">
            <flux:heading size="lg">Bestellungen-Monitoring</flux:heading>
            <flux:input wire:model.live.debounce.300ms="search" placeholder="BEN, Lieferant oder D3-ID…" class="w-72" icon="magnifying-glass" />
        </div>

        <flux:table>
            <flux:table.columns>
                <flux:table.column>BEN</flux:table.column>
                <flux:table.column>Status</flux:table.column>
                <flux:table.column>Lieferant</flux:table.column>
                <flux:table.column>Ersteller</flux:table.column>
                <flux:table.column class="text-right">Betrag</flux:table.column>
                <flux:table.column>D3</flux:table.column>
                <flux:table.column></flux:table.column>
            </flux:table.columns>
            <flux:table.rows>
                @foreach ($bestellungen as $bestellung)
                    <flux:table.row :key="$bestellung->id">
                        <flux:table.cell>
                            <a href="{{ route('apps.bestellungen.detail', $bestellung) }}" wire:navigate class="text-sky-600 hover:underline">
                                {{ $bestellung->nummer }}
                            </a>
                        </flux:table.cell>
                        <flux:table.cell>
                            <flux:badge :color="$bestellung->status?->color()" size="sm">
                                {{ $bestellung->status?->label() }}
                            </flux:badge>
                        </flux:table.cell>
                        <flux:table.cell>{{ $bestellung->lieferantenname }}</flux:table.cell>
                        <flux:table.cell>{{ optional($bestellung->user)->name }}</flux:table.cell>
                        <flux:table.cell class="text-right">
                            {{ number_format((float) $bestellung->gesamtbetrag, 2, ',', '.') }} €
                        </flux:table.cell>
                        <flux:table.cell>
                            @if ($bestellung->d3id)
                                <flux:badge color="emerald" size="sm">{{ \Illuminate\Support\Str::limit($bestellung->d3id, 12) }}</flux:badge>
                            @else
                                <flux:badge color="zinc" size="sm">—</flux:badge>
                            @endif
                        </flux:table.cell>
                        <flux:table.cell>
                            <flux:button size="xs" icon="arrow-path" wire:click="rePush({{ $bestellung->id }})" wire:confirm="D3 Re-Push starten?">
                                Re-Push
                            </flux:button>
                            @if ($bestellung->d3id)
                                <flux:button size="xs" variant="ghost" icon="trash" wire:click="quasiDelete({{ $bestellung->id }})" wire:confirm="D3-Dokument quasi-löschen?">
                                    D3-Löschen
                                </flux:button>
                            @endif
                        </flux:table.cell>
                    </flux:table.row>
                @endforeach
            </flux:table.rows>
        </flux:table>
        <div class="mt-4">{{ $bestellungen->links() }}</div>
    </flux:card>
</div>
