<div>
    <x-intranet-app-bestellungen::bestellungen-layout heading="Projekte" subheading="Bündeln Sie mehrere Bestellungen zu einem Projekt">
        <x-slot:actions>
            <flux:modal.trigger name="projekt-erstellen">
                <button type="button" class="inline-flex items-center gap-2 rounded-full bg-white px-4 py-2 text-sm font-semibold text-[#073070] shadow-md shadow-[#04214e]/20 transition hover:bg-[#d0e3f9]">
                    <flux:icon icon="plus" variant="micro" class="size-4" />
                    Neues Projekt
                </button>
            </flux:modal.trigger>
        </x-slot:actions>

        <div class="space-y-4">
            @if ($projekte->isEmpty())
                <x-intranet-app-bestellungen::hwk-empty icon="folder" title="Keine Projekte">
                    Sie haben noch keine Projekte. Legen Sie ein neues Projekt an, um mehrere Bestellungen zu bündeln.
                </x-intranet-app-bestellungen::hwk-empty>
            @else
                <x-intranet-app-bestellungen::hwk-card title="Ihre Projekte" icon="folder" description="Öffnen Sie ein Projekt, um Bestellungen und Beteiligte zu sehen.">
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
                                        <div class="font-medium text-[#073070] dark:text-white">{{ $projekt->name }}</div>
                                        @if ($projekt->d3_projekt_id)
                                            <div class="mt-0.5 font-mono text-xs text-slate-600 dark:text-white/70">{{ $projekt->d3_projekt_id }}</div>
                                        @endif
                                        @if ($projekt->beschreibung)
                                            <div class="mt-0.5 line-clamp-1 text-xs text-slate-600 dark:text-white/70">{{ $projekt->beschreibung }}</div>
                                        @endif
                                    </flux:table.cell>
                                    <flux:table.cell>{{ optional($projekt->ersteller)->name }}</flux:table.cell>
                                    <flux:table.cell>
                                        @if ($projekt->mitglieder->isNotEmpty())
                                            <flux:badge size="sm" color="zinc">{{ $projekt->mitglieder->count() }}</flux:badge>
                                        @else
                                            <span class="text-sm text-slate-600 dark:text-white/70">–</span>
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
                </x-intranet-app-bestellungen::hwk-card>
            @endif
        </div>

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

                <flux:textarea
                    wire:model="begruendung"
                    label="Begründung"
                    placeholder="Fachliche Begründung für alle Bestellungen in diesem Projekt…"
                    rows="4"
                    required
                />
                <flux:error name="begruendung" />
            </div>

            <div class="mt-6 flex justify-end gap-2">
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
