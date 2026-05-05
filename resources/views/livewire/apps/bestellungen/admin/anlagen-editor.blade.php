<div>
    <div class="flex items-center justify-between mb-4">
        <flux:heading size="lg">Anlagen</flux:heading>
        <flux:button icon="plus" variant="primary" wire:click="neu">Neue Anlage</flux:button>
    </div>

    @if ($this->anlagen->isEmpty())
        <flux:text class="text-zinc-500">Keine Anlagen definiert.</flux:text>
    @else
        <flux:table>
            <flux:table.columns>
                <flux:table.column>Bezeichnung</flux:table.column>
                <flux:table.column>Art</flux:table.column>
                <flux:table.column>Aktiv</flux:table.column>
                <flux:table.column></flux:table.column>
            </flux:table.columns>
            <flux:table.rows>
                @foreach ($this->anlagen as $anlage)
                    <flux:table.row :key="$anlage->id">
                        <flux:table.cell>{{ $anlage->bezeichnung }}</flux:table.cell>
                        <flux:table.cell>{{ optional($anlage->art)->bezeichnung ?? '—' }}</flux:table.cell>
                        <flux:table.cell>
                            @if ($anlage->aktiv)
                                <flux:badge size="sm" color="emerald">aktiv</flux:badge>
                            @else
                                <flux:badge size="sm" color="zinc">inaktiv</flux:badge>
                            @endif
                        </flux:table.cell>
                        <flux:table.cell>
                            <flux:button size="xs" icon="pencil" wire:click="edit({{ $anlage->id }})">Bearbeiten</flux:button>
                            <flux:button size="xs" variant="ghost" icon="trash" wire:click="loeschen({{ $anlage->id }})" wire:confirm="Wirklich löschen?">Löschen</flux:button>
                        </flux:table.cell>
                    </flux:table.row>
                @endforeach
            </flux:table.rows>
        </flux:table>
    @endif

    <flux:modal name="anlage-modal" :show="false">
        <form wire:submit="speichern" class="space-y-3">
            <flux:heading size="lg">{{ $editId ? 'Anlage bearbeiten' : 'Neue Anlage' }}</flux:heading>
            <flux:input wire:model="bezeichnung" label="Bezeichnung" required />
            <flux:select wire:model="artId" label="Zugehörige Art (optional)">
                <flux:select.option value="">— keine Zuordnung —</flux:select.option>
                @foreach ($this->arten as $art)
                    <flux:select.option value="{{ $art->id }}">{{ $art->bezeichnung }}</flux:select.option>
                @endforeach
            </flux:select>
            <flux:textarea wire:model="beschreibung" label="Beschreibung" rows="3" />
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
