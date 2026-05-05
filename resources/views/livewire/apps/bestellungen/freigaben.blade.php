<div>
    <x-intranet-app-bestellungen::bestellungen-layout heading="Freigaben" subheading="Bestellungen, die auf Ihre Entscheidung warten">
        <flux:card>
            @if ($bestellungen->isEmpty())
                <flux:text class="text-zinc-500">Aktuell sind keine Bestellungen für Sie zur Freigabe offen.</flux:text>
            @else
                <flux:table>
                    <flux:table.columns>
                        <flux:table.column>BEN</flux:table.column>
                        <flux:table.column>Besteller</flux:table.column>
                        <flux:table.column>Lieferant</flux:table.column>
                        <flux:table.column>Status</flux:table.column>
                        <flux:table.column class="text-right">Betrag</flux:table.column>
                        <flux:table.column></flux:table.column>
                    </flux:table.columns>
                    <flux:table.rows>
                        @foreach ($bestellungen as $bestellung)
                            <flux:table.row :key="$bestellung->id">
                                <flux:table.cell>{{ $bestellung->nummer }}</flux:table.cell>
                                <flux:table.cell>{{ optional($bestellung->user)->name }}</flux:table.cell>
                                <flux:table.cell>{{ $bestellung->lieferantenname }}</flux:table.cell>
                                <flux:table.cell>
                                    <flux:badge :color="$bestellung->status?->color()" size="sm">
                                        {{ $bestellung->status?->label() }}
                                    </flux:badge>
                                </flux:table.cell>
                                <flux:table.cell class="text-right">
                                    {{ number_format((float) $bestellung->gesamtbetrag, 2, ',', '.') }} €
                                </flux:table.cell>
                                <flux:table.cell>
                                    <flux:button
                                        size="xs"
                                        variant="primary"
                                        :href="route('apps.bestellungen.detail', ['bestellung' => $bestellung, 'aktion' => 'freigeben'])"
                                        wire:navigate
                                    >
                                        Prüfen
                                    </flux:button>
                                </flux:table.cell>
                            </flux:table.row>
                        @endforeach
                    </flux:table.rows>
                </flux:table>
            @endif
        </flux:card>
    </x-intranet-app-bestellungen::bestellungen-layout>
</div>
