@php
    $prefix = "stufen.{$stufenIdx}.freigabe{$freigabeNr}Regeln.{$regelIdx}";
    $removeFn = "removeFreigabe{$freigabeNr}Regel({$stufenIdx}, {$regelIdx})";
@endphp

<div class="flex flex-wrap items-end gap-2 rounded-lg bg-zinc-50 p-2 dark:bg-zinc-800" wire:key="regel-{{ $stufenIdx }}-{{ $freigabeNr }}-{{ $regelIdx }}">
    {{-- Typ --}}
    <flux:field class="min-w-32">
        <flux:label>Typ</flux:label>
        <flux:select wire:model.live="{{ $prefix }}.typ" size="sm">
            <flux:select.option value="if_attribute">Attribut-Bedingung</flux:select.option>
            <flux:select.option value="if_rolle">Rollen-Bedingung</flux:select.option>
            <flux:select.option value="default">Standard (default)</flux:select.option>
        </flux:select>
    </flux:field>

    {{-- Bedingung (nur für if_attribute / if_rolle) --}}
    @if (($regel['typ'] ?? 'default') === 'if_attribute')
        <flux:field class="min-w-40">
            <flux:label>Attribut</flux:label>
            <flux:select wire:model="{{ $prefix }}.bedingung" size="sm">
                <flux:select.option value="">– wählen –</flux:select.option>
                @foreach ($this->userAttributeOptions as $attr => $label)
                    <flux:select.option value="{{ $attr }}">{{ $attr }}</flux:select.option>
                @endforeach
            </flux:select>
        </flux:field>
    @elseif (($regel['typ'] ?? 'default') === 'if_rolle')
        <flux:field class="min-w-40">
            <flux:label>Rolle</flux:label>
            <flux:select wire:model="{{ $prefix }}.bedingung" size="sm">
                <flux:select.option value="">– wählen –</flux:select.option>
                @foreach ($this->rollenOptions as $rolle)
                    <flux:select.option value="{{ $rolle }}">{{ $rolle }}</flux:select.option>
                @endforeach
            </flux:select>
        </flux:field>
    @endif

    {{-- Kein Freigeber --}}
    <flux:field class="min-w-36">
        <flux:label>Ergebnis</flux:label>
        <flux:select wire:model.live="{{ $prefix }}.keinFreigeber" size="sm">
            <flux:select.option value="0">Freigeber auflösen</flux:select.option>
            <flux:select.option value="1">Kein Freigeber nötig</flux:select.option>
        </flux:select>
    </flux:field>

    {{-- Quelle-Typ + Quelle (nur wenn nicht keinFreigeber) --}}
    @if (! ($regel['keinFreigeber'] ?? false))
        <flux:field class="min-w-28">
            <flux:label>Quelle-Typ</flux:label>
            <flux:select wire:model.live="{{ $prefix }}.quelleTyp" size="sm">
                <flux:select.option value="single">Einzelperson</flux:select.option>
                <flux:select.option value="multi">Kollektion (Methode)</flux:select.option>
                <flux:select.option value="gruppe">GVP-Gruppe</flux:select.option>
            </flux:select>
        </flux:field>

        <flux:field class="min-w-48">
            <flux:label>Quelle</flux:label>
            <flux:select wire:model="{{ $prefix }}.quelle" size="sm">
                @foreach ($this->quelleOptions as $val => $label)
                    <flux:select.option value="{{ $val }}">{{ $label }}</flux:select.option>
                @endforeach
            </flux:select>
        </flux:field>

        {{-- exclude_attributes (nur für default) --}}
        @if (($regel['typ'] ?? 'default') === 'default')
            <flux:field class="min-w-40">
                <flux:label>Ausschließen wenn Attribut</flux:label>
                <flux:select wire:model="{{ $prefix }}.excludeAttribute" multiple size="sm" variant="listbox">
                    @foreach ($this->userAttributeOptions as $attr => $label)
                        <flux:select.option value="{{ $attr }}">{{ $attr }}</flux:select.option>
                    @endforeach
                </flux:select>
            </flux:field>
        @endif
    @endif

    {{-- Entfernen --}}
    <div class="flex items-end">
        <flux:button
            type="button"
            variant="ghost"
            size="sm"
            icon="x-mark"
            wire:click="{{ $removeFn }}"
        />
    </div>
</div>
