<div>
    <x-intranet-app-bestellungen::bestellungen-layout
        heading="Neue Bestellung"
        subheading="Welche Art von Bestellung möchten Sie anlegen? Die Auswahl legt fest, welche Felder im Formular erscheinen."
    >
        <div class="space-y-4">
            <div class="grid gap-4 sm:grid-cols-2">
                <a href="{{ $this->internUrl() }}" wire:navigate class="glass-card flex h-full flex-col p-5 transition hover:ring-2 hover:ring-[#073070]/30 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-[#073070]">
                    <div class="flex items-center gap-3">
                        <span class="grid size-10 shrink-0 place-items-center rounded-xl bg-[#073070]/10 text-[#073070] dark:bg-white/10 dark:text-white">
                            <flux:icon name="users" variant="micro" class="size-5" />
                        </span>
                        <h2 class="text-sm font-semibold text-[#073070] dark:text-white">Interne Bestellung</h2>
                    </div>
                    <p class="mt-3 text-sm text-slate-600 dark:text-white/70">
                        Bedarfsanforderung an eine Fachabteilung (z.&nbsp;B. IT). Schätzpreis erfassen; der tatsächliche Lieferant wird beim Abschluss hinterlegt.
                    </p>
                    <span class="mt-4 inline-flex items-center justify-center rounded-full bg-[#073070] px-3 py-1.5 text-sm font-semibold text-white shadow-md shadow-[#073070]/25">Intern starten</span>
                </a>

                <a href="{{ $this->externUrl() }}" wire:navigate class="glass-card flex h-full flex-col p-5 transition hover:ring-2 hover:ring-[#073070]/30 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-[#073070]">
                    <div class="flex items-center gap-3">
                        <span class="grid size-10 shrink-0 place-items-center rounded-xl bg-[#073070]/10 text-[#073070] dark:bg-white/10 dark:text-white">
                            <flux:icon name="building-storefront" variant="micro" class="size-5" />
                        </span>
                        <h2 class="text-sm font-semibold text-[#073070] dark:text-white">Externe Bestellung</h2>
                    </div>
                    <p class="mt-3 text-sm text-slate-600 dark:text-white/70">
                        Bestellung bei einem externen Lieferanten mit fester Lieferantenauswahl aus dem Stammdatenstamm.
                    </p>
                    <span class="mt-4 inline-flex items-center justify-center rounded-full bg-[#073070] px-3 py-1.5 text-sm font-semibold text-white shadow-md shadow-[#073070]/25">Extern starten</span>
                </a>

                <a href="{{ $this->auftragsvergabeUrl() }}" wire:navigate class="glass-card flex h-full flex-col p-5 transition hover:ring-2 hover:ring-[#073070]/30 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-[#073070]">
                    <div class="flex items-center gap-3">
                        <span class="grid size-10 shrink-0 place-items-center rounded-xl bg-[#073070]/10 text-[#073070] dark:bg-white/10 dark:text-white">
                            <flux:icon name="document-check" variant="micro" class="size-5" />
                        </span>
                        <h2 class="text-sm font-semibold text-[#073070] dark:text-white">Auftragsvergabe mit Angebotsbezug</h2>
                    </div>
                    <p class="mt-3 text-sm text-slate-600 dark:text-white/70">
                        Zuerst Angebote verschiedener Anbieter sammeln. Der Auftragnehmer ergibt sich aus dem Angebot, das am Ende übernommen wird.
                    </p>
                    <span class="mt-4 inline-flex items-center justify-center rounded-full bg-[#073070] px-3 py-1.5 text-sm font-semibold text-white shadow-md shadow-[#073070]/25">Auftragsvergabe starten</span>
                </a>

                <a href="{{ $this->nachtraeglichUrl() }}" wire:navigate class="glass-card flex h-full flex-col p-5 transition hover:ring-2 hover:ring-[#073070]/30 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-[#073070]">
                    <div class="flex items-center gap-3">
                        <span class="grid size-10 shrink-0 place-items-center rounded-xl bg-[#073070]/10 text-[#073070] dark:bg-white/10 dark:text-white">
                            <flux:icon name="receipt-percent" variant="micro" class="size-5" />
                        </span>
                        <h2 class="text-sm font-semibold text-[#073070] dark:text-white">Nachträgliche Bestellung</h2>
                    </div>
                    <p class="mt-3 text-sm text-slate-600 dark:text-white/70">
                        Nur für dringende Ausnahmefälle, zum Beispiel ein Ersatzteil aus dem Baumarkt. Statt eines Bestellformulars laden Sie den Kassenbon hoch. Es gibt keine Freigabe. Bestellschein und Rechnung entstehen danach automatisch in D3.
                    </p>
                    <span class="mt-4 inline-flex items-center justify-center rounded-full bg-[#073070] px-3 py-1.5 text-sm font-semibold text-white shadow-md shadow-[#073070]/25">Kassenbon hochladen</span>
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
