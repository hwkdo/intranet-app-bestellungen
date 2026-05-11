<div>
    <div class="space-y-4">
        <div class="flex items-center justify-between">
            <flux:heading size="lg">Wertgrenzen &amp; Freigabe-Stufen</flux:heading>
            <flux:button icon="plus" wire:click="addStufe">Stufe hinzufügen</flux:button>
        </div>

        @if (empty($stufen))
            <flux:callout icon="exclamation-triangle" variant="warning">
                Keine Stufen definiert. Bestellungen können bislang nicht freigegeben werden.
            </flux:callout>
        @else
            <div class="space-y-6">
                @foreach ($stufen as $idx => $stufe)
                    <flux:card wire:key="stufe-{{ $idx }}">
                        {{-- Header --}}
                        <div class="mb-4 flex items-center justify-between">
                            <flux:heading size="sm">Stufe {{ $idx + 1 }}: {{ $stufe['bezeichnung'] ?? '' }}</flux:heading>
                            <flux:button
                                type="button"
                                variant="ghost"
                                size="sm"
                                icon="trash"
                                wire:click="removeStufe({{ $idx }})"
                            >
                                Stufe entfernen
                            </flux:button>
                        </div>

                        {{-- Basis-Felder --}}
                        <div class="grid gap-3 md:grid-cols-3">
                            <flux:input
                                wire:model="stufen.{{ $idx }}.bezeichnung"
                                label="Bezeichnung"
                                placeholder="z. B. Bis 500 €"
                            />
                            <flux:input
                                wire:model="stufen.{{ $idx }}.vonBetrag"
                                type="number"
                                step="0.01"
                                label="Von Betrag (€)"
                                placeholder="0"
                            />
                            <flux:input
                                wire:model="stufen.{{ $idx }}.bisBetrag"
                                type="number"
                                step="0.01"
                                label="Bis Betrag (€) – leer = unbegrenzt"
                            />
                        </div>

                        {{-- Informationstexte --}}
                        <div class="mt-3 grid gap-3 md:grid-cols-3">
                            <flux:input
                                wire:model="stufen.{{ $idx }}.textBerechtigt"
                                label="Text: Wer darf bestellen (informativ)"
                                placeholder="z. B. AL, GL, FK, Ausbilder/in oder SB"
                            />
                            <flux:input
                                wire:model="stufen.{{ $idx }}.textFreigeber1"
                                label="Text: Freigeber 1 (informativ)"
                                placeholder="z. B. Direkter Vorgesetzter"
                            />
                            <flux:input
                                wire:model="stufen.{{ $idx }}.textFreigeber2"
                                label="Text: Freigeber 2 (informativ)"
                                placeholder="z. B. GF – leer = keine zweite Freigabe"
                            />
                        </div>

                        {{-- Bestellberechtigung --}}
                        <div class="mt-4">
                            <flux:heading size="xs" class="mb-2">Bestellberechtigung (darfBestellen)</flux:heading>
                            <div class="grid gap-3 md:grid-cols-2">
                                <flux:field>
                                    <flux:label>Berechtigte Attribute (User-Eigenschaften)</flux:label>
                                    <flux:select
                                        wire:model="stufen.{{ $idx }}.berechtigteAttribute"
                                        multiple
                                        variant="listbox"
                                    >
                                        @foreach ($this->userAttributeOptions as $attr => $label)
                                            <flux:select.option value="{{ $attr }}">{{ $label }}</flux:select.option>
                                        @endforeach
                                    </flux:select>
                                    <flux:description>Mindestens eines dieser Attribute muss der User haben.</flux:description>
                                </flux:field>
                                <flux:field>
                                    <flux:label>Berechtigte Rollen</flux:label>
                                    <flux:select
                                        wire:model="stufen.{{ $idx }}.berechtigteRollen"
                                        multiple
                                        variant="listbox"
                                    >
                                        @foreach ($this->rollenOptions as $rolle)
                                            <flux:select.option value="{{ $rolle }}">{{ $rolle }}</flux:select.option>
                                        @endforeach
                                    </flux:select>
                                    <flux:description>Oder mindestens eine dieser Rollen.</flux:description>
                                </flux:field>
                            </div>
                        </div>

                        {{-- Freigabe 1 Regeln --}}
                        <div class="mt-4">
                            <div class="mb-2 flex items-center justify-between">
                                <flux:heading size="xs">Freigabe 1 – Regelwerk</flux:heading>
                                <flux:button
                                    type="button"
                                    variant="ghost"
                                    size="sm"
                                    icon="plus"
                                    wire:click="addFreigabe1Regel({{ $idx }})"
                                >
                                    Regel hinzufügen
                                </flux:button>
                            </div>

                            @if (empty($stufe['freigabe1Regeln']))
                                <p class="text-sm text-zinc-500">Keine Regeln – kein Freigeber erforderlich.</p>
                            @else
                                <div class="space-y-2">
                                    @foreach ($stufe['freigabe1Regeln'] as $rIdx => $regel)
                                        @include('intranet-app-bestellungen::livewire.apps.bestellungen.admin.partials.freigebe-regel-row', [
                                            'stufenIdx' => $idx,
                                            'regelIdx' => $rIdx,
                                            'freigabeNr' => 1,
                                            'regel' => $regel,
                                        ])
                                    @endforeach
                                </div>
                            @endif
                        </div>

                        {{-- Freigabe 2 Regeln --}}
                        <div class="mt-4 border-t pt-4">
                            <div class="mb-2 flex items-center justify-between">
                                <flux:heading size="xs">Freigabe 2 – Regelwerk (leer = keine zweite Freigabe)</flux:heading>
                                <flux:button
                                    type="button"
                                    variant="ghost"
                                    size="sm"
                                    icon="plus"
                                    wire:click="addFreigabe2Regel({{ $idx }})"
                                >
                                    Regel hinzufügen
                                </flux:button>
                            </div>

                            @if (empty($stufe['freigabe2Regeln']))
                                <p class="text-sm text-zinc-500">Keine zweite Freigabe für diese Stufe.</p>
                            @else
                                <div class="space-y-2">
                                    @foreach ($stufe['freigabe2Regeln'] as $rIdx => $regel)
                                        @include('intranet-app-bestellungen::livewire.apps.bestellungen.admin.partials.freigebe-regel-row', [
                                            'stufenIdx' => $idx,
                                            'regelIdx' => $rIdx,
                                            'freigabeNr' => 2,
                                            'regel' => $regel,
                                        ])
                                    @endforeach
                                </div>
                            @endif
                        </div>
                    </flux:card>
                @endforeach
            </div>
        @endif

        <div class="flex justify-end">
            <flux:button variant="primary" icon="check" wire:click="speichern">Speichern</flux:button>
        </div>

        <flux:card>
            <flux:heading size="sm" class="mb-2">Freigabe-Stufen als JSON (nur dieses Array)</flux:heading>
            <flux:description class="mb-3">
                Export aus einer Umgebung kopieren und in einer anderen einfügen. Es wird nur
                <code class="rounded bg-zinc-100 px-1 py-0.5 text-xs dark:bg-zinc-800">freigabeStufen</code>
                ersetzt; alle übrigen App-Einstellungen bleiben unverändert. Rollen-Namen müssen in der Zielumgebung existieren.
            </flux:description>

            <div x-data="{ copied: false }" class="mb-4 space-y-2">
                <div class="flex flex-wrap items-center justify-between gap-2">
                    <flux:subheading>Export (aktueller Stand)</flux:subheading>
                    <flux:button
                        type="button"
                        size="sm"
                        variant="ghost"
                        icon="clipboard-document"
                        x-bind:disabled="copied"
                        x-on:click="
                            navigator.clipboard.writeText(document.getElementById('freigabe-stufen-export-json').value).then(() => {
                                copied = true;
                                setTimeout(() => (copied = false), 2000);
                            })
                        "
                    >
                        <span x-show="!copied">In Zwischenablage kopieren</span>
                        <span x-show="copied" x-cloak>Kopiert</span>
                    </flux:button>
                </div>
                <textarea
                    id="freigabe-stufen-export-json"
                    readonly
                    rows="14"
                    class="w-full resize-y rounded-lg border border-zinc-200 bg-zinc-50 p-3 font-mono text-xs text-zinc-900 dark:border-zinc-700 dark:bg-zinc-900 dark:text-zinc-100"
                >{{ $this->freigabeStufenAlsFormatiertesJson() }}</textarea>
            </div>

            <flux:separator class="my-4" />

            <flux:subheading class="mb-2">Import</flux:subheading>
            <flux:field>
                <flux:label>JSON einfügen</flux:label>
                <flux:textarea
                    wire:model="freigabeStufenJsonImport"
                    rows="10"
                    class="font-mono text-xs"
                    placeholder='[ { "bezeichnung": "…", "vonBetrag": 0, … }, … ]'
                />
                <flux:error name="freigabeStufenJsonImport" />
            </flux:field>
            <div class="mt-3 flex flex-wrap gap-2">
                <flux:button
                    type="button"
                    variant="primary"
                    icon="arrow-down-tray"
                    wire:click="freigabeStufenAusJsonUebernehmenUndSpeichern"
                >
                    Aus JSON übernehmen &amp; speichern
                </flux:button>
            </div>
        </flux:card>
    </div>
</div>
