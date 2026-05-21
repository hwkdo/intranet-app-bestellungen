<div
    x-data="{
        initialTotals: {},
        rowTotals: {},
        initRowTotals(positionen) {
            this.initialTotals = {};
            this.rowTotals = {};
            positionen.forEach((pos, idx) => {
                const total = this.calcRowTotal(pos?.menge, pos?.preis);
                this.initialTotals[idx] = total;
                this.rowTotals[idx] = total;
            });
        },
        syncFromWire(positionen) {
            positionen.forEach((pos, idx) => {
                if (this.rowTotals[idx] === undefined) {
                    const total = this.calcRowTotal(pos?.menge, pos?.preis);
                    this.initialTotals[idx] = total;
                    this.rowTotals[idx] = total;
                }
            });
        },
        calcRowTotal(menge, preis) {
            const m = Number(menge ?? 0);
            const p = Number(preis ?? 0);

            if (Number.isNaN(m) || Number.isNaN(p)) {
                return 0;
            }

            return m * p;
        },
        setRowTotal(idx, menge, preis) {
            this.rowTotals[idx] = this.calcRowTotal(menge, preis);
        },
        total() {
            return Object.values(this.rowTotals).reduce((sum, val) => sum + Number(val || 0), 0);
        },
        formatEuro(value) {
            return new Intl.NumberFormat('de-DE', { minimumFractionDigits: 2, maximumFractionDigits: 2 }).format(Number(value || 0));
        },
        kontierungPercents: {},
        initKontierungPercents(kontierung) {
            this.kontierungPercents = {};
            kontierung.forEach((row, idx) => {
                let p = Number(row?.aufteilung ?? 0);
                if (Number.isNaN(p)) {
                    p = 0;
                }
                this.kontierungPercents[idx] = p;
            });
        },
        syncKontierungFromWire(kontierung) {
            kontierung.forEach((row, idx) => {
                if (this.kontierungPercents[idx] === undefined) {
                    let p = Number(row?.aufteilung ?? 0);
                    if (Number.isNaN(p)) {
                        p = 0;
                    }
                    this.kontierungPercents[idx] = p;
                }
            });
            Object.keys(this.kontierungPercents).forEach((key) => {
                if (Number(key) >= kontierung.length) {
                    delete this.kontierungPercents[key];
                }
            });
        },
        setKontierungPercent(idx, value) {
            let p = Number(value ?? 0);
            if (Number.isNaN(p)) {
                p = 0;
            }
            this.kontierungPercents[idx] = p;
        },
        kontierungSumme() {
            return Object.values(this.kontierungPercents).reduce((sum, val) => sum + Number(val || 0), 0);
        },
        formatPercent(value) {
            return new Intl.NumberFormat('de-DE', { minimumFractionDigits: 2, maximumFractionDigits: 2 }).format(Number(value || 0));
        },
    }"
    x-init="initRowTotals(@js($positionen)); initKontierungPercents(@js($kontierung))"
    x-effect="syncFromWire($wire.positionen || []); syncKontierungFromWire($wire.kontierung || [])"
>
    <x-intranet-app-bestellungen::bestellungen-layout heading="Neue Bestellung" subheading="Bestellschein erfassen und zur Freigabe einreichen">
        <form wire:submit="speichern" class="space-y-6">
            @if ($errors->any())
                <flux:callout icon="exclamation-triangle" variant="danger">
                    <div class="font-medium">Bitte korrigiere die markierten Eingaben.</div>
                    <ul class="mt-1 list-disc pl-5 text-sm">
                        @foreach ($errors->all() as $error)
                            <li>{{ $error }}</li>
                        @endforeach
                    </ul>
                </flux:callout>
            @endif

            <flux:card>
                <flux:heading size="lg" class="mb-4">Allgemeine Angaben</flux:heading>

                <div class="grid gap-4 md:grid-cols-2">
                    <flux:field>
                        <flux:label>Lieferant</flux:label>
                        <flux:select
                            variant="combobox"
                            wire:model.live="lieferantennummer"
                            :filter="false"
                            clearable
                            placeholder="Lieferant wählen…"
                        >
                            <x-slot name="input">
                                <flux:select.input
                                    wire:model.live.debounce.250ms="lieferantSearch"
                                    placeholder="Name oder Nummer eingeben…"
                                />
                            </x-slot>

                            @foreach ($this->lieferantenSuggestions as $lieferant)
                                <flux:select.option
                                    wire:key="lf-{{ $lieferant->lieferantennummer }}"
                                    value="{{ $lieferant->lieferantennummer }}"
                                >
                                    {{ $lieferant->lieferantenname }} ({{ $lieferant->lieferantennummer }})
                                </flux:select.option>
                            @endforeach

                            <x-slot name="empty">
                                <flux:select.option.empty when-loading="Suche läuft…">
                                    Keine Lieferanten gefunden.
                                </flux:select.option.empty>
                            </x-slot>
                        </flux:select>
                        <flux:error name="lieferantennummer" />
                    </flux:field>

                    <div class="flex items-end">
                        <flux:modal.trigger name="fehlender-lieferant">
                            <flux:button variant="outline" class="w-full">
                                Lieferant fehlt
                            </flux:button>
                        </flux:modal.trigger>
                    </div>

                    <livewire:intranet-app-bestellungen::apps.bestellungen.erstellen-projekt-select wire:model="projektId" />

                    <flux:field>
                        <flux:label>Kostenstelle</flux:label>
                        <flux:select
                            variant="combobox"
                            wire:model.live="kostenstelle"
                            :filter="false"
                            clearable
                            placeholder="Kostenstelle wählen…"
                        >
                            <x-slot name="input">
                                <flux:select.input
                                    wire:model.live.debounce.250ms="kostenstelleSearch"
                                    placeholder="Nummer oder Bezeichnung eingeben…"
                                />
                            </x-slot>

                            @foreach ($this->kostenstellenSuggestions as $kst)
                                <flux:select.option
                                    wire:key="kst-{{ $kst->kostenstelle }}"
                                    value="{{ $kst->kostenstelle }}"
                                >
                                    {{ $kst->kostenstelle }} – {{ $kst->bezeichnung }}
                                </flux:select.option>
                            @endforeach

                            <x-slot name="empty">
                                <flux:select.option.empty when-loading="Suche läuft…">
                                    Keine Kostenstellen gefunden.
                                </flux:select.option.empty>
                            </x-slot>
                        </flux:select>
                        <flux:error name="kostenstelle" />
                    </flux:field>

                    <flux:field>
                        <flux:label>Lieferanschrift</flux:label>
                        <flux:select
                            variant="listbox"
                            wire:model="lieferanschriftUserId"
                            searchable
                            clearable
                            placeholder="User auswählen…"
                        >
                            @foreach ($this->lieferanschriftUserSuggestions as $u)
                                <flux:select.option
                                    wire:key="lieferanschrift-user-{{ $u->id }}"
                                    value="{{ $u->id }}"
                                >
                                    {{ trim(($u->vorname ?? '').' '.($u->nachname ?? '')) }} ({{ $u->username }})
                                </flux:select.option>
                            @endforeach
                        </flux:select>
                        <flux:error name="lieferanschriftUserId" />
                    </flux:field>

                    <flux:input
                        wire:model="haushaltsjahr"
                        type="number"
                        label="Haushaltsjahr"
                        min="2000"
                        max="2100"
                    />

                    <flux:input
                        wire:model="betreff"
                        label="Betreff"
                        placeholder="Kurzbeschreibung der Bestellung"
                    />

                    <flux:field>
                        <flux:label>D3 - Gruppen</flux:label>
                        <flux:select wire:model.live="d3GruppenAuswahl" multiple variant="listbox">
                            @foreach ($this->d3GruppenOptionenSortiert as $gruppe)
                                <flux:select.option value="{{ $gruppe }}">{{ $gruppe }}</flux:select.option>
                            @endforeach
                        </flux:select>
                        <flux:error name="d3GruppenAuswahl" />
                        <flux:error name="d3GruppenAuswahl.*" />
                    </flux:field>

                    <div class="md:col-span-2">
                        <flux:textarea
                            wire:model="begruendung"
                            label="Begründung"
                            placeholder="Hintergrund und Kontext der Bestellung für den Freigeber"
                            rows="3"
                            required
                        />
                        <flux:error name="begruendung" />
                    </div>
                </div>
            </flux:card>

            <flux:card>
                <div class="flex items-center justify-between mb-4">
                    <flux:heading size="lg">Positionen</flux:heading>
                    <div class="flex items-center gap-2">
                        <flux:button type="button" size="sm" icon="plus" wire:click="addPosition">Position hinzufügen</flux:button>
                        <flux:button type="button" size="sm" icon="document-text" wire:click="addPdfPosition">PDF-Position</flux:button>
                    </div>
                </div>

                <div class="overflow-x-auto">
                    <table class="w-full text-sm">
                        <thead class="text-xs uppercase tracking-wide text-zinc-600 dark:text-white/70 border-b border-zinc-200 dark:border-zinc-700">
                            <tr>
                                <th class="py-2 pr-2 text-left w-10">Nr.</th>
                                <th class="py-2 px-2 text-left">Bezeichnung</th>
                                <th class="py-2 px-2 text-left w-28">Art.-Nr.</th>
                                <th class="py-2 px-2 text-right w-20">Menge</th>
                                <th class="py-2 px-2 text-left w-20">Einh.</th>
                                <th class="py-2 px-2 text-right w-28">Einzelpreis</th>
                                <th class="py-2 px-2 text-right w-28">Gesamt</th>
                                <th class="py-2 px-2 text-left w-44">Anlage</th>
                                <th class="py-2 pl-2 w-10"></th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-zinc-100 dark:divide-zinc-800">
                            @foreach ($positionen as $idx => $position)
                                @php($isPdfPosition = $this->isPdfPosition($idx))
                                @php($zeilenSumme = (float) ($position['menge'] ?? 0) * (float) ($position['preis'] ?? 0))
                                <tr wire:key="pos-{{ $idx }}" class="align-top">
                                    <td class="py-2 pr-2 text-zinc-600 dark:text-white/70">
                                        {{ $idx + 1 }}
                                        @if ($isPdfPosition)
                                            <div class="mt-1">
                                                <flux:badge size="sm" color="sky" icon="document-text">PDF</flux:badge>
                                            </div>
                                        @endif
                                    </td>
                                    <td class="py-2 px-2">
                                        @if ($isPdfPosition)
                                            <span class="text-zinc-600 dark:text-white/70 italic text-xs">Siehe PDF-Anlage</span>
                                        @else
                                            <flux:input
                                                size="sm"
                                                wire:model.blur="positionen.{{ $idx }}.bezeichnung"
                                                placeholder="Bezeichnung"
                                            />
                                            <flux:error name="positionen.{{ $idx }}.bezeichnung" />
                                        @endif
                                    </td>
                                    <td class="py-2 px-2">
                                        @if ($isPdfPosition)
                                            <span class="text-zinc-500 dark:text-white/50">—</span>
                                        @else
                                            <flux:input
                                                size="sm"
                                                wire:model.blur="positionen.{{ $idx }}.art_nr"
                                                placeholder="Art.-Nr."
                                            />
                                            <flux:error name="positionen.{{ $idx }}.art_nr" />
                                        @endif
                                    </td>
                                    <td class="py-2 px-2">
                                        @if ($isPdfPosition)
                                            <span class="text-zinc-500 dark:text-white/50">—</span>
                                        @else
                                            <flux:input
                                                size="sm"
                                                wire:model.live.debounce.300ms="positionen.{{ $idx }}.menge"
                                                x-on:input="setRowTotal({{ $idx }}, $event.target.value, $wire.positionen?.[{{ $idx }}]?.preis)"
                                                type="number"
                                                step="0.01"
                                                class="text-right"
                                            />
                                            <flux:error name="positionen.{{ $idx }}.menge" />
                                        @endif
                                    </td>
                                    <td class="py-2 px-2">
                                        @if ($isPdfPosition)
                                            <span class="text-zinc-500 dark:text-white/50">—</span>
                                        @else
                                            <flux:input
                                                size="sm"
                                                wire:model.blur="positionen.{{ $idx }}.einheit"
                                                placeholder="Stk"
                                            />
                                            <flux:error name="positionen.{{ $idx }}.einheit" />
                                        @endif
                                    </td>
                                    <td class="py-2 px-2">
                                        @if ($isPdfPosition)
                                            <span class="text-zinc-500 dark:text-white/50">—</span>
                                        @else
                                            <flux:input
                                                size="sm"
                                                wire:model.live.debounce.300ms="positionen.{{ $idx }}.preis"
                                                x-on:input="setRowTotal({{ $idx }}, $wire.positionen?.[{{ $idx }}]?.menge, $event.target.value)"
                                                type="number"
                                                step="0.01"
                                                class="text-right"
                                            />
                                            <flux:error name="positionen.{{ $idx }}.preis" />
                                        @endif
                                    </td>
                                    <td class="py-2 px-2">
                                        @if ($isPdfPosition)
                                            <flux:input
                                                size="sm"
                                                wire:model.live.debounce.300ms="positionen.{{ $idx }}.preis"
                                                x-on:input="setRowTotal({{ $idx }}, 1, $event.target.value)"
                                                type="number"
                                                step="0.01"
                                                placeholder="Gesamtpreis"
                                                class="text-right"
                                            />
                                            <flux:error name="positionen.{{ $idx }}.preis" />
                                        @else
                                            <div class="text-right tabular-nums py-1.5">
                                                <span x-text="formatEuro((rowTotals[{{ $idx }}] ?? initialTotals[{{ $idx }}] ?? {{ $zeilenSumme }}))"></span> €
                                            </div>
                                        @endif
                                    </td>
                                    <td class="py-2 px-2">
                                        @if ($isPdfPosition)
                                            <input
                                                type="file"
                                                wire:model="positionPdfs.{{ $idx }}"
                                                accept="application/pdf,.pdf"
                                                class="block w-full text-xs text-zinc-600 dark:text-zinc-300 file:mr-2 file:py-1 file:px-2 file:rounded file:border-0 file:bg-zinc-100 file:text-xs hover:file:bg-zinc-200 dark:file:bg-zinc-700 dark:hover:file:bg-zinc-600"
                                            />
                                            @error('positionPdfs.'.$idx)
                                                <p class="text-xs text-red-500 mt-1">{{ $message }}</p>
                                            @enderror

                                            @if (! empty($positionPdfs[$idx]))
                                                <div class="mt-1 flex items-center gap-2 rounded border border-zinc-200 px-2 py-1 text-xs text-zinc-600 dark:border-zinc-700 dark:text-zinc-300">
                                                    <flux:icon name="document-text" class="size-4 shrink-0 text-sky-500" />
                                                    <span class="truncate" title="{{ $positionPdfs[$idx]->getClientOriginalName() }}">
                                                        {{ $positionPdfs[$idx]->getClientOriginalName() }}
                                                    </span>
                                                </div>
                                            @endif
                                        @else
                                            <span class="text-zinc-500 dark:text-white/50 text-xs">—</span>
                                        @endif
                                    </td>
                                    <td class="py-2 pl-2 text-right">
                                        @if (count($positionen) > 1)
                                            <flux:button
                                                type="button"
                                                size="xs"
                                                variant="ghost"
                                                icon="trash"
                                                wire:click="removePosition({{ $idx }})"
                                            />
                                        @endif
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>

                <div class="mt-4 text-right">
                    <flux:heading size="md">
                        Gesamt: <span class="text-emerald-600 dark:text-emerald-400"><span x-text="formatEuro(total())"></span> €</span>
                    </flux:heading>
                </div>
            </flux:card>

            <flux:card>
                <div class="flex items-center justify-between mb-4">
                    <div>
                        <flux:heading size="lg">Kontierung</flux:heading>
                        <flux:text class="text-sm">Kostenstelle, Kursnummer, Verwendungsort und prozentuale Aufteilung. Summe sollte 100% ergeben.</flux:text>
                    </div>
                    <flux:button type="button" size="sm" icon="plus" wire:click="addKontierung">Zeile hinzufügen</flux:button>
                </div>

                <div class="overflow-x-auto">
                    <table class="w-full text-sm">
                        <thead class="text-xs uppercase tracking-wide text-zinc-600 dark:text-white/70 border-b border-zinc-200 dark:border-zinc-700">
                            <tr>
                                <th class="py-2 px-2 text-left">Kostenstelle</th>
                                <th class="py-2 px-2 text-left w-48">Kursnummer</th>
                                <th class="py-2 px-2 text-left w-56">Verw.-Ort / Raum</th>
                                <th class="py-2 px-2 text-right w-28">% Aufteilung</th>
                                <th class="py-2 pl-2 w-10"></th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-zinc-100 dark:divide-zinc-800">
                            @foreach ($kontierung as $kIdx => $kontZeile)
                                <tr wire:key="kont-{{ $kIdx }}" class="align-top">
                                    <td class="py-2 px-2">
                                        <flux:select
                                            variant="combobox"
                                            wire:model="kontierung.{{ $kIdx }}.kostenstelle"
                                            :filter="false"
                                            clearable
                                            placeholder="Kostenstelle wählen…"
                                        >
                                            <x-slot name="input">
                                                <flux:select.input
                                                    wire:model.live.debounce.250ms="kontierungSearch.{{ $kIdx }}"
                                                    placeholder="Suchen…"
                                                />
                                            </x-slot>

                                            @foreach ($this->kostenstellenForKontierung($kIdx) as $kst)
                                                <flux:select.option
                                                    wire:key="kont-{{ $kIdx }}-kst-{{ $kst->kostenstelle }}"
                                                    value="{{ $kst->kostenstelle }}"
                                                >
                                                    {{ $kst->kostenstelle }} – {{ $kst->bezeichnung }}
                                                </flux:select.option>
                                            @endforeach

                                            <x-slot name="empty">
                                                <flux:select.option.empty when-loading="Suche läuft…">
                                                    Keine Kostenstellen gefunden.
                                                </flux:select.option.empty>
                                            </x-slot>
                                        </flux:select>
                                    </td>
                                    <td class="py-2 px-2">
                                        <flux:input
                                            size="sm"
                                            wire:model="kontierung.{{ $kIdx }}.kursnummer"
                                            placeholder="Kursnummer"
                                        />
                                    </td>
                                    <td class="py-2 px-2">
                                        <flux:input
                                            size="sm"
                                            wire:model="kontierung.{{ $kIdx }}.raum"
                                            placeholder="Verw.-Ort / Raum"
                                        />
                                    </td>
                                    <td class="py-2 px-2">
                                        <flux:input
                                            size="sm"
                                            wire:model.live.debounce.300ms="kontierung.{{ $kIdx }}.aufteilung"
                                            x-on:input="setKontierungPercent({{ $kIdx }}, $event.target.value)"
                                            type="number"
                                            step="0.01"
                                            min="0"
                                            max="100"
                                            class="text-right"
                                        />
                                    </td>
                                    <td class="py-2 pl-2 text-right">
                                        @if (count($kontierung) > 1)
                                            <flux:button
                                                type="button"
                                                size="xs"
                                                variant="ghost"
                                                icon="trash"
                                                wire:click="removeKontierung({{ $kIdx }})"
                                            />
                                        @endif
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>

                <div class="mt-3 text-right text-sm">
                    Summe: <strong><span x-text="formatPercent(kontierungSumme())"></span> %</strong>
                    <span x-show="Math.abs(kontierungSumme() - 100) > 0.01" x-cloak class="ml-2 inline">
                        <flux:badge size="sm" color="amber">Nicht 100 %</flux:badge>
                    </span>
                </div>
            </flux:card>

            <flux:card>
                <flux:heading size="md" class="mb-2">Vorschau Workflow</flux:heading>
                @if ($this->freigeberHinweis())
                    <flux:callout icon="information-circle" class="mb-2">{{ $this->freigeberHinweis() }}</flux:callout>
                @endif
                @if ($this->angebotsHinweis())
                    <flux:callout icon="exclamation-triangle" variant="warning">{{ $this->angebotsHinweis() }}</flux:callout>
                @endif
            </flux:card>

            <div class="flex justify-end gap-3">
                <flux:button type="button" variant="ghost" :href="route('apps.bestellungen.index')" wire:navigate>
                    Abbrechen
                </flux:button>
                <flux:button
                    type="submit"
                    variant="primary"
                    icon="paper-airplane"
                    wire:loading.attr="disabled"
                    wire:target="speichern"
                >
                    <span wire:loading.remove wire:target="speichern">Bestellung einreichen</span>
                    <span wire:loading wire:target="speichern" class="inline-flex items-center gap-2">
                        <flux:icon name="arrow-path" class="size-4 animate-spin" />
                        Wird eingereicht...
                    </span>
                </flux:button>
            </div>
        </form>
    </x-intranet-app-bestellungen::bestellungen-layout>

    <flux:modal name="fehlender-lieferant" class="max-w-lg">
        <flux:heading size="lg">Lieferant fehlt</flux:heading>
        <flux:text class="mt-1 mb-4">
            Melden Sie einen fehlenden Lieferanten. Die Buchhaltung wird informiert und ein Platzhalter-Lieferant wird für diese Bestellung gesetzt.
        </flux:text>

        <div class="space-y-4">
            <flux:input
                wire:model="fehlenderLieferantName"
                label="Name"
                placeholder="Name des Lieferanten"
                required
            />
            <flux:error name="fehlenderLieferantName" />

            <flux:textarea
                wire:model="fehlenderLieferantAdresse"
                label="Adresse"
                placeholder="Straße, PLZ Ort"
                rows="3"
            />
            <flux:error name="fehlenderLieferantAdresse" />

            <flux:input
                wire:model="fehlenderLieferantIban"
                label="IBAN"
                placeholder="DE…"
            />
            <flux:error name="fehlenderLieferantIban" />

            <flux:input
                wire:model="fehlenderLieferantWebseite"
                label="Webseite"
                placeholder="https://…"
            />
            <flux:error name="fehlenderLieferantWebseite" />
        </div>

        <div class="flex gap-2 justify-end mt-6">
            <flux:modal.close>
                <flux:button variant="ghost">Abbrechen</flux:button>
            </flux:modal.close>
            <flux:button
                variant="primary"
                wire:click="meldeFehlendenLieferant"
                wire:loading.attr="disabled"
                wire:target="meldeFehlendenLieferant"
            >
                <span wire:loading.remove wire:target="meldeFehlendenLieferant">Melden</span>
                <span wire:loading wire:target="meldeFehlendenLieferant" class="inline-flex items-center gap-2">
                    <flux:icon name="arrow-path" class="size-4 animate-spin" />
                    Wird gesendet…
                </span>
            </flux:button>
        </div>
    </flux:modal>
</div>
