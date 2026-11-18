@props([
    'icon' => 'inbox',
    'title',
    'bare' => false,
])

<div @class([
    'flex flex-col items-center gap-3 px-6 text-center',
    'glass-card py-14' => ! $bare,
    'py-10' => $bare,
])>
    <span class="grid size-12 place-items-center rounded-2xl bg-[#073070]/10 text-[#073070] dark:bg-white/10 dark:text-white">
        <flux:icon :icon="$icon" class="size-6" />
    </span>
    <p class="text-base font-semibold text-[#073070] dark:text-white">{{ $title }}</p>
    @if ($slot->isNotEmpty())
        <div class="max-w-md text-sm text-slate-600 dark:text-white/70">{{ $slot }}</div>
    @endif
</div>
