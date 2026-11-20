<section class="mb-4 rounded-xl bg-white/70 p-4 ring-1 ring-[#d0e3f9] dark:bg-white/5 dark:ring-white/10">
    <flux:heading size="lg">Unterschrift</flux:heading>
    @if ($bestellung->unterschrift_art === \Hwkdo\IntranetAppBestellungen\Enums\UnterschriftArt::Digital)
        <flux:text class="mt-1 text-slate-600 dark:text-white/70">
            Digital unterschrieben von {{ $bestellung->unterzeichner?->name ?? 'unbekannt' }}
            @if ($bestellung->unterschrieben_at)
                am {{ $bestellung->unterschrieben_at->timezone(config('app.timezone'))->format('d.m.Y H:i') }}
            @endif.
        </flux:text>
    @elseif ($bestellung->unterschrift_art === \Hwkdo\IntranetAppBestellungen\Enums\UnterschriftArt::Scan)
        <flux:text class="mt-1 text-slate-600 dark:text-white/70">
            Scan hochgeladen von {{ $bestellung->unterschriftScanVon?->name ?? 'unbekannt' }}
            @if ($bestellung->unterschrieben_at)
                am {{ $bestellung->unterschrieben_at->timezone(config('app.timezone'))->format('d.m.Y H:i') }}
            @endif.
            Unterzeichner: {{ $bestellung->unterzeichner?->name ?? 'unbekannt' }}.
        </flux:text>
    @else
        <flux:text class="mt-1 text-slate-600 dark:text-white/70">
            Noch nicht unterschrieben. Unterzeichner: {{ $bestellung->unterzeichner?->name ?? 'unbekannt' }}.
        </flux:text>
    @endif

    @if ($bestellung->unterschrift_art !== \Hwkdo\IntranetAppBestellungen\Enums\UnterschriftArt::Digital)
    <div class="mt-3 flex flex-wrap items-center gap-2">
        <flux:button
            size="sm"
            variant="ghost"
            icon="arrow-down-tray"
            :href="route('apps.bestellungen.pdf.download', ['bestellung' => $bestellung, 'typ' => 'auftragsvergabe'])"
        >
            Schein zum Unterschreiben herunterladen
        </flux:button>

        @if ($this->darfDigitalUnterschreiben())
            <flux:button size="sm" variant="primary" icon="pencil-square" wire:click="digitalUnterschreiben" wire:confirm="Auftragsschein jetzt mit Ihrer registrierten Unterschrift unterschreiben?">
                Jetzt digital unterschreiben
            </flux:button>
        @endif
    </div>
    @endif

    @if ($this->darfScanHochladen())
        <form wire:submit="unterschriftScanHochladen" class="mt-4 space-y-3">
            <flux:field>
                <flux:label>Unterschriebenen Scan hochladen</flux:label>
                <flux:input type="file" wire:model="unterschriftScan" accept="application/pdf" />
                <flux:error name="unterschriftScan" />
                <flux:text class="text-slate-600 dark:text-white/70">PDF, höchstens 20 MB. Der Scan ersetzt das generierte Anschreiben. Die Angebote bleiben angehängt.</flux:text>
            </flux:field>
            <div class="flex flex-wrap gap-2">
                <flux:button type="submit" size="sm" variant="primary" icon="arrow-up-tray">Scan speichern</flux:button>
                @if ($bestellung->unterschrift_art === \Hwkdo\IntranetAppBestellungen\Enums\UnterschriftArt::Scan)
                    <flux:button type="button" size="sm" variant="ghost" icon="x-mark" wire:click="unterschriftScanEntfernen" wire:confirm="Den hochgeladenen Scan entfernen?">
                        Scan entfernen
                    </flux:button>
                @endif
            </div>
        </form>
    @endif
</section>
