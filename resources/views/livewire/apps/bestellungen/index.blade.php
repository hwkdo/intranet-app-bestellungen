@php
    $gruppen = \Hwkdo\IntranetAppBestellungen\Support\BestellungenNavigation::grouped();
    $canUseAi = \Hwkdo\IntranetAppBase\Support\AiUsage::allowed();
    $darf = function (array $item) use ($canUseAi): bool {
        if (isset($item['permission']) && ! auth()->user()?->can($item['permission'])) {
            return false;
        }

        return true;
    };
@endphp

<div>
    <x-intranet-app-bestellungen::bestellungen-layout
        heading="Bestellungen"
        subheading="Erfasse neue Bestellungen, verfolge offene Freigaben und verwalte den gesamten Bestellprozess bis zur Übergabe an D3."
    >
        <div class="space-y-4">
            <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
                <section class="glass-card px-5 py-4">
                    <p class="text-xs font-medium text-slate-600 dark:text-white/70">Meine Bestellungen</p>
                    <p class="mt-1 text-2xl font-semibold tabular-nums text-[#073070] dark:text-white">{{ $this->statistik['gesamt'] }}</p>
                </section>
                <section class="glass-card px-5 py-4">
                    <p class="text-xs font-medium text-slate-600 dark:text-white/70">In Freigabe</p>
                    <p class="mt-1 text-2xl font-semibold tabular-nums text-amber-700 dark:text-amber-300">{{ $this->statistik['offen'] }}</p>
                </section>
                <section class="glass-card px-5 py-4">
                    <p class="text-xs font-medium text-slate-600 dark:text-white/70">Bestellt</p>
                    <p class="mt-1 text-2xl font-semibold tabular-nums text-emerald-700 dark:text-emerald-300">{{ $this->statistik['bestellt'] }}</p>
                </section>
                <section class="glass-card px-5 py-4">
                    <p class="text-xs font-medium text-slate-600 dark:text-white/70">Abgelehnt</p>
                    <p class="mt-1 text-2xl font-semibold tabular-nums text-red-700 dark:text-red-300">{{ $this->statistik['abgelehnt'] }}</p>
                </section>
            </div>

            <div class="grid gap-4 lg:grid-cols-2">
                <x-intranet-app-bestellungen::hwk-card title="Zur Freigabe für mich" icon="check-badge" description="Bestellungen, die auf Ihre Entscheidung warten">
                    <x-slot:actions>
                        <a href="{{ route('apps.bestellungen.freigaben') }}" wire:navigate class="inline-flex items-center rounded-full bg-white/80 px-3 py-1 text-xs font-semibold text-[#073070] ring-1 ring-[#d0e3f9] transition hover:bg-[#073070] hover:text-white dark:bg-white/10 dark:text-white dark:ring-white/15 dark:hover:bg-white dark:hover:text-[#073070]">Alle anzeigen</a>
                    </x-slot:actions>

                    @if ($this->offeneFreigaben->isEmpty())
                        <x-intranet-app-bestellungen::hwk-empty bare icon="check-badge" title="Keine offenen Freigaben">
                            Aktuell sind keine Bestellungen für Sie zur Freigabe offen.
                        </x-intranet-app-bestellungen::hwk-empty>
                    @else
                        <flux:table>
                            <flux:table.columns>
                                <flux:table.column>BEN</flux:table.column>
                                <flux:table.column>Besteller</flux:table.column>
                                <flux:table.column class="text-right">Betrag</flux:table.column>
                                <flux:table.column></flux:table.column>
                            </flux:table.columns>
                            <flux:table.rows>
                                @foreach ($this->offeneFreigaben as $bestellung)
                                    <flux:table.row :key="$bestellung->id">
                                        <flux:table.cell>{{ $bestellung->nummer }}</flux:table.cell>
                                        <flux:table.cell>{{ optional($bestellung->user)->name }}</flux:table.cell>
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
                </x-intranet-app-bestellungen::hwk-card>

                <x-intranet-app-bestellungen::hwk-card title="Meine letzten Bestellungen" icon="document-text" description="Die zehn zuletzt angelegten Bestellungen">
                    <x-slot:actions>
                        <a href="{{ route('apps.bestellungen.meine') }}" wire:navigate class="inline-flex items-center rounded-full bg-white/80 px-3 py-1 text-xs font-semibold text-[#073070] ring-1 ring-[#d0e3f9] transition hover:bg-[#073070] hover:text-white dark:bg-white/10 dark:text-white dark:ring-white/15 dark:hover:bg-white dark:hover:text-[#073070]">Alle anzeigen</a>
                    </x-slot:actions>

                    @if ($this->meineBestellungen->isEmpty())
                        <x-intranet-app-bestellungen::hwk-empty bare icon="document-plus" title="Noch keine Bestellung">
                            Sie haben noch keine Bestellung erstellt.
                            <div class="mt-4">
                                <flux:button variant="primary" :href="route('apps.bestellungen.erstellen')" wire:navigate>
                                    Erste Bestellung anlegen
                                </flux:button>
                            </div>
                        </x-intranet-app-bestellungen::hwk-empty>
                    @else
                        <flux:table>
                            <flux:table.columns>
                                <flux:table.column>BEN</flux:table.column>
                                <flux:table.column>Lieferant</flux:table.column>
                                <flux:table.column>Status</flux:table.column>
                                <flux:table.column class="text-right">Betrag</flux:table.column>
                            </flux:table.columns>
                            <flux:table.rows>
                                @foreach ($this->meineBestellungen as $bestellung)
                                    <flux:table.row
                                        :key="$bestellung->id"
                                        class="cursor-pointer"
                                        wire:navigate
                                        :href="route('apps.bestellungen.detail', $bestellung)"
                                    >
                                        <flux:table.cell>{{ $bestellung->nummer }}</flux:table.cell>
                                        <flux:table.cell>{{ $bestellung->lieferantenname }}</flux:table.cell>
                                        <flux:table.cell>
                                            <flux:badge :color="$bestellung->status?->color()" size="sm">
                                                {{ $bestellung->status?->label() }}
                                            </flux:badge>
                                        </flux:table.cell>
                                        <flux:table.cell class="text-right">
                                            {{ number_format((float) $bestellung->gesamtbetrag, 2, ',', '.') }} €
                                        </flux:table.cell>
                                    </flux:table.row>
                                @endforeach
                            </flux:table.rows>
                        </flux:table>
                    @endif
                </x-intranet-app-bestellungen::hwk-card>
            </div>

            <div class="grid gap-4 md:grid-cols-2">
                @foreach ($gruppen['main'] as $item)
                    @continue(! $darf($item))
                    @if (! empty($item['requiresAiUsage']) && ! $canUseAi)
                        <section class="glass-card flex h-full flex-col p-5">
                            <div class="flex items-center gap-3">
                                <span class="grid size-10 shrink-0 place-items-center rounded-xl bg-[#073070]/10 text-[#073070] dark:bg-white/10 dark:text-white">
                                    <flux:icon :icon="$item['icon'] ?? 'document-text'" variant="micro" class="size-5" />
                                </span>
                                <div class="min-w-0">
                                    <h3 class="text-sm font-semibold text-[#073070] dark:text-white">{{ $item['label'] }}</h3>
                                    <p class="text-xs text-slate-600 dark:text-white/70">{{ $item['description'] ?? $item['label'] }}</p>
                                </div>
                            </div>
                            <x-intranet-app-base::ai-usage-locked-button :label="$item['buttonText'] ?? $item['label']" />
                        </section>
                    @else
                        <a href="{{ $item['href'] }}" wire:navigate class="glass-card flex h-full flex-col p-5 transition hover:ring-2 hover:ring-[#073070]/30">
                            <div class="flex items-center gap-3">
                                <span class="grid size-10 shrink-0 place-items-center rounded-xl bg-[#073070]/10 text-[#073070] dark:bg-white/10 dark:text-white">
                                    <flux:icon :icon="$item['icon'] ?? 'document-text'" variant="micro" class="size-5" />
                                </span>
                                <div class="min-w-0">
                                    <h3 class="text-sm font-semibold text-[#073070] dark:text-white">{{ $item['label'] }}</h3>
                                    <p class="text-xs text-slate-600 dark:text-white/70">{{ $item['description'] ?? $item['label'] }}</p>
                                </div>
                            </div>
                            <span class="mt-4 inline-flex items-center justify-center rounded-full bg-[#073070] px-3 py-1.5 text-sm font-semibold text-white shadow-md shadow-[#073070]/25">
                                {{ $item['buttonText'] ?? $item['label'] }}
                            </span>
                        </a>
                    @endif
                @endforeach
            </div>

            @if ($gruppen['settings'] !== [])
                <div class="grid gap-4 md:grid-cols-2">
                    @foreach ($gruppen['settings'] as $item)
                        @continue(! $darf($item))
                        <a href="{{ $item['href'] }}" wire:navigate class="glass-card flex h-full flex-col p-5 transition hover:ring-2 hover:ring-[#073070]/30">
                            <div class="flex items-center gap-3">
                                <span class="grid size-10 shrink-0 place-items-center rounded-xl bg-[#073070]/10 text-[#073070] dark:bg-white/10 dark:text-white">
                                    <flux:icon :icon="$item['icon'] ?? 'cog-6-tooth'" variant="micro" class="size-5" />
                                </span>
                                <div class="min-w-0">
                                    <h3 class="text-sm font-semibold text-[#073070] dark:text-white">{{ $item['label'] }}</h3>
                                    <p class="text-xs text-slate-600 dark:text-white/70">{{ $item['description'] ?? $item['label'] }}</p>
                                </div>
                            </div>
                            <span class="mt-4 inline-flex items-center justify-center rounded-full bg-white/80 px-3 py-1.5 text-sm font-semibold text-[#073070] ring-1 ring-[#d0e3f9] dark:bg-white/10 dark:text-white dark:ring-white/15">
                                {{ $item['buttonText'] ?? $item['label'] }}
                            </span>
                        </a>
                    @endforeach
                </div>
            @endif
        </div>
    </x-intranet-app-bestellungen::bestellungen-layout>
</div>
