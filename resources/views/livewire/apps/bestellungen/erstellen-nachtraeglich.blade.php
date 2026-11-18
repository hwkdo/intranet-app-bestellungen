<div>
    <x-intranet-app-bestellungen::bestellungen-layout
        heading="Nachträgliche Bestellung"
        subheading="Kassenbon hochladen, Angaben prüfen und abschließen"
    >
        <div class="mx-auto max-w-5xl space-y-6" @if ($this->analyseLaeuft) wire:poll.2s.visible="pruefeAnalyse" @endif>
            <flux:callout icon="exclamation-triangle" variant="warning">
                Nur für dringende Ausnahmefälle, zum Beispiel ein Ersatzteil, das sofort im Baumarkt gekauft werden musste. Es gibt keinen Bestellschein im Vorfeld und keine Freigabe. Laden Sie den Kassenbon hoch. Nach Ihrer Prüfung entstehen Bestellschein und Rechnung in D3.
            </flux:callout>

            @if ($kassenbonId === null)
                <section class="glass-card overflow-hidden p-5 space-y-4">
                    <flux:heading size="lg">Kassenbon</flux:heading>
                    <flux:file-upload wire:model="kassenbonDatei" accept=".pdf,.jpg,.jpeg,.png,.webp,application/pdf,image/jpeg,image/png,image/webp">
                        <flux:file-upload.dropzone
                            heading="Kassenbon ablegen oder auswählen"
                            text="PDF, JPG, PNG oder WebP. Die Auswertung startet direkt danach."
                            with-progress
                            inline
                        />
                    </flux:file-upload>
                    <div wire:loading.flex wire:target="kassenbonDatei,hochladen" class="items-center gap-2 text-sm text-zinc-600 dark:text-white/70">
                        <flux:icon.arrow-path class="size-4 animate-spin" />
                        Kassenbon wird hochgeladen, die Auswertung startet …
                    </div>
                    @if ($kassenbonDatei)
                        <flux:file-item
                            :heading="$kassenbonDatei->getClientOriginalName()"
                            :size="$kassenbonDatei->getSize()"
                        />
                    @endif
                    <flux:error name="kassenbonDatei" />
                </section>
            @elseif ($this->analyseLaeuft)
                <section class="glass-card overflow-hidden p-5 space-y-5">
                    <div class="flex items-start justify-between gap-4">
                        <div>
                            <flux:heading size="lg">Kassenbon wird ausgewertet</flux:heading>
                            @if ($extractionDateiname)
                                <flux:text class="mt-1">{{ $extractionDateiname }}</flux:text>
                            @endif
                        </div>
                        <p
                            wire:ignore
                            class="shrink-0 text-sm text-zinc-600 tabular-nums dark:text-white/70"
                            x-data="{
                                startedAt: {{ (int) $extractionGestartetAm }} * 1000,
                                seconds: 0,
                                init() {
                                    this.tick();
                                    this.timer = setInterval(() => this.tick(), 1000);
                                },
                                tick() {
                                    this.seconds = Math.max(0, Math.floor((Date.now() - this.startedAt) / 1000));
                                },
                                destroy() {
                                    clearInterval(this.timer);
                                },
                            }"
                            x-text="'Läuft seit ' + String(Math.floor(seconds / 60)).padStart(2, '0') + ':' + String(seconds % 60).padStart(2, '0')"
                        ></p>
                    </div>
                    <ol class="space-y-4">
                        @foreach ($this->analyseSchritte as $schritt)
                            <li wire:key="kb-schritt-{{ $schritt['key'] }}" class="flex gap-3">
                                <div class="mt-0.5">
                                    @if ($schritt['zustand'] === 'fertig')
                                        <flux:icon.check-circle class="size-5 text-emerald-600 dark:text-emerald-400" />
                                    @elseif ($schritt['zustand'] === 'aktiv')
                                        <flux:icon.arrow-path class="size-5 animate-spin" />
                                    @else
                                        <span class="mt-0.5 block size-5 rounded-full border border-zinc-300 dark:border-white/10"></span>
                                    @endif
                                </div>
                                <div>
                                    <p @class([
                                        'text-sm font-medium',
                                        'text-zinc-400 dark:text-zinc-500' => $schritt['zustand'] === 'offen',
                                    ])>{{ $schritt['label'] }}</p>
                                    @if ($schritt['zustand'] === 'aktiv')
                                        <flux:text class="mt-0.5 text-sm">{{ $schritt['hinweis'] }}</flux:text>
                                    @endif
                                </div>
                            </li>
                        @endforeach
                    </ol>
                    <flux:text class="text-sm">
                        Meist ein bis zwei Minuten. Die Seite prüft alle zwei Sekunden von selbst, ob der nächste Schritt fertig ist.
                    </flux:text>
                </section>
            @elseif ($extractionStatus === 'failed')
                <flux:callout icon="exclamation-circle" variant="danger">
                    {{ $extractionError ?: 'Die Auswertung ist fehlgeschlagen.' }}
                </flux:callout>
            @elseif (! $auszahlungBestaetigt)
                <section class="glass-card overflow-hidden p-5 mx-auto max-w-2xl space-y-6">
                    <div>
                        <flux:heading size="lg">Art der Aus-/Rückzahlung</flux:heading>
                        <flux:text class="mt-1">Wohin soll das Geld ausgezahlt werden?</flux:text>
                    </div>
                    <flux:radio.group wire:model.live="auszahlungsEmpfaenger" label="Empfänger" variant="cards">
                        <flux:radio value="mitarbeiter" label="An mich" description="Ich habe das Geld vorgestreckt und bekomme es zurück." />
                        <flux:radio value="lieferant" label="An den Lieferanten" description="Der Einkauf lief über das Firmen-Kundenkonto. Das Geld geht an den Lieferanten." />
                    </flux:radio.group>
                    <flux:error name="auszahlungsEmpfaenger" />
                    @if ($auszahlungsEmpfaenger === 'mitarbeiter')
                        <flux:radio.group wire:model.live="auszahlungsArt" label="Auszahlungsart" variant="cards">
                            <flux:radio value="ueberweisung" label="Überweisung" description="Das Geld geht auf mein Konto." />
                            <flux:radio value="bar" label="Barauszahlung" description="Ich hole das Geld an der Kasse ab." />
                        </flux:radio.group>
                        <flux:error name="auszahlungsArt" />
                    @elseif ($auszahlungsEmpfaenger === 'lieferant')
                        <flux:callout icon="information-circle">
                            An den Lieferanten wird immer per Überweisung gezahlt.
                        </flux:callout>
                    @endif
                    <div class="flex justify-end">
                        <flux:button type="button" variant="primary" wire:click="auszahlungUebernehmen">Weiter zur Prüfung</flux:button>
                    </div>
                </section>
            @else
                <div class="grid gap-6 lg:grid-cols-[minmax(0,16rem)_minmax(0,1fr)]">
                    <section class="glass-card overflow-hidden p-5 space-y-3">
                        <flux:heading size="lg">Beleg</flux:heading>
                        <flux:button
                            class="w-full"
                            variant="outline"
                            icon="document-text"
                            icon-trailing="arrow-top-right-on-square"
                            :href="route('apps.bestellungen.kassenbon.datei', $kassenbonId)"
                            target="_blank"
                        >
                            Kassenbon öffnen
                        </flux:button>
                    </section>

                    <form wire:submit="abschliessen" class="space-y-6">
                        <section class="glass-card overflow-hidden p-5 space-y-4">
                            <flux:heading size="lg">Prüfung</flux:heading>
                            <flux:text>Bitte alle erkannten Angaben prüfen. Fehlendes ergänzen, Falsches korrigieren.</flux:text>

                            <flux:field>
                                <flux:label>
                                    Lieferant
                                    @if ($this->feldErkannt('lieferant'))
                                        <flux:badge size="sm" color="lime" class="ml-1">aus Kassenbon erkannt</flux:badge>
                                    @elseif (filled($erkannterLieferantenname) && ! filled($lieferantennummer))
                                        <flux:badge size="sm" color="amber" class="ml-1">kein Treffer</flux:badge>
                                    @elseif (! filled($lieferantennummer))
                                        <flux:badge size="sm" color="amber" class="ml-1">bitte ergänzen</flux:badge>
                                    @endif
                                </flux:label>
                                <div class="flex items-end gap-2">
                                    <div class="min-w-0 flex-1">
                                        <flux:select variant="combobox" wire:model.live="lieferantennummer" :filter="false" clearable placeholder="Lieferant wählen…">
                                            <x-slot name="input">
                                                <flux:select.input wire:model.live.debounce.250ms="lieferantSearch" :invalid="$errors->has('lieferantennummer')" placeholder="Name oder Nummer eingeben…" />
                                            </x-slot>
                                            @foreach ($this->lieferantenSuggestions as $lieferant)
                                                <flux:select.option wire:key="kb-lf-{{ $lieferant->lieferantennummer }}" value="{{ $lieferant->lieferantennummer }}">
                                                    {{ $lieferant->lieferantenname }} ({{ $lieferant->lieferantennummer }})
                                                </flux:select.option>
                                            @endforeach
                                        </flux:select>
                                    </div>
                                    <flux:modal.trigger name="fehlender-lieferant">
                                        <flux:button variant="outline" type="button" class="shrink-0">Lieferant fehlt</flux:button>
                                    </flux:modal.trigger>
                                </div>
                                @if (filled($erkannterLieferantenname) && ! filled($lieferantennummer))
                                    <flux:text class="text-sm">
                                        „{{ $erkannterLieferantenname }}“ wurde gelesen, steht aber nicht in den Lieferanten. Bitte einen Eintrag wählen oder „Lieferant fehlt“ verwenden.
                                    </flux:text>
                                @endif
                                <flux:error name="lieferantennummer" />
                            </flux:field>

                            <div class="grid items-start gap-4 md:grid-cols-2">
                                <flux:input wire:model="belegnummer" label="Belegnummer" description:trailing="Darf 0 sein, wenn der Bon keine Nummer hat." />
                                <flux:input wire:model.live="belegdatum" type="date" label="Belegdatum" />
                                <flux:input wire:model="nettobetrag" label="Netto" />
                                <flux:input wire:model="steuerbetrag" label="MwSt" />
                                <flux:input wire:model="bruttobetrag" label="Brutto" />
                                <flux:input wire:model="zahlungsart" label="Zahlungsart" />
                                <flux:field>
                                    <flux:label>Kostenstelle</flux:label>
                                    <flux:select variant="combobox" wire:model.live="kostenstelle" :filter="false" clearable placeholder="Kostenstelle wählen…">
                                        <x-slot name="input">
                                            <flux:select.input wire:model.live.debounce.250ms="kostenstelleSearch" placeholder="Nummer oder Bezeichnung eingeben…" />
                                        </x-slot>
                                        @foreach ($this->kostenstellenSuggestions as $kst)
                                            <flux:select.option wire:key="kb-kst-{{ $kst->kostenstelle }}" value="{{ $kst->kostenstelle }}">
                                                {{ $kst->kostenstelle }} – {{ $kst->bezeichnung }}
                                            </flux:select.option>
                                        @endforeach
                                    </flux:select>
                                    <flux:error name="kostenstelle" />
                                </flux:field>
                                <flux:input wire:model="haushaltsjahr" type="number" label="Haushaltsjahr" />
                                <div class="md:col-span-2">
                                    <flux:input wire:model="betreff" label="Betreff" />
                                </div>
                                <div class="md:col-span-2">
                                    <div class="mb-2 flex items-center justify-between gap-3">
                                        <flux:text class="text-sm">Die Begründung ist aus der Auszahlung vorbereitet und kann angepasst werden.</flux:text>
                                        <flux:button type="button" size="sm" variant="ghost" wire:click="auszahlungAendern">Auszahlung ändern</flux:button>
                                    </div>
                                    <flux:textarea wire:model="begruendung" label="Begründung der nachträglichen Bestellung" rows="3" />
                                    <flux:error name="begruendung" />
                                </div>
                            </div>
                            <flux:error name="bruttobetrag" />
                        </section>

                        <section class="glass-card overflow-hidden p-5">
                            <div class="mb-4 flex items-center justify-between">
                                <flux:heading size="lg">
                                    Positionen
                                    @if ($this->feldErkannt('positionen'))
                                        <flux:badge size="sm" color="lime">aus Kassenbon erkannt</flux:badge>
                                    @endif
                                </flux:heading>
                                <flux:button type="button" size="sm" icon="plus" wire:click="addPosition">Position hinzufügen</flux:button>
                            </div>
                            <div class="overflow-x-auto">
                                <table class="w-full text-sm">
                                    <thead class="border-b border-[#d0e3f9] text-xs tracking-wide text-zinc-600 uppercase dark:border-white/10 dark:text-white/70">
                                        <tr>
                                            <th class="w-10 py-2 pr-2 text-left">Nr.</th>
                                            <th class="px-2 py-2 text-left">Bezeichnung</th>
                                            <th class="w-28 px-2 py-2 text-left">Art.-Nr.</th>
                                            <th class="w-24 px-2 py-2 text-right">Menge</th>
                                            <th class="w-20 px-2 py-2 text-left">Einh.</th>
                                            <th class="w-32 px-2 py-2 text-right">Einzelpreis (brutto)</th>
                                            <th class="w-28 px-2 py-2 text-right">Gesamt (brutto)</th>
                                            <th class="w-10 py-2 pl-2"></th>
                                        </tr>
                                    </thead>
                                    <tbody class="divide-y divide-[#d0e3f9] dark:divide-white/10">
                                        @foreach ($positionen as $index => $position)
                                            @php($zeilenSumme = (float) ($position['menge'] ?? 0) * (float) ($position['preis'] ?? 0))
                                            <tr wire:key="kb-pos-{{ $index }}" class="align-top">
                                                <td class="py-2 pr-2 text-zinc-600 dark:text-white/70">{{ $index + 1 }}</td>
                                                <td class="px-2 py-2">
                                                    <flux:input size="sm" wire:model.blur="positionen.{{ $index }}.bezeichnung" placeholder="Bezeichnung" />
                                                    <flux:error name="positionen.{{ $index }}.bezeichnung" />
                                                </td>
                                                <td class="px-2 py-2">
                                                    <flux:input size="sm" wire:model.blur="positionen.{{ $index }}.art_nr" placeholder="Art.-Nr." />
                                                    <flux:error name="positionen.{{ $index }}.art_nr" />
                                                </td>
                                                <td class="px-2 py-2">
                                                    <flux:input size="sm" wire:model.live.debounce.300ms="positionen.{{ $index }}.menge" type="number" step="0.01" class="text-right" />
                                                    <flux:error name="positionen.{{ $index }}.menge" />
                                                </td>
                                                <td class="px-2 py-2">
                                                    <flux:input size="sm" wire:model.blur="positionen.{{ $index }}.einheit" placeholder="Stk" />
                                                    <flux:error name="positionen.{{ $index }}.einheit" />
                                                </td>
                                                <td class="px-2 py-2">
                                                    <flux:input size="sm" wire:model.live.debounce.300ms="positionen.{{ $index }}.preis" type="number" step="0.01" class="text-right" />
                                                    <flux:error name="positionen.{{ $index }}.preis" />
                                                </td>
                                                <td class="px-2 py-2 text-right tabular-nums">
                                                    <div class="py-1.5">{{ number_format($zeilenSumme, 2, ',', '.') }} €</div>
                                                </td>
                                                <td class="py-2 pl-2 text-right">
                                                    @if (count($positionen) > 1)
                                                        <flux:button type="button" size="xs" variant="ghost" icon="trash" wire:click="removePosition({{ $index }})" />
                                                    @endif
                                                </td>
                                            </tr>
                                        @endforeach
                                    </tbody>
                                </table>
                            </div>
                            <div class="mt-4 flex justify-end">
                                <flux:heading size="md">
                                    Summe der Positionen (brutto):
                                    <span class="tabular-nums text-emerald-600 dark:text-emerald-400">
                                        {{ number_format($this->positionenSumme(), 2, ',', '.') }} €
                                    </span>
                                </flux:heading>
                            </div>
                            @if ($this->summenWeichenAb())
                                <flux:checkbox wire:model="summenAbweichungBestaetigt" label="Abweichung zwischen Positionen, Netto, Steuer und Brutto ist geprüft" />
                                <flux:error name="summenAbweichungBestaetigt" />
                            @endif
                        </section>

                        <section class="glass-card overflow-hidden p-5 space-y-4">
                            <flux:field>
                                <flux:label>D3-Gruppen</flux:label>
                                <flux:select wire:model.live="d3GruppenAuswahl" multiple variant="listbox">
                                    @foreach ($this->d3GruppenOptionenSortiert as $gruppe)
                                        <flux:select.option value="{{ $gruppe }}">{{ $gruppe }}</flux:select.option>
                                    @endforeach
                                </flux:select>
                                <flux:error name="d3GruppenAuswahl" />
                            </flux:field>
                            @if ($this->userHasProjekte)
                                <flux:select variant="listbox" wire:model="projektId" clearable placeholder="Kein Projekt" label="Projekt">
                                    @foreach ($this->projektSuggestions as $projektOption)
                                        <flux:select.option value="{{ $projektOption->id }}">{{ $projektOption->name }}</flux:select.option>
                                    @endforeach
                                </flux:select>
                            @endif
                        </section>

                        <div class="flex justify-end">
                            <flux:button type="submit" variant="primary" icon="paper-airplane" wire:loading.attr="disabled" wire:target="abschliessen">
                                Abschließen und an D3 senden
                            </flux:button>
                        </div>
                    </form>
                </div>
            @endif
        </div>
    </x-intranet-app-bestellungen::bestellungen-layout>

    <flux:modal name="fehlender-lieferant" class="max-w-lg">
        <flux:heading size="lg">Lieferant fehlt</flux:heading>
        <flux:text class="mt-1 mb-4">
            Die Buchhaltung wird informiert. Für Bestellschein und Rechnung wird die hinterlegte Nummer des unbekannten Lieferanten verwendet.
        </flux:text>
        <div class="space-y-4">
            <flux:input wire:model="fehlenderLieferantName" label="Name" required />
            <flux:error name="fehlenderLieferantName" />
            <flux:textarea wire:model="fehlenderLieferantAdresse" label="Adresse" rows="3" />
            <flux:input wire:model="fehlenderLieferantIban" label="IBAN" />
            <flux:input wire:model="fehlenderLieferantWebseite" label="Webseite" />
        </div>
        <div class="mt-6 flex justify-end gap-2">
            <flux:modal.close>
                <flux:button variant="ghost" type="button">Abbrechen</flux:button>
            </flux:modal.close>
            <flux:button variant="primary" type="button" wire:click="meldeFehlendenLieferant">Melden</flux:button>
        </div>
    </flux:modal>
</div>
