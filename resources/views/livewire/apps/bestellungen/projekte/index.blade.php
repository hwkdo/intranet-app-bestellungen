<div>
    <x-intranet-app-bestellungen::bestellungen-layout heading="Projekte" subheading="Bündeln Sie mehrere Bestellungen zu einem Projekt">
        <div class="flex justify-end mb-4">
            <flux:modal.trigger name="projekt-erstellen">
                <flux:button variant="primary" icon="plus">
                    Neues Projekt
                </flux:button>
            </flux:modal.trigger>
        </div>

        <flux:card>
            @if ($projekte->isEmpty())
                <flux:text class="text-zinc-500">Sie haben noch keine Projekte. Legen Sie ein neues Projekt an, um mehrere Bestellungen zu bündeln.</flux:text>
            @else
                <flux:table>
                    <flux:table.columns>
                        <flux:table.column>Projekt</flux:table.column>
                        <flux:table.column>Ersteller</flux:table.column>
                        <flux:table.column>Mitglieder</flux:table.column>
                        <flux:table.column class="text-right">Bestellungen</flux:table.column>
                        <flux:table.column class="text-right">Gesamtkosten</flux:table.column>
                    </flux:table.columns>
                    <flux:table.rows>
                        @foreach ($projekte as $projekt)
                            <flux:table.row
                                :key="$projekt->id"
                                wire:navigate
                                :href="route('apps.bestellungen.projekte.detail', $projekt)"
                                class="cursor-pointer"
                            >
                                <flux:table.cell>
                                    <div class="font-medium">{{ $projekt->name }}</div>
                                    @if ($projekt->beschreibung)
                                        <div class="text-xs text-zinc-500 mt-0.5 line-clamp-1">{{ $projekt->beschreibung }}</div>
                                    @endif
                                </flux:table.cell>
                                <flux:table.cell>{{ optional($projekt->ersteller)->name }}</flux:table.cell>
                                <flux:table.cell>
                                    @if ($projekt->mitglieder->isNotEmpty())
                                        <flux:badge size="sm" color="zinc">{{ $projekt->mitglieder->count() }}</flux:badge>
                                    @else
                                        <span class="text-zinc-400 text-sm">–</span>
                                    @endif
                                </flux:table.cell>
                                <flux:table.cell class="text-right">{{ $projekt->bestellungen_count }}</flux:table.cell>
                                <flux:table.cell class="text-right">
                                    {{ number_format((float) ($projekt->bestellungen_sum_gesamtbetrag ?? 0), 2, ',', '.') }} €
                                </flux:table.cell>
                            </flux:table.row>
                        @endforeach
                    </flux:table.rows>
                </flux:table>
            @endif
        </flux:card>

        <flux:modal name="projekt-erstellen" class="max-w-lg">
            <flux:heading size="lg">Neues Projekt anlegen</flux:heading>
            <flux:text class="mt-1 mb-4">Geben Sie dem Projekt einen aussagekräftigen Namen, um es von anderen Projekten zu unterscheiden.</flux:text>

            <div class="space-y-4">
                <flux:input
                    wire:model="name"
                    label="Projektname"
                    placeholder="z. B. EDV-Ausstattung Schulungsraum 2026"
                    required
                />
                <flux:error name="name" />

                <flux:textarea
                    wire:model="beschreibung"
                    label="Beschreibung (optional)"
                    placeholder="Kurze Beschreibung des Projekts…"
                    rows="3"
                />
                <flux:error name="beschreibung" />
            </div>

            <div class="flex gap-2 justify-end mt-6">
                <flux:modal.close>
                    <flux:button variant="ghost">Abbrechen</flux:button>
                </flux:modal.close>
                <flux:button variant="primary" wire:click="erstellen" wire:loading.attr="disabled">
                    Projekt anlegen
                </flux:button>
            </div>
        </flux:modal>
    </x-intranet-app-bestellungen::bestellungen-layout>
</div>
