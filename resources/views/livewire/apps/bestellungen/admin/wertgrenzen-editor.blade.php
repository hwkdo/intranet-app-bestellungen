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
            <div class="space-y-3">
                @foreach ($stufen as $idx => $stufe)
                    <flux:card wire:key="stufe-{{ $idx }}">
                        <div class="grid gap-3 md:grid-cols-3">
                            <flux:input
                                wire:model="stufen.{{ $idx }}.bezeichnung"
                                label="Bezeichnung"
                                placeholder="z. B. Bis 500 €"
                            />
                            <flux:input
                                wire:model="stufen.{{ $idx }}.bisBetrag"
                                type="number"
                                step="0.01"
                                label="Gilt bis Betrag (€) – leer = unbegrenzt"
                            />
                            <div class="flex items-end">
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

                            <flux:select
                                wire:model="stufen.{{ $idx }}.freigeberUserIds"
                                multiple
                                label="Freigeber (konkrete Personen)"
                            >
                                @foreach ($this->userOptions as $userId => $name)
                                    <flux:select.option value="{{ $userId }}">{{ $name }}</flux:select.option>
                                @endforeach
                            </flux:select>

                            <flux:select
                                wire:model="stufen.{{ $idx }}.freigeberRollen"
                                multiple
                                label="Freigeber (Rollen)"
                            >
                                @foreach ($this->rollenOptions as $rolle)
                                    <flux:select.option value="{{ $rolle }}">{{ $rolle }}</flux:select.option>
                                @endforeach
                            </flux:select>

                            <div class="space-y-2">
                                <flux:switch
                                    wire:model="stufen.{{ $idx }}.zweiteFreigabeErforderlich"
                                    label="Zweite Freigabe immer erforderlich"
                                />
                                <flux:input
                                    wire:model="stufen.{{ $idx }}.zweiteFreigabeAb"
                                    type="number"
                                    step="0.01"
                                    label="Zweite Freigabe ab Betrag (€)"
                                />
                            </div>
                        </div>
                    </flux:card>
                @endforeach
            </div>
        @endif

        <div class="flex justify-end">
            <flux:button variant="primary" icon="check" wire:click="speichern">Speichern</flux:button>
        </div>
    </div>
</div>
