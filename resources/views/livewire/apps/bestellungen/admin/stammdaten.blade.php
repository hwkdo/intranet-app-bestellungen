<div>
    <flux:tab.group>
        <flux:tabs wire:model.live="aktiveTab">
            <flux:tab name="lieferanten" icon="truck">Lieferanten</flux:tab>
            <flux:tab name="kostenstellen" icon="banknotes">Kostenstellen</flux:tab>
        </flux:tabs>

        <flux:tab.panel name="lieferanten">
            <div class="flex items-center gap-3 mb-3">
                <flux:input wire:model.live.debounce.300ms="search" placeholder="Lieferant suchen…" class="flex-1" icon="magnifying-glass" />
                <flux:button icon="arrow-path" wire:click="syncJetzt('lieferanten')">Jetzt synchronisieren</flux:button>
            </div>
            <flux:table>
                <flux:table.columns>
                    <flux:table.column>Nummer</flux:table.column>
                    <flux:table.column>Name</flux:table.column>
                    <flux:table.column>PLZ / Ort</flux:table.column>
                    <flux:table.column>Synchronisiert</flux:table.column>
                </flux:table.columns>
                <flux:table.rows>
                    @foreach ($this->lieferanten as $lieferant)
                        <flux:table.row :key="$lieferant->id">
                            <flux:table.cell>{{ $lieferant->lieferantennummer }}</flux:table.cell>
                            <flux:table.cell>{{ $lieferant->lieferantenname }}</flux:table.cell>
                            <flux:table.cell>{{ $lieferant->plz }} {{ $lieferant->ort }}</flux:table.cell>
                            <flux:table.cell>{{ $lieferant->synced_at?->format('d.m.Y H:i') }}</flux:table.cell>
                        </flux:table.row>
                    @endforeach
                </flux:table.rows>
            </flux:table>
        </flux:tab.panel>

        <flux:tab.panel name="kostenstellen">
            <div class="flex items-center gap-3 mb-3">
                <flux:input wire:model.live.debounce.300ms="search" placeholder="Kostenstelle suchen…" class="flex-1" icon="magnifying-glass" />
                <flux:button icon="arrow-path" wire:click="syncJetzt('kostenstellen')">Jetzt synchronisieren</flux:button>
            </div>
            <flux:table>
                <flux:table.columns>
                    <flux:table.column>Kostenstelle</flux:table.column>
                    <flux:table.column>Bezeichnung</flux:table.column>
                    <flux:table.column>Aktiv</flux:table.column>
                    <flux:table.column>Synchronisiert</flux:table.column>
                </flux:table.columns>
                <flux:table.rows>
                    @foreach ($this->kostenstellen as $kst)
                        <flux:table.row :key="$kst->id">
                            <flux:table.cell>{{ $kst->kostenstelle }}</flux:table.cell>
                            <flux:table.cell>{{ $kst->bezeichnung }}</flux:table.cell>
                            <flux:table.cell>
                                @if ($kst->aktiv)
                                    <flux:badge size="sm" color="emerald">aktiv</flux:badge>
                                @else
                                    <flux:badge size="sm" color="zinc">inaktiv</flux:badge>
                                @endif
                            </flux:table.cell>
                            <flux:table.cell>{{ $kst->synced_at?->format('d.m.Y H:i') }}</flux:table.cell>
                        </flux:table.row>
                    @endforeach
                </flux:table.rows>
            </flux:table>
        </flux:tab.panel>
    </flux:tab.group>
</div>
