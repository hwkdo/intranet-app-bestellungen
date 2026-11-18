<?php

use Hwkdo\IntranetAppBestellungen\Dashboard\BestellungenDashboardWidgetProvider;
use Hwkdo\IntranetAppBestellungen\Models\Bestellung;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Livewire\Attributes\Computed;
use Livewire\Component;

new class extends Component
{
    public function mount(): void
    {
        $this->authorize('see-app-bestellungen');
    }

    private function itemLimit(): int
    {
        $counts = auth()->user()?->settings->dashboard->personalGrid?->widgetItemCounts ?? [];
        $widgetKey = BestellungenDashboardWidgetProvider::KEY_OFFENE_BESTELLUNGEN;

        $value = $counts['bestellungen.'.$widgetKey]
            ?? $counts[$widgetKey]
            ?? 5;

        return min(max((int) $value, 1), 30);
    }

    #[Computed]
    public function bestellungen(): Collection
    {
        return $this->offeneBestellungenQuery()
            ->with(['freigeber', 'internerEmpfaenger'])
            ->latest()
            ->limit($this->itemLimit())
            ->get();
    }

    #[Computed]
    public function hasMore(): bool
    {
        return $this->totalCount() > $this->itemLimit();
    }

    #[Computed]
    public function totalCount(): int
    {
        return $this->offeneBestellungenQuery()->count();
    }

    /**
     * @return Builder<Bestellung>
     */
    private function offeneBestellungenQuery(): Builder
    {
        $user = auth()->user();
        abort_unless($user !== null, 403);

        return Bestellung::query()
            ->wartendFuerAnforderer((int) $user->getKey());
    }
};
?>

@placeholder
    <section class="glass-card overflow-hidden p-5 h-full">
        <div class="mb-3 space-y-2">
            <flux:skeleton class="h-4 w-52" />
            <flux:skeleton class="h-3 w-72" />
        </div>
        <div class="space-y-2">
            <flux:skeleton class="h-14 w-full rounded-md" />
            <flux:skeleton class="h-14 w-full rounded-md" />
            <flux:skeleton class="h-14 w-full rounded-md" />
        </div>
    </section>
@endplaceholder

<x-intranet-app-base::dashboard.widget-card
    :title="'Bestellungen in Bearbeitung ('.$this->totalCount().')'"
    :description="'Aktueller Stand Ihrer angeforderten Bestellungen (max. '.$this->itemLimit().')'"
>
    @forelse($this->bestellungen as $bestellung)
        <a
            href="{{ route('apps.bestellungen.detail', $bestellung) }}"
            wire:key="bestellungen-offene-{{ $bestellung->id }}"
            wire:navigate
            class="group block cursor-pointer rounded-xl border border-[#d0e3f9] px-3 py-2 transition-colors duration-150 hover:bg-[#d0e3f9]/80 dark:border-white/10 dark:bg-white/10 dark:hover:bg-white/15"
        >
            <div class="flex items-start justify-between gap-3">
                <div class="min-w-0 font-medium group-hover:text-zinc-900 dark:group-hover:text-white">
                    {{ $bestellung->nummer }}
                    @if(filled($bestellung->betreff))
                        <span class="font-normal text-zinc-600 dark:text-white/80">– {{ $bestellung->betreff }}</span>
                    @endif
                </div>
                <flux:badge :color="$bestellung->status?->color()" size="sm" class="shrink-0">
                    {{ $bestellung->status?->label() }}
                </flux:badge>
            </div>
            <div class="text-xs text-zinc-500 dark:text-white">
                {{ $bestellung->wartehinweisFuerAnforderer() }}
            </div>
        </a>
    @empty
        <p class="text-sm text-slate-600 dark:text-white/70">Keine Bestellungen in Bearbeitung.</p>
    @endforelse

    @if($this->hasMore)
        <div class="pt-1">
            <flux:button variant="ghost" size="sm" :href="route('apps.bestellungen.meine')" wire:navigate>
                Weitere anzeigen
            </flux:button>
        </div>
    @endif
</x-intranet-app-base::dashboard.widget-card>
