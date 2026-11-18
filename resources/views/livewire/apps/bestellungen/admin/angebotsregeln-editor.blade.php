<div>
    <div class="space-y-4">
        <div class="flex items-center justify-between">
            <flux:heading size="lg">Angebotsregeln</flux:heading>
            <flux:button icon="plus" wire:click="addRegel">Regel hinzufügen</flux:button>
        </div>

        @if (empty($regeln))
            <x-intranet-app-bestellungen::hwk-empty icon="check-badge" title="Keine Regel">
                Keine Regel definiert – es werden keine Angebote verlangt.
            </x-intranet-app-bestellungen::hwk-empty>
        @else
            <div class="space-y-3">
                @foreach ($regeln as $idx => $regel)
                    <section class="glass-card overflow-hidden p-5" wire:key="regel-{{ $idx }}">
                        <div class="grid gap-3 md:grid-cols-4">
                            <flux:input
                                wire:model="regeln.{{ $idx }}.abBetrag"
                                type="number"
                                step="0.01"
                                label="Ab Betrag (€)"
                            />
                            <flux:input
                                wire:model="regeln.{{ $idx }}.mindestAngebote"
                                type="number"
                                min="0"
                                label="Mindestanzahl Angebote"
                            />
                            <flux:switch
                                wire:model="regeln.{{ $idx }}.begruendungErlaubt"
                                label="Ausnahme-Begründung erlaubt"
                            />
                            <div class="flex items-end">
                                <flux:button
                                    type="button"
                                    variant="ghost"
                                    size="sm"
                                    icon="trash"
                                    wire:click="removeRegel({{ $idx }})"
                                >
                                    Entfernen
                                </flux:button>
                            </div>
                            <flux:textarea
                                wire:model="regeln.{{ $idx }}.hinweisText"
                                label="Hinweis-Text (optional)"
                                rows="2"
                                class="md:col-span-4"
                            />
                        </div>
                    </section>
                @endforeach
            </div>
        @endif

        <div class="flex justify-end">
            <flux:button variant="primary" icon="check" wire:click="speichern">Speichern</flux:button>
        </div>
    </div>
</div>
