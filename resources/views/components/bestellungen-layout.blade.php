@props([
    'heading' => '',
    'subheading' => '',
    'navItems' => [],
])

@php
    $navItems = ! empty($navItems) ? $navItems : \Hwkdo\IntranetAppBestellungen\Support\BestellungenNavigation::items();
    $customBgUrl = \Hwkdo\IntranetAppBase\Models\AppBackground::getCustomBackgroundUrl('bestellungen');
@endphp

@if ($customBgUrl)
    @push('app-styles')
        <style data-app-bg data-ts="{{ uniqid() }}">
            :root {
                --app-bg-image: url('{{ $customBgUrl }}');
            }
        </style>
    @endpush
@endif

<div class="hwk-bestellungen space-y-4">
    <style>
        .hwk-bestellungen {
            --color-accent: #073070;
            --color-accent-content: #073070;
            --color-accent-foreground: #ffffff;
        }

        .dark .hwk-bestellungen {
            --color-accent: #d0e3f9;
            --color-accent-content: #d0e3f9;
            --color-accent-foreground: #04214e;
        }

        .hwk-bestellungen ui-tabs.hwk-pills {
            height: auto !important;
            flex-wrap: wrap;
            gap: 0.375rem;
            border-bottom-width: 0 !important;
        }

        .hwk-bestellungen ui-tabs.hwk-pills [data-flux-tab] {
            height: auto;
            margin-bottom: 0 !important;
            border-bottom-width: 0 !important;
            border-radius: 9999px;
            padding: 0.375rem 0.75rem;
            background: rgb(255 255 255 / 0.8) !important;
            color: #073070 !important;
            box-shadow: inset 0 0 0 1px #d0e3f9;
        }

        .dark .hwk-bestellungen ui-tabs.hwk-pills [data-flux-tab] {
            background: rgb(255 255 255 / 0.1) !important;
            color: #fff !important;
            box-shadow: inset 0 0 0 1px rgb(255 255 255 / 0.15);
        }

        .hwk-bestellungen ui-tabs.hwk-pills [data-flux-tab][data-selected],
        .dark .hwk-bestellungen ui-tabs.hwk-pills [data-flux-tab][data-selected] {
            background: #073070 !important;
            color: #fff !important;
            box-shadow: 0 4px 6px rgb(7 48 112 / 0.25);
        }

        .hwk-bestellungen .hwk-rows > div {
            display: flex;
            flex-direction: column;
            gap: 0.25rem;
            padding-block: 0.75rem;
        }

        @media (min-width: 640px) {
            .hwk-bestellungen .hwk-rows > div {
                flex-direction: row;
                align-items: center;
                justify-content: space-between;
                gap: 2rem;
            }

            .hwk-bestellungen .hwk-rows > div > dd {
                text-align: right;
            }
        }

        .hwk-bestellungen .hwk-rows dt {
            font-size: 0.875rem;
            font-weight: 500;
            color: #073070;
        }

        .dark .hwk-bestellungen .hwk-rows dt {
            color: #fff;
        }

        .hwk-bestellungen .hwk-rows dd {
            font-size: 0.875rem;
            color: var(--color-slate-600);
        }

        .dark .hwk-bestellungen .hwk-rows dd {
            color: rgb(255 255 255 / 0.7);
        }
    </style>

    <x-intranet-app-bestellungen::hwk-hero
        eyebrow="Bestellungen"
        :title="$heading !== '' ? $heading : 'Bestellungen'"
        :description="$subheading !== '' ? $subheading : null"
    >
        @isset($actions)
            <x-slot:actions>{{ $actions }}</x-slot:actions>
        @endisset
    </x-intranet-app-bestellungen::hwk-hero>

    @php
        $canUseAi = \Hwkdo\IntranetAppBase\Support\AiUsage::allowed();
        $currentPath = trim(request()->path(), '/');
    @endphp

    <div class="flex items-start gap-6 max-lg:flex-col">
        <nav
            aria-label="Bestellungen"
            class="glass-card w-full shrink-0 p-2 lg:sticky lg:top-4 lg:w-64"
            style="--glass-opacity: 0.92"
        >
            <ul class="space-y-0.5">
                @foreach ($navItems as $navItem)
                    @if (($navItem['type'] ?? '') === 'separator')
                        @if (! isset($navItem['permission']) || auth()->user()?->can($navItem['permission']))
                            <li class="px-3 pt-3 pb-1 text-xs font-semibold tracking-[0.2em] text-[#073070]/70 uppercase dark:text-white/60">{{ $navItem['label'] }}</li>
                        @endif
                    @elseif (! isset($navItem['permission']) || auth()->user()?->can($navItem['permission']))
                        <li>
                            @if (! empty($navItem['requiresAiUsage']) && ! $canUseAi)
                                <x-intranet-app-base::ai-usage-locked-nav-item :label="$navItem['label']" />
                            @else
                                @php
                                    $itemPath = trim((string) parse_url((string) ($navItem['href'] ?? ''), PHP_URL_PATH), '/');
                                    $current = $itemPath !== '' && (
                                        $currentPath === $itemPath
                                        || ($itemPath !== 'apps/bestellungen' && str_starts_with($currentPath, $itemPath.'/'))
                                    );
                                @endphp
                                <a
                                    href="{{ $navItem['href'] }}"
                                    wire:navigate
                                    @class([
                                        'flex items-center gap-2.5 rounded-xl px-3 py-2 text-sm font-medium transition focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-[#073070]',
                                        'bg-[#073070] text-white shadow-md shadow-[#073070]/25' => $current,
                                        'text-[#073070] hover:bg-[#d0e3f9]/80 dark:text-white dark:hover:bg-white/10' => ! $current,
                                    ])
                                    @if ($current) aria-current="page" @endif
                                >
                                    @if (! empty($navItem['icon']))
                                        <flux:icon :icon="$navItem['icon']" variant="micro" class="size-4 shrink-0" />
                                    @endif
                                    <span class="truncate">{{ $navItem['label'] }}</span>
                                    @if (isset($navItem['badge']) && $navItem['badge'] !== null && $navItem['badge'] !== '')
                                        <span class="ml-auto inline-flex rounded-full bg-red-600 px-1.5 text-xs font-semibold text-white">{{ $navItem['badge'] }}</span>
                                    @endif
                                </a>
                            @endif
                        </li>
                    @endif
                @endforeach
            </ul>
        </nav>

        <div class="min-w-0 flex-1 space-y-4">
            {{ $slot }}
        </div>
    </div>
</div>

@push('nav-items')
    @json($navItems)
@endpush
