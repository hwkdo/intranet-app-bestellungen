@if ($this->userHasProjekte)
    <flux:field>
        <flux:label>Projekt <flux:badge size="sm" color="zinc" class="ml-1">Optional</flux:badge></flux:label>
        <flux:select
            variant="listbox"
            wire:model="projektId"
            clearable
            placeholder="Kein Projekt"
        >
            @foreach ($this->projektSuggestions as $projektOption)
                <flux:select.option
                    wire:key="proj-{{ $projektOption->id }}"
                    value="{{ $projektOption->id }}"
                >
                    {{ $projektOption->name }}
                </flux:select.option>
            @endforeach
        </flux:select>
    </flux:field>
@endif
