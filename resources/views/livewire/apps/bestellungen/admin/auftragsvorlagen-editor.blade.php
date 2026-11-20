<div>
    <x-intranet-app-bestellungen::hwk-card title="Auftragsvorlagen" icon="document-text" description="Brieftexte für den Auftragsschein. Das Layout bleibt gleich, der Mittelteil kommt aus der Vorlage.">
        <x-slot:actions>
            <flux:button icon="plus" variant="primary" wire:click="neu">Neue Vorlage</flux:button>
        </x-slot:actions>

        @if ($this->vorlagen->isEmpty())
            <x-intranet-app-bestellungen::hwk-empty bare icon="document-text" title="Keine Vorlagen">
                Es ist noch keine Auftragsvorlage angelegt.
            </x-intranet-app-bestellungen::hwk-empty>
        @else
            <flux:table>
                <flux:table.columns>
                    <flux:table.column>Name</flux:table.column>
                    <flux:table.column>Variablen</flux:table.column>
                    <flux:table.column>Aktiv</flux:table.column>
                    <flux:table.column></flux:table.column>
                </flux:table.columns>
                <flux:table.rows>
                    @foreach ($this->vorlagen as $vorlage)
                        <flux:table.row :key="$vorlage->id">
                            <flux:table.cell>
                                <div class="font-medium">{{ $vorlage->name }}</div>
                                <div class="text-xs text-zinc-500">{{ $vorlage->schluessel }}</div>
                            </flux:table.cell>
                            <flux:table.cell>
                                {{ collect($vorlage->variablenEnums())->map(fn ($variable) => $variable->label())->implode(', ') ?: '—' }}
                            </flux:table.cell>
                            <flux:table.cell>
                                @if ($vorlage->aktiv)
                                    <flux:badge size="sm" color="emerald">aktiv</flux:badge>
                                @else
                                    <flux:badge size="sm" color="zinc">inaktiv</flux:badge>
                                @endif
                            </flux:table.cell>
                            <flux:table.cell>
                                <flux:button size="xs" icon="pencil" wire:click="edit({{ $vorlage->id }})">Bearbeiten</flux:button>
                                <flux:button size="xs" variant="ghost" icon="trash" wire:click="loeschen({{ $vorlage->id }})" wire:confirm="Vorlage löschen? Wird sie noch verwendet, wird sie nur deaktiviert.">Löschen</flux:button>
                            </flux:table.cell>
                        </flux:table.row>
                    @endforeach
                </flux:table.rows>
            </flux:table>
        @endif
    </x-intranet-app-bestellungen::hwk-card>

    <flux:modal name="auftragsvorlage-modal" class="max-w-3xl" :show="false">
        <form wire:submit="speichern" class="space-y-4">
            <flux:heading size="lg">{{ $editId ? 'Vorlage bearbeiten' : 'Neue Vorlage' }}</flux:heading>

            <div class="grid gap-3 md:grid-cols-2">
                <flux:input wire:model="name" label="Name" required />
                <flux:input wire:model="schluessel" label="Schlüssel" description="Kleinbuchstaben und Bindestriche, z. B. hausverwaltung." required />
            </div>
            <flux:input wire:model="beschreibung" label="Beschreibung" />

            <div>
                <flux:label>Variablen</flux:label>
                <div class="mt-2 flex flex-wrap gap-4">
                    @foreach ($this->variablenKatalog as $variable)
                        <flux:checkbox wire:model.live="variablen" value="{{ $variable->value }}" label="{{ $variable->label() }}" />
                    @endforeach
                </div>
                <flux:error name="variablen" />
            </div>

            <flux:switch wire:model="anredeAutomatisch" label="Anrede automatisch erzeugen" description="Setzt „Sehr geehrte …“ über den Brieftext. Aus, wenn der Text die Anrede selbst enthält." />

            <flux:textarea wire:model.live.debounce.400ms="inhalt" label="Brieftext" rows="12" description="Markdown. Systemplatzhalter: {anrede}, {betreff}, {angebotswert}, {angebot_bezug}. Dazu die Platzhalter der gewählten Variablen, z. B. {frist} oder {freitext}." />
            <flux:error name="inhalt" />

            <div class="grid gap-3 md:grid-cols-2">
                <flux:input wire:model="sort" type="number" label="Sortierung" />
                <div class="flex items-end">
                    <flux:switch wire:model="aktiv" label="Aktiv" />
                </div>
            </div>

            <div class="rounded-lg border border-[#d0e3f9] p-4 text-sm dark:border-white/10">
                <flux:heading size="sm">Vorschau mit Beispieldaten</flux:heading>
                <div class="prose prose-sm mt-3 max-w-none dark:prose-invert">
                    {!! $this->vorschauHtml() !!}
                </div>
            </div>

            <div class="flex justify-end gap-2">
                <flux:modal.close>
                    <flux:button type="button" variant="ghost">Abbrechen</flux:button>
                </flux:modal.close>
                <flux:button type="submit" variant="primary" icon="check">Speichern</flux:button>
            </div>
        </form>
    </flux:modal>
</div>
