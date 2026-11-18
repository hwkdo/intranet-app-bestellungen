<div>
    <x-intranet-app-bestellungen::bestellungen-layout
        heading="Interne Bestellungen"
        subheading="Interne Bedarfsanforderungen, die Ihnen als Fachabteilung zugewiesen wurden"
    >
        @if ($bestellungen->isEmpty())
            <x-intranet-app-bestellungen::hwk-empty icon="building-office-2" title="Keine internen Bestellungen">
                Aktuell sind Ihnen keine internen Bestellungen zugewiesen.
            </x-intranet-app-bestellungen::hwk-empty>
        @else
            <x-intranet-app-bestellungen::hwk-card title="Zugewiesene Bestellungen" icon="building-office-2" description="Offene Bedarfsanforderungen können Sie direkt bearbeiten.">
                <flux:table>
                    <flux:table.columns>
                        <flux:table.column>BEN</flux:table.column>
                        <flux:table.column>Antragsteller</flux:table.column>
                        <flux:table.column>Betreff / Lieferant</flux:table.column>
                        <flux:table.column>Status</flux:table.column>
                        <flux:table.column class="text-right">Betrag</flux:table.column>
                        <flux:table.column></flux:table.column>
                    </flux:table.columns>
                    <flux:table.rows>
                        @foreach ($bestellungen as $bestellung)
                            <flux:table.row :key="$bestellung->id">
                                <flux:table.cell>{{ $bestellung->nummer }}</flux:table.cell>
                                <flux:table.cell>{{ optional($bestellung->user)->name }}</flux:table.cell>
                                <flux:table.cell>
                                    <span class="block">{{ $bestellung->betreff ?: '—' }}</span>
                                    <span class="text-xs text-slate-600 dark:text-white/70">{{ $bestellung->lieferantenname }}</span>
                                </flux:table.cell>
                                <flux:table.cell>
                                    <flux:badge :color="$bestellung->status?->color()" size="sm">
                                        {{ $bestellung->status?->label() }}
                                    </flux:badge>
                                </flux:table.cell>
                                <flux:table.cell class="text-right">
                                    {{ number_format((float) $bestellung->gesamtbetrag, 2, ',', '.') }} €
                                </flux:table.cell>
                                <flux:table.cell>
                                    @if ($bestellung->istInternBearbeitungOffen())
                                        <flux:button
                                            size="xs"
                                            variant="primary"
                                            :href="route('apps.bestellungen.detail', ['bestellung' => $bestellung, 'aktion' => 'bestellen'])"
                                            wire:navigate
                                        >
                                            Bearbeiten
                                        </flux:button>
                                    @else
                                        <flux:button
                                            size="xs"
                                            variant="ghost"
                                            :href="route('apps.bestellungen.detail', ['bestellung' => $bestellung])"
                                            wire:navigate
                                        >
                                            Öffnen
                                        </flux:button>
                                    @endif
                                </flux:table.cell>
                            </flux:table.row>
                        @endforeach
                    </flux:table.rows>
                </flux:table>
            </x-intranet-app-bestellungen::hwk-card>
        @endif
    </x-intranet-app-bestellungen::bestellungen-layout>
</div>
