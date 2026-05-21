<div>
    <x-intranet-app-bestellungen::bestellungen-layout
        heading="Neue Bestellung"
        subheading="Welche Art von Bestellung möchten Sie anlegen?"
    >
        <div class="mx-auto max-w-3xl space-y-6">
            <flux:text class="text-zinc-600 dark:text-white/80">
                Die Auswahl legt fest, welche Felder im Formular erscheinen. Sie können die Art später nicht mehr wechseln.
            </flux:text>

            <div class="grid gap-4 sm:grid-cols-2">
                <a
                    href="{{ $this->internUrl() }}"
                    wire:navigate
                    class="group block rounded-xl focus:outline-none focus-visible:ring-2 focus-visible:ring-blue-500"
                >
                    <flux:card class="h-full text-zinc-800 dark:text-slate-100 transition group-hover:ring-2 group-hover:ring-blue-500/60 dark:group-hover:ring-blue-400/50">
                        <div class="flex flex-col gap-3 p-1">
                            <div class="flex items-center gap-3">
                                <flux:icon name="users" class="size-8 text-blue-600 dark:text-blue-400" />
                                <flux:heading size="lg" class="text-zinc-900 dark:text-white">Interne Bestellung</flux:heading>
                            </div>
                            <flux:text class="text-zinc-600 dark:text-white/70">
                                Bedarfsanforderung an eine Fachabteilung (z.&nbsp;B. IT). Schätzpreis erfassen; der tatsächliche Lieferant wird beim Abschluss hinterlegt.
                            </flux:text>
                            <flux:button variant="primary" icon="arrow-right" class="mt-auto w-full pointer-events-none">
                                Intern starten
                            </flux:button>
                        </div>
                    </flux:card>
                </a>

                <a
                    href="{{ $this->externUrl() }}"
                    wire:navigate
                    class="group block rounded-xl focus:outline-none focus-visible:ring-2 focus-visible:ring-emerald-500"
                >
                    <flux:card class="h-full text-zinc-800 dark:text-slate-100 transition group-hover:ring-2 group-hover:ring-emerald-500/60 dark:group-hover:ring-emerald-400/50">
                        <div class="flex flex-col gap-3 p-1">
                            <div class="flex items-center gap-3">
                                <flux:icon name="building-storefront" class="size-8 text-emerald-600 dark:text-emerald-400" />
                                <flux:heading size="lg" class="text-zinc-900 dark:text-white">Externe Bestellung</flux:heading>
                            </div>
                            <flux:text class="text-zinc-600 dark:text-white/70">
                                Bestellung bei einem externen Lieferanten mit fester Lieferantenauswahl aus dem Stammdatenstamm.
                            </flux:text>
                            <flux:button variant="primary" icon="arrow-right" class="mt-auto w-full pointer-events-none">
                                Extern starten
                            </flux:button>
                        </div>
                    </flux:card>
                </a>
            </div>

            <div class="flex justify-center">
                <flux:button variant="ghost" icon="arrow-left" :href="route('apps.bestellungen.index')" wire:navigate>
                    Zurück zur Übersicht
                </flux:button>
            </div>
        </div>
    </x-intranet-app-bestellungen::bestellungen-layout>
</div>
