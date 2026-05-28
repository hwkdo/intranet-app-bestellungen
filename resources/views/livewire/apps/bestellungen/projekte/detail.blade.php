<div>
    <x-intranet-app-bestellungen::bestellungen-layout
        :heading="$projekt->name"
        :subheading="$projekt->beschreibung ?? 'Projektübersicht'"
    >
        {{-- Header-Aktionen --}}
        <div class="flex flex-wrap items-center justify-between gap-3 mb-4">
            <flux:button variant="ghost" icon="arrow-left" :href="route('apps.bestellungen.projekte.index')" wire:navigate>
                Alle Projekte
            </flux:button>

            <flux:button variant="primary" icon="plus" :href="route('apps.bestellungen.erstellen', ['projekt' => $projekt->id])" wire:navigate>
                Bestellung erstellen
            </flux:button>
        </div>

        {{-- Kennzahlen --}}
        <div class="grid grid-cols-2 md:grid-cols-4 gap-4 mb-6">
            <flux:card class="text-center">
                <flux:heading size="xl">{{ $this->bestellungen->count() }}</flux:heading>
                <flux:text class="text-zinc-500 text-sm mt-1">Bestellungen</flux:text>
            </flux:card>
            <flux:card class="text-center">
                <flux:heading size="xl">{{ number_format($this->gesamtkosten, 2, ',', '.') }} €</flux:heading>
                <flux:text class="text-zinc-500 text-sm mt-1">Gesamtkosten</flux:text>
            </flux:card>
            <flux:card class="text-center">
                <flux:heading size="xl">{{ $projekt->mitglieder->count() + 1 }}</flux:heading>
                <flux:text class="text-zinc-500 text-sm mt-1">Beteiligte</flux:text>
            </flux:card>
            <flux:card class="text-center">
                <flux:heading size="lg" class="font-mono break-all">{{ $projekt->d3_projekt_id }}</flux:heading>
                <flux:text class="text-zinc-500 text-sm mt-1">Projekt-ID (D3)</flux:text>
            </flux:card>
        </div>

        <flux:callout icon="information-circle" variant="secondary" class="mb-6">
            Die Projekt-ID wird beim D3-Push aller Bestellscheine dieses Projekts gesetzt und verknüpft sie in D3.
        </flux:callout>

        {{-- Begründung --}}
        <flux:card class="mb-6">
            <flux:heading size="lg" class="mb-3">Begründung</flux:heading>
            <flux:text class="text-zinc-500 text-sm mb-3">
                Wird beim Erstellen einer Bestellung in diesem Projekt als Vorgabe in das Begründungsfeld übernommen und kann dort angepasst werden.
            </flux:text>

            @if ($this->istErsteller())
                <flux:textarea
                    wire:model="begruendung"
                    label="Projekt-Begründung"
                    rows="4"
                    required
                />
                <flux:error name="begruendung" />
                <div class="flex justify-end mt-3">
                    <flux:button
                        variant="primary"
                        wire:click="begruendungSpeichern"
                        wire:loading.attr="disabled"
                        icon="check"
                    >
                        Begründung speichern
                    </flux:button>
                </div>
            @else
                <div class="text-sm whitespace-pre-wrap">{{ $projekt->begruendung ?: '—' }}</div>
            @endif
        </flux:card>

        {{-- Bestellungen --}}
        <flux:card class="mb-6">
            <flux:heading size="lg" class="mb-3">Bestellungen</flux:heading>

            @if ($this->bestellungen->isEmpty())
                <flux:text class="text-zinc-500">Noch keine Bestellungen in diesem Projekt.</flux:text>
            @else
                <flux:table>
                    <flux:table.columns>
                        <flux:table.column>BEN</flux:table.column>
                        <flux:table.column>Lieferant</flux:table.column>
                        <flux:table.column>Betreff</flux:table.column>
                        <flux:table.column>Ersteller</flux:table.column>
                        <flux:table.column>Status</flux:table.column>
                        <flux:table.column class="text-right">Betrag</flux:table.column>
                    </flux:table.columns>
                    <flux:table.rows>
                        @foreach ($this->bestellungen as $bestellung)
                            <flux:table.row
                                :key="$bestellung->id"
                                wire:navigate
                                :href="route('apps.bestellungen.detail', $bestellung)"
                                class="cursor-pointer"
                            >
                                <flux:table.cell class="font-mono text-sm">{{ $bestellung->nummer }}</flux:table.cell>
                                <flux:table.cell>{{ $bestellung->lieferantenname }}</flux:table.cell>
                                <flux:table.cell>{{ $bestellung->betreff }}</flux:table.cell>
                                <flux:table.cell>{{ optional($bestellung->user)->name }}</flux:table.cell>
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

        {{-- Mitglieder --}}
        <flux:card>
            <flux:heading size="lg" class="mb-3">Beteiligte</flux:heading>

            <ul class="divide-y divide-zinc-100 dark:divide-zinc-800 mb-4">
                {{-- Ersteller --}}
                <li class="flex items-center justify-between py-2">
                    <div>
                        <span class="font-medium">{{ optional($projekt->ersteller)->name }}</span>
                        <flux:badge size="sm" color="blue" class="ml-2">Ersteller</flux:badge>
                    </div>
                </li>

                {{-- Mitglieder --}}
                @foreach ($projekt->mitglieder as $mitglied)
                    <li class="flex items-center justify-between py-2">
                        <span>{{ $mitglied->name }}</span>

                        @if ($this->istErsteller())
                            <flux:button
                                variant="ghost"
                                icon="x-mark"
                                size="sm"
                                wire:click="mitgliedEntfernen({{ $mitglied->id }})"
                                wire:confirm="Mitglied '{{ $mitglied->name }}' wirklich entfernen?"
                            />
                        @endif
                    </li>
                @endforeach
            </ul>

            @if ($this->istErsteller())
                <div class="flex items-end gap-2">
                    <flux:field class="flex-1">
                        <flux:label>Mitglied hinzufügen</flux:label>
                        <flux:select
                            variant="listbox"
                            wire:model="mitgliedUserId"
                            searchable
                            clearable
                            placeholder="Mitarbeiter auswählen…"
                        >
                            @foreach ($this->userSuggestions as $user)
                                <flux:select.option
                                    wire:key="mitglied-user-{{ $user->id }}"
                                    value="{{ $user->id }}"
                                >
                                    {{ trim(($user->vorname ?? '').' '.($user->nachname ?? '')) }} ({{ $user->username }})
                                </flux:select.option>
                            @endforeach
                        </flux:select>
                        <flux:error name="mitgliedUserId" />
                    </flux:field>

                    <flux:button
                        variant="primary"
                        wire:click="mitgliedHinzufuegen"
                        wire:loading.attr="disabled"
                        icon="user-plus"
                    >
                        Hinzufügen
                    </flux:button>
                </div>
            @endif
        </flux:card>
    </x-intranet-app-bestellungen::bestellungen-layout>
</div>
