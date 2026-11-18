<div>
    <x-intranet-app-bestellungen::bestellungen-layout
        :heading="$projekt->name"
        :subheading="$projekt->beschreibung ?? 'Projektübersicht'"
    >
        <x-slot:actions>
            <a
                href="{{ route('apps.bestellungen.erstellen', ['projekt' => $projekt->id]) }}"
                wire:navigate
                class="inline-flex items-center gap-2 rounded-full bg-white px-4 py-2 text-sm font-semibold text-[#073070] shadow-md shadow-[#04214e]/20 transition hover:bg-[#d0e3f9]"
            >
                <flux:icon icon="plus" variant="micro" class="size-4" />
                Bestellung erstellen
            </a>
        </x-slot:actions>

        <div class="mb-4">
            <a
                href="{{ route('apps.bestellungen.projekte.index') }}"
                wire:navigate
                class="inline-flex items-center gap-1.5 text-sm font-medium text-[#073070] hover:underline dark:text-[#d0e3f9]"
            >
                <flux:icon icon="arrow-left" variant="micro" class="size-4" />
                Alle Projekte
            </a>
        </div>

        <div class="mb-4 grid grid-cols-1 gap-4 sm:grid-cols-3">
            <section class="glass-card overflow-hidden p-5 text-center">
                <p class="text-2xl font-semibold text-[#073070] dark:text-white">{{ $this->bestellungen->count() }}</p>
                <p class="mt-1 text-sm text-slate-600 dark:text-white/70">Bestellungen</p>
            </section>
            <section class="glass-card overflow-hidden p-5 text-center">
                <p class="text-2xl font-semibold text-[#073070] dark:text-white">{{ number_format($this->gesamtkosten, 2, ',', '.') }} €</p>
                <p class="mt-1 text-sm text-slate-600 dark:text-white/70">Gesamtkosten</p>
            </section>
            <section class="glass-card overflow-hidden p-5 text-center">
                <p class="text-2xl font-semibold text-[#073070] dark:text-white">{{ $projekt->mitglieder->count() + 1 }}</p>
                <p class="mt-1 text-sm text-slate-600 dark:text-white/70">Beteiligte</p>
            </section>
        </div>

        <section class="glass-card mb-6 overflow-hidden px-5">
            <div class="flex flex-col gap-1 border-b border-[#d0e3f9] py-4 sm:flex-row sm:items-center sm:justify-between sm:gap-6 dark:border-white/10">
                <span class="text-sm font-medium text-[#073070] dark:text-white">Projekt-ID (D3)</span>
                <span class="font-mono text-sm break-words text-slate-600 sm:text-right dark:text-white/70">{{ $projekt->d3_projekt_id ?: '—' }}</span>
            </div>
            <p class="py-4 text-sm text-slate-600 dark:text-white/70">
                Die Projekt-ID wird beim D3-Push aller Bestellscheine dieses Projekts gesetzt und verknüpft sie in D3.
            </p>
        </section>

        {{-- Begründung --}}
        <section class="glass-card overflow-hidden p-5 mb-6">
            <flux:heading size="lg" class="mb-3">Begründung</flux:heading>
            <flux:text class="mb-3 text-sm text-slate-600 dark:text-white/70">
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
        </section>

        {{-- Bestellungen --}}
        <section class="glass-card overflow-hidden p-5 mb-6">
            <flux:heading size="lg" class="mb-3">Bestellungen</flux:heading>

            @if ($this->bestellungen->isEmpty())
                <x-intranet-app-bestellungen::hwk-empty bare icon="document-text" title="Keine Bestellungen">
                    Noch keine Bestellungen in diesem Projekt.
                </x-intranet-app-bestellungen::hwk-empty>
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
        </section>

        {{-- Mitglieder --}}
        <section class="glass-card overflow-hidden p-5">
            <flux:heading size="lg" class="mb-3">Beteiligte</flux:heading>

            <ul class="divide-y divide-[#d0e3f9] dark:divide-white/10 mb-4">
                {{-- Ersteller --}}
                <li class="flex items-center justify-between py-2">
                    <div>
                        <span class="font-medium">{{ optional($projekt->ersteller)->name }}</span>
                        <flux:badge size="sm" color="zinc" class="ml-2">Ersteller</flux:badge>
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
        </section>
    </x-intranet-app-bestellungen::bestellungen-layout>
</div>
