<div>
    <x-intranet-app-bestellungen::hwk-card title="Bestell-Arten" icon="rectangle-stack" description="Arten stehen beim Anlegen einer Bestellung zur Auswahl.">
        <x-slot:actions>
            <flux:button icon="plus" variant="primary" wire:click="neu">Neue Art</flux:button>
        </x-slot:actions>

        @if ($this->arten->isEmpty())
            <x-intranet-app-bestellungen::hwk-empty bare icon="rectangle-stack" title="Keine Arten">
                Keine Arten definiert.
            </x-intranet-app-bestellungen::hwk-empty>
    @else
        <flux:table>
            <flux:table.columns>
                <flux:table.column>Bezeichnung</flux:table.column>
                <flux:table.column>Icon</flux:table.column>
                <flux:table.column>Sortierung</flux:table.column>
                <flux:table.column>Aktiv</flux:table.column>
                <flux:table.column></flux:table.column>
            </flux:table.columns>
            <flux:table.rows>
                @foreach ($this->arten as $art)
                    <flux:table.row :key="$art->id">
                        <flux:table.cell>{{ $art->bezeichnung }}</flux:table.cell>
                        <flux:table.cell>{{ $art->icon }}</flux:table.cell>
                        <flux:table.cell>{{ $art->sortierung }}</flux:table.cell>
                        <flux:table.cell>
                            @if ($art->aktiv)
                                <flux:badge size="sm" color="emerald">aktiv</flux:badge>
                            @else
                                <flux:badge size="sm" color="zinc">inaktiv</flux:badge>
                            @endif
                        </flux:table.cell>
                        <flux:table.cell>
                            <flux:button size="xs" icon="pencil" wire:click="edit({{ $art->id }})">Bearbeiten</flux:button>
                            <flux:button size="xs" variant="ghost" icon="trash" wire:click="loeschen({{ $art->id }})" wire:confirm="Wirklich löschen?">Löschen</flux:button>
                        </flux:table.cell>
                    </flux:table.row>
                @endforeach
            </flux:table.rows>
        </flux:table>
    @endif
    </x-intranet-app-bestellungen::hwk-card>

    <flux:modal name="art-modal" :show="false">
        <form wire:submit="speichern" class="space-y-3">
            <flux:heading size="lg">{{ $editId ? 'Art bearbeiten' : 'Neue Art' }}</flux:heading>
            <flux:input wire:model="bezeichnung" label="Bezeichnung" required />
            <flux:input wire:model="icon" label="Heroicon-Name (optional)" placeholder="z. B. computer-desktop" />
            <flux:input wire:model="sortierung" type="number" label="Sortierung" />
            <flux:switch wire:model="aktiv" label="Aktiv" />
            <div class="flex justify-end gap-2">
                <flux:modal.close>
                    <flux:button type="button" variant="ghost">Abbrechen</flux:button>
                </flux:modal.close>
                <flux:button type="submit" variant="primary" icon="check">Speichern</flux:button>
            </div>
        </form>
    </flux:modal>
</div>
