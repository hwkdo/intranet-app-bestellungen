<div>
    <flux:tab.group>
        <flux:tabs wire:model.live="aktiveTab">
            <flux:tab name="lieferanten" icon="truck">Lieferanten</flux:tab>
            <flux:tab name="kostenstellen" icon="banknotes">Kostenstellen</flux:tab>
        </flux:tabs>

        <flux:tab.panel name="lieferanten">
            <div class="flex items-center gap-3 mb-3">
                <flux:input wire:model.live.debounce.300ms="search" placeholder="Lieferant suchen…" class="flex-1" icon="magnifying-glass" />
                <flux:button icon="arrow-path" wire:click="syncJetzt('lieferanten')" wire:loading.attr="disabled">Jetzt synchronisieren</flux:button>
                <flux:button icon="chart-bar" variant="ghost" wire:click="syncLieferantenNutzungAusLegacy" wire:loading.attr="disabled" wire:target="syncLieferantenNutzungAusLegacy">
                    <span wire:loading.remove wire:target="syncLieferantenNutzungAusLegacy">Legacy-Nutzung synchronisieren</span>
                    <span wire:loading wire:target="syncLieferantenNutzungAusLegacy">Synchronisiere…</span>
                </flux:button>
            </div>
            <p class="text-sm text-zinc-500 mb-3">
                „Legacy-Nutzung synchronisieren" überträgt Bestellungs­häufigkeiten aus dem Legacy-Intranet und verbessert die Reihenfolge der Lieferanten­vorschläge beim Anlegen neuer Bestellungen.
            </p>
            <flux:table>
                <flux:table.columns>
                    <flux:table.column>
                        <button type="button" class="inline-flex items-center gap-1 font-medium text-left hover:text-zinc-900 dark:hover:text-white" wire:click="sortLieferantenBy('nummer')">
                            Nummer
                            @if ($lieferantenSortBy === 'nummer')
                                @if ($lieferantenSortDir === 'asc')
                                    <flux:icon name="chevron-up" class="size-4 shrink-0" />
                                @else
                                    <flux:icon name="chevron-down" class="size-4 shrink-0" />
                                @endif
                            @endif
                        </button>
                    </flux:table.column>
                    <flux:table.column>
                        <button type="button" class="inline-flex items-center gap-1 font-medium text-left hover:text-zinc-900 dark:hover:text-white" wire:click="sortLieferantenBy('name')">
                            Name
                            @if ($lieferantenSortBy === 'name')
                                @if ($lieferantenSortDir === 'asc')
                                    <flux:icon name="chevron-up" class="size-4 shrink-0" />
                                @else
                                    <flux:icon name="chevron-down" class="size-4 shrink-0" />
                                @endif
                            @endif
                        </button>
                    </flux:table.column>
                    <flux:table.column align="end">
                        <button type="button" class="inline-flex items-center gap-1 font-medium hover:text-zinc-900 dark:hover:text-white" wire:click="sortLieferantenBy('legacy')">
                            Legacy
                            @if ($lieferantenSortBy === 'legacy')
                                @if ($lieferantenSortDir === 'asc')
                                    <flux:icon name="chevron-up" class="size-4 shrink-0" />
                                @else
                                    <flux:icon name="chevron-down" class="size-4 shrink-0" />
                                @endif
                            @endif
                        </button>
                    </flux:table.column>
                    <flux:table.column align="end">
                        <button type="button" class="inline-flex items-center gap-1 font-medium hover:text-zinc-900 dark:hover:text-white" wire:click="sortLieferantenBy('v3')">
                            V3
                            @if ($lieferantenSortBy === 'v3')
                                @if ($lieferantenSortDir === 'asc')
                                    <flux:icon name="chevron-up" class="size-4 shrink-0" />
                                @else
                                    <flux:icon name="chevron-down" class="size-4 shrink-0" />
                                @endif
                            @endif
                        </button>
                    </flux:table.column>
                    <flux:table.column align="end">
                        <button type="button" class="inline-flex items-center gap-1 font-medium hover:text-zinc-900 dark:hover:text-white" wire:click="sortLieferantenBy('nutzung')">
                            Gesamt
                            @if ($lieferantenSortBy === 'nutzung')
                                @if ($lieferantenSortDir === 'asc')
                                    <flux:icon name="chevron-up" class="size-4 shrink-0" />
                                @else
                                    <flux:icon name="chevron-down" class="size-4 shrink-0" />
                                @endif
                            @endif
                        </button>
                    </flux:table.column>
                    <flux:table.column>PLZ / Ort</flux:table.column>
                    <flux:table.column>Synchronisiert</flux:table.column>
                </flux:table.columns>
                <flux:table.rows>
                    @foreach ($this->lieferanten as $lieferant)
                        <flux:table.row :key="$lieferant->id">
                            <flux:table.cell>{{ $lieferant->lieferantennummer }}</flux:table.cell>
                            <flux:table.cell>{{ $lieferant->lieferantenname }}</flux:table.cell>
                            <flux:table.cell align="end">{{ (int) ($lieferant->legacy_nutzung ?? 0) }}</flux:table.cell>
                            <flux:table.cell align="end">{{ (int) ($lieferant->v3_nutzung ?? 0) }}</flux:table.cell>
                            <flux:table.cell align="end">
                                <span class="font-medium tabular-nums">{{ (int) ($lieferant->nutzung_gesamt ?? 0) }}</span>
                            </flux:table.cell>
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
