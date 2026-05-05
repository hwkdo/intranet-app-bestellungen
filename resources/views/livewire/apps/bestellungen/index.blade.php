<div>
    <x-intranet-app-bestellungen::bestellungen-layout heading="Bestellungen" subheading="Ihre Übersicht">
        <div class="grid gap-4 md:grid-cols-4 mb-6">
            <flux:card>
                <flux:heading size="sm" class="text-zinc-500">Meine Bestellungen</flux:heading>
                <flux:heading size="xl" class="mt-1">{{ $this->statistik['gesamt'] }}</flux:heading>
            </flux:card>
            <flux:card>
                <flux:heading size="sm" class="text-zinc-500">In Freigabe</flux:heading>
                <flux:heading size="xl" class="mt-1 text-amber-600">{{ $this->statistik['offen'] }}</flux:heading>
            </flux:card>
            <flux:card>
                <flux:heading size="sm" class="text-zinc-500">Bestellt</flux:heading>
                <flux:heading size="xl" class="mt-1 text-emerald-600">{{ $this->statistik['bestellt'] }}</flux:heading>
            </flux:card>
            <flux:card>
                <flux:heading size="sm" class="text-zinc-500">Abgelehnt</flux:heading>
                <flux:heading size="xl" class="mt-1 text-red-600">{{ $this->statistik['abgelehnt'] }}</flux:heading>
            </flux:card>
        </div>

        <div class="grid gap-6 lg:grid-cols-2">
            <flux:card>
                <div class="flex items-center justify-between mb-4">
                    <flux:heading size="lg">Zur Freigabe für mich</flux:heading>
                    <flux:button variant="ghost" size="sm" :href="route('apps.bestellungen.freigaben')" wire:navigate>
                        Alle anzeigen
                    </flux:button>
                </div>

                @if ($this->offeneFreigaben->isEmpty())
                    <flux:text class="text-zinc-500">Aktuell sind keine Bestellungen für Sie zur Freigabe offen.</flux:text>
                @else
                    <flux:table>
                        <flux:table.columns>
                            <flux:table.column>BEN</flux:table.column>
                            <flux:table.column>Besteller</flux:table.column>
                            <flux:table.column class="text-right">Betrag</flux:table.column>
                            <flux:table.column></flux:table.column>
                        </flux:table.columns>
                        <flux:table.rows>
                            @foreach ($this->offeneFreigaben as $bestellung)
                                <flux:table.row :key="$bestellung->id">
                                    <flux:table.cell>{{ $bestellung->nummer }}</flux:table.cell>
                                    <flux:table.cell>{{ optional($bestellung->user)->name }}</flux:table.cell>
                                    <flux:table.cell class="text-right">
                                        {{ number_format((float) $bestellung->gesamtbetrag, 2, ',', '.') }} €
                                    </flux:table.cell>
                                    <flux:table.cell>
                                        <flux:button
                                            size="xs"
                                            variant="primary"
                                            :href="route('apps.bestellungen.detail', ['bestellung' => $bestellung, 'aktion' => 'freigeben'])"
                                            wire:navigate
                                        >
                                            Prüfen
                                        </flux:button>
                                    </flux:table.cell>
                                </flux:table.row>
                            @endforeach
                        </flux:table.rows>
                    </flux:table>
                @endif
            </flux:card>

            <flux:card>
                <div class="flex items-center justify-between mb-4">
                    <flux:heading size="lg">Meine letzten Bestellungen</flux:heading>
                    <flux:button variant="ghost" size="sm" :href="route('apps.bestellungen.meine')" wire:navigate>
                        Alle anzeigen
                    </flux:button>
                </div>

                @if ($this->meineBestellungen->isEmpty())
                    <flux:text class="text-zinc-500 mb-4">Sie haben noch keine Bestellung erstellt.</flux:text>
                    <flux:button variant="primary" :href="route('apps.bestellungen.erstellen')" wire:navigate>
                        Erste Bestellung anlegen
                    </flux:button>
                @else
                    <flux:table>
                        <flux:table.columns>
                            <flux:table.column>BEN</flux:table.column>
                            <flux:table.column>Lieferant</flux:table.column>
                            <flux:table.column>Status</flux:table.column>
                            <flux:table.column class="text-right">Betrag</flux:table.column>
                        </flux:table.columns>
                        <flux:table.rows>
                            @foreach ($this->meineBestellungen as $bestellung)
                                <flux:table.row
                                    :key="$bestellung->id"
                                    class="cursor-pointer"
                                    wire:navigate
                                    :href="route('apps.bestellungen.detail', $bestellung)"
                                >
                                    <flux:table.cell>{{ $bestellung->nummer }}</flux:table.cell>
                                    <flux:table.cell>{{ $bestellung->lieferantenname }}</flux:table.cell>
                                    <flux:table.cell>
                                        <flux:badge :color="$bestellung->status?->color()" size="sm">
                                            {{ $bestellung->status?->label() }}
                                        </flux:badge>
                                    </flux:table.cell>
                                    <flux:table.cell class="text-right">
                                        {{ number_format((float) $bestellung->gesamtbetrag, 2, ',', '.') }} €
                                    </flux:table.cell>
                                </flux:table.row>
                            @endforeach
                        </flux:table.rows>
                    </flux:table>
                @endif
            </flux:card>
        </div>
    </x-intranet-app-bestellungen::bestellungen-layout>
</div>
