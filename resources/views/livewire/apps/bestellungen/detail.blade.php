<div>
    <x-intranet-app-bestellungen::bestellungen-layout
        :heading="'Bestellung ' . $bestellung->nummer"
        :subheading="$bestellung->betreff ?? 'Detail'"
    >
        <div
            class="grid gap-6"
            x-data="{ stammdatenOffen: false }"
            :class="stammdatenOffen ? 'lg:grid-cols-3' : 'grid-cols-1'"
        >
            <div class="min-w-0" :class="stammdatenOffen ? 'lg:col-span-2' : ''">
            <section class="glass-card overflow-hidden p-5">
                <div class="flex flex-wrap items-center justify-between gap-3 mb-4">
                    <div class="flex items-center gap-3">
                        <flux:badge :color="$bestellung->status?->color()">
                            {{ $bestellung->status?->label() }}
                        </flux:badge>
                        <flux:text class="text-slate-600 dark:text-white/70">{{ $bestellung->nummer }}</flux:text>
                        @if ($bestellung->istInD3())
                            @if ($this->d3OneUrl())
                                <a
                                    href="{{ $this->d3OneUrl() }}"
                                    target="_blank"
                                    rel="noopener noreferrer"
                                    class="inline-flex"
                                >
                                    <flux:badge color="zinc" icon="cloud">in D3</flux:badge>
                                </a>
                            @else
                                <flux:badge color="zinc" icon="cloud">in D3</flux:badge>
                            @endif
                        @endif
                    </div>
                    <div class="flex flex-wrap gap-2">
                        <flux:dropdown>
                            <flux:button size="sm" variant="ghost" icon="document-text" icon-trailing="chevron-down">
                                Bestellschein
                            </flux:button>
                            <flux:menu>
                                @foreach ($this->bestellscheinPdfMenue as $variante)
                                    <flux:menu.item
                                        :href="$variante['inline_url']"
                                        target="_blank"
                                        icon="document-text"
                                    >
                                        {{ $variante['label'] }}
                                    </flux:menu.item>
                                @endforeach
                            </flux:menu>
                        </flux:dropdown>
                        @if ($this->kannLoeschen())
                            <flux:button
                                size="sm"
                                variant="danger"
                                icon="trash"
                                wire:click="loeschen"
                                wire:confirm="Möchten Sie diesen Entwurf wirklich löschen? Die Bestellung wird aus Ihren Listen entfernt."
                            >
                                Löschen
                            </flux:button>
                        @endif
                        <flux:button size="sm" variant="ghost" icon="document-duplicate" wire:click="wiederholen">
                            Wiederholen
                        </flux:button>
                        @if ($this->kannEinreichen())
                            <flux:button size="sm" variant="primary" icon="paper-airplane" wire:click="einreichenModalOeffnen">
                                Zur Freigabe einreichen
                            </flux:button>
                        @elseif (! $bestellung->istNachtraeglich() && $bestellung->status === \Hwkdo\IntranetAppBestellungen\Enums\BestellungStatus::Entwurf && $bestellung->user_id === auth()->id())
                            <flux:tooltip :content="$this->einreichenGesperrtHinweis()">
                                <flux:button size="sm" variant="primary" icon="paper-airplane" disabled>
                                    Zur Freigabe einreichen
                                </flux:button>
                            </flux:tooltip>
                        @endif
                        @if ($this->kannFreigeben())
                            <flux:button size="sm" variant="primary" icon="check" x-on:click="$flux.modal('freigeben-modal').show()">
                                Freigeben
                            </flux:button>
                            <flux:button size="sm" variant="danger" icon="x-mark" x-on:click="$flux.modal('ablehnen-modal').show()">
                                Ablehnen
                            </flux:button>
                            <flux:button size="sm" variant="ghost" icon="arrow-right" x-on:click="$flux.modal('weiterleiten-modal').show()">
                                Weiterleiten
                            </flux:button>
                        @endif
                        @if ($this->kannBestellen())
                            @if ($bestellung->istAuftragsvergabe() && ! $bestellung->istUnterschrieben())
                                <flux:tooltip content="Der Auftragsschein muss vor dem Bestellen unterschrieben werden.">
                                    <flux:button size="sm" variant="primary" icon="paper-airplane" disabled>
                                        Bestellen
                                    </flux:button>
                                </flux:tooltip>
                            @elseif ($bestellung->istIntern())
                                <flux:button size="sm" variant="primary" icon="paper-airplane" wire:click="bestellen">
                                    Bestellen
                                </flux:button>
                            @else
                                <flux:button size="sm" variant="primary" icon="paper-airplane" wire:click="bestellen" wire:confirm="Bestellung an D3 senden?">
                                    Bestellen
                                </flux:button>
                            @endif
                        @endif
                    </div>
                </div>

                @if ($bestellung->istAuftragsvergabe() && $bestellung->status === \Hwkdo\IntranetAppBestellungen\Enums\BestellungStatus::Freigegeben)
                    @include('intranet-app-bestellungen::livewire.apps.bestellungen.partials.unterschrift')
                @endif

                @if ($bestellung->kassenbon)
                    <section class="glass-card overflow-hidden p-5 mb-4 space-y-3">
                        <flux:heading size="lg">Kassenbon</flux:heading>
                        <flux:text>
                            Beleg {{ $bestellung->kassenbon->belegnummer ?: '0' }}
                            @if ($bestellung->kassenbon->belegdatum)
                                vom {{ $bestellung->kassenbon->belegdatum->format('d.m.Y') }}
                            @endif
                            @if ($bestellung->kassenbon->bruttobetrag !== null)
                                · {{ number_format((float) $bestellung->kassenbon->bruttobetrag, 2, ',', '.') }} €
                            @endif
                        </flux:text>
                        <div class="flex flex-wrap gap-2">
                            <flux:button size="sm" variant="ghost" icon="document" :href="route('apps.bestellungen.kassenbon.datei', $bestellung->kassenbon)" target="_blank">
                                Beleg öffnen
                            </flux:button>
                            @if ($bestellung->status === \Hwkdo\IntranetAppBestellungen\Enums\BestellungStatus::Entwurf)
                                <flux:button size="sm" variant="primary" icon="arrow-path" wire:click="kassenbonD3Erneut">
                                    D3-Übertragung erneut versuchen
                                </flux:button>
                            @endif
                        </div>
                    </section>
                @endif

                <button
                    type="button"
                    x-show="! stammdatenOffen"
                    x-cloak
                    x-on:click="stammdatenOffen = true"
                    class="mb-4 flex w-full items-center gap-3 rounded-xl border border-[#d0e3f9] bg-white px-3 py-2 text-start transition hover:bg-[#d0e3f9]/40 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-[#073070] dark:border-white/10 dark:bg-white/5 dark:hover:bg-white/10"
                >
                    <flux:icon.identification variant="mini" class="shrink-0 text-[#073070] dark:text-white" />
                    <span class="shrink-0 font-medium text-[#073070] dark:text-white">Stammdaten</span>
                    <span class="min-w-0 flex-1 truncate text-sm text-slate-600 dark:text-white/70">
                        @if ($bestellung->istIntern() && $bestellung->internerEmpfaenger)
                            {{ $bestellung->internerEmpfaenger->vorname }} {{ $bestellung->internerEmpfaenger->nachname }}
                        @elseif ($bestellung->istAuftragsvergabe() && ! filled($bestellung->lieferantenname))
                            Auftragnehmer offen
                        @else
                            {{ $bestellung->lieferantenname ?: '—' }}
                        @endif
                        · {{ number_format((float) $bestellung->gesamtbetrag, 2, ',', '.') }} €
                        @if (filled($bestellung->kostenstelle))
                            · {{ $bestellung->kostenstelle }}
                        @endif
                    </span>
                    <span class="inline-flex shrink-0 items-center gap-1 text-sm font-medium text-[#073070] dark:text-white">
                        Anzeigen
                        <flux:icon.chevron-right variant="mini" />
                    </span>
                </button>

                <flux:tab.group>
                    <div class="mb-4">
                    <flux:tabs variant="pills" class="hwk-pills" wire:model.live="activeTab">
                        @if ($bestellung->istAuftragsvergabe())
                            <flux:tab name="auftrag" icon="document-check">Auftragszeilen</flux:tab>
                        @else
                            <flux:tab name="positionen" icon="list-bullet">Positionen</flux:tab>
                        @endif
                        <flux:tab name="angebote" icon="document-text">{{ $bestellung->istAuftragsvergabe() ? 'Angebote' : 'Angebote / Begründung' }}</flux:tab>
                        <flux:tab name="d3" icon="cloud">D3-Dokumente</flux:tab>
                        <flux:tab name="notizen" icon="chat-bubble-left-ellipsis">Notizen</flux:tab>
                        <flux:tab name="verlauf" icon="clock">Verlauf</flux:tab>
                    </flux:tabs>
                    </div>

                    <flux:tab.panel name="positionen">
                        <div class="mb-3 flex justify-end">
                            @if ($this->kannBearbeiten() && ! $this->positionenBearbeiten)
                                <flux:button size="sm" icon="pencil-square" wire:click="positionenBearbeitenStarten">
                                    Positionen bearbeiten
                                </flux:button>
                            @endif
                        </div>

                        @if ($this->positionenBearbeiten)
                            <div class="space-y-3">
                                @foreach ($positionenDraft as $idx => $position)
                                    <div wire:key="pos-draft-{{ $idx }}" class="grid gap-3 md:grid-cols-12 items-end rounded border border-[#d0e3f9] p-3 dark:border-white/10">
                                        <div class="md:col-span-4">
                                            <flux:input wire:model.blur="positionenDraft.{{ $idx }}.bezeichnung" label="Bezeichnung" />
                                            <flux:error name="positionenDraft.{{ $idx }}.bezeichnung" />
                                        </div>
                                        <div class="md:col-span-2">
                                            <flux:input wire:model.blur="positionenDraft.{{ $idx }}.art_nr" label="Art.-Nr." />
                                            <flux:error name="positionenDraft.{{ $idx }}.art_nr" />
                                        </div>
                                        <div class="md:col-span-2">
                                            <flux:input wire:model.live.debounce.300ms="positionenDraft.{{ $idx }}.menge" type="number" step="0.01" label="Menge" />
                                            <flux:error name="positionenDraft.{{ $idx }}.menge" />
                                        </div>
                                        <div class="md:col-span-1">
                                            <flux:input wire:model.blur="positionenDraft.{{ $idx }}.einheit" label="Einheit" />
                                            <flux:error name="positionenDraft.{{ $idx }}.einheit" />
                                        </div>
                                        <div class="md:col-span-2">
                                            <flux:input wire:model.live.debounce.300ms="positionenDraft.{{ $idx }}.preis" type="number" step="0.01" label="Einzelpreis" />
                                            <flux:error name="positionenDraft.{{ $idx }}.preis" />
                                        </div>
                                        <div class="md:col-span-1">
                                            <flux:checkbox wire:model.live="positionenDraft.{{ $idx }}.pdf_position" label="PDF" />
                                        </div>
                                        @if ($position['pdf_position'] ?? false)
                                            <div class="md:col-span-11">
                                                <input
                                                    type="file"
                                                    wire:model="positionenDraftPdfs.{{ $idx }}"
                                                    accept="application/pdf,.pdf"
                                                    class="block w-full text-xs text-zinc-600 dark:text-zinc-300 file:mr-2 file:py-1 file:px-2 file:rounded file:border-0 file:bg-zinc-100 file:text-xs hover:file:bg-zinc-200 dark:file:bg-zinc-700 dark:hover:file:bg-zinc-600"
                                                />
                                                <flux:error name="positionenDraftPdfs.{{ $idx }}" />
                                            </div>
                                        @endif
                                        <div class="md:col-span-1 flex justify-end">
                                            <flux:button type="button" size="xs" variant="ghost" icon="trash" wire:click="positionDraftEntfernen({{ $idx }})" />
                                        </div>
                                    </div>
                                @endforeach
                            </div>

                            <div class="mt-3 flex items-center justify-between">
                                <flux:button type="button" size="sm" icon="plus" wire:click="positionDraftHinzufuegen">
                                    Position hinzufügen
                                </flux:button>
                                <div class="flex gap-2">
                                    <flux:button type="button" variant="ghost" wire:click="positionenBearbeitenAbbrechen">Abbrechen</flux:button>
                                    <flux:button type="button" variant="primary" icon="check" wire:click="positionenSpeichern">Speichern</flux:button>
                                </div>
                            </div>
                        @else
                            <flux:table>
                                <flux:table.columns>
                                    <flux:table.column>Nr.</flux:table.column>
                                    <flux:table.column>Bezeichnung</flux:table.column>
                                    <flux:table.column class="text-right">Menge</flux:table.column>
                                    <flux:table.column>Einheit</flux:table.column>
                                    <flux:table.column class="text-right">Einzelpreis</flux:table.column>
                                    <flux:table.column class="text-right">Gesamt</flux:table.column>
                                    <flux:table.column>PDF</flux:table.column>
                                </flux:table.columns>
                                <flux:table.rows>
                                    @foreach ($bestellung->positionen as $pos)
                                        <flux:table.row :key="$pos->id">
                                            <flux:table.cell>{{ $pos->nr }}</flux:table.cell>
                                            <flux:table.cell>
                                                <strong>{{ $pos->bezeichnung }}</strong>
                                                @if ($pos->art_nr)
                                                    <br><small class="text-zinc-500">Art.-Nr.: {{ $pos->art_nr }}</small>
                                                @endif
                                            </flux:table.cell>
                                            <flux:table.cell class="text-right">{{ number_format((float) $pos->menge, 2, ',', '.') }}</flux:table.cell>
                                            <flux:table.cell>{{ $pos->einheit }}</flux:table.cell>
                                            <flux:table.cell class="text-right">{{ number_format((float) $pos->preis, 2, ',', '.') }} €</flux:table.cell>
                                            <flux:table.cell class="text-right">{{ number_format($pos->gesamt(), 2, ',', '.') }} €</flux:table.cell>
                                            <flux:table.cell>
                                                @if ($pos->hasPositionPdf())
                                                    <a href="{{ $pos->getFirstMediaUrl('position_pdf') }}" target="_blank" class="inline-flex items-start gap-2">
                                                        <iframe
                                                            src="{{ $pos->getFirstMediaUrl('position_pdf') }}#toolbar=0&navpanes=0&scrollbar=0"
                                                            class="h-12 w-16 rounded border border-[#d0e3f9] dark:border-white/10"
                                                            title="PDF Vorschau Position {{ $pos->nr }}"
                                                        ></iframe>
                                                        <span class="text-xs text-zinc-500">Öffnen</span>
                                                    </a>
                                                @else
                                                    <span class="text-xs text-zinc-400">—</span>
                                                @endif
                                            </flux:table.cell>
                                        </flux:table.row>
                                    @endforeach
                                </flux:table.rows>
                            </flux:table>
                        @endif
                    </flux:tab.panel>

                    <flux:tab.panel name="auftrag">
                        @include('intranet-app-bestellungen::livewire.apps.bestellungen.partials.auftragszeilen')
                    </flux:tab.panel>

                    <flux:tab.panel name="angebote">
                        <div @if ($this->angebotAnalyseLaeuft) wire:poll.5s.visible @endif>
                        @php($angebotsAuswertung = $this->angebotsregelAuswertung)
                        @if ($this->angebotAnalyseLaeuft)
                            <flux:callout icon="arrow-path" variant="secondary" class="mb-4">
                                Die Analyse läuft im Hintergrund. Der Status aktualisiert sich von selbst.
                            </flux:callout>
                        @endif
                        @if ($angebotsAuswertung->anwendbar)
                            <flux:callout
                                :icon="$angebotsAuswertung->bereit ? 'check-circle' : 'information-circle'"
                                :variant="$angebotsAuswertung->bereit ? 'success' : 'warning'"
                                class="mb-4"
                            >
                                <flux:callout.heading>Angebotsvoraussetzungen</flux:callout.heading>
                                <flux:callout.text>
                                    {{ $angebotsAuswertung->zusammenfassung() }}
                                    @if ($angebotsAuswertung->pruefungAktiv)
                                        <br><span class="text-sm opacity-80">D3-Übertragung von Angeboten und Bestellschein erfolgt erst beim Abschluss „Bestellen“.</span>
                                    @endif
                                </flux:callout.text>
                            </flux:callout>
                        @endif

                        @if ($this->kannAngeboteErfassen())
                            @if ($angebotsAuswertung->anwendbar && $angebotsAuswertung->hatAusnahmeBegruendung)
                                <flux:callout icon="information-circle" variant="secondary" class="mb-4">
                                    Es ist bereits eine Ausnahme-Begründung erfasst. Weitere Vergleichsangebote sind optional.
                                </flux:callout>
                            @endif

                            <form wire:submit="angebotSpeichern" class="space-y-3 mb-6">
                                @if ($angebotsAuswertung->anwendbar)
                                    <flux:select wire:model.live="angebotTyp" label="Typ">
                                        <flux:select.option value="angebot">Vergleichsangebot (PDF)</flux:select.option>
                                        @if (! $angebotsAuswertung->hatAusnahmeBegruendung)
                                            <flux:select.option value="begruendung">Ausnahme-Begründung (Text)</flux:select.option>
                                        @endif
                                    </flux:select>
                                @endif

                                @if ($angebotsAuswertung->anwendbar && $angebotTyp === 'begruendung')
                                    <flux:textarea
                                        wire:model="angebotBegruendung"
                                        label="Ausnahme-Begründung"
                                        description="Begründet, warum keine Vergleichsangebote vorliegen. Wird automatisch als PDF erzeugt."
                                        rows="5"
                                        required
                                    />
                                    <flux:error name="angebotBegruendung" />
                                @else
                                    <div class="grid gap-3 md:grid-cols-2">
                                        <flux:input wire:model="angebotLieferant" label="Lieferant (optional)" />
                                        <flux:input wire:model="angebotNummer" label="Angebots-Nr. (optional)" />
                                        <flux:input wire:model="angebotDatum" type="date" label="Angebotsdatum (optional)" />
                                        <flux:input wire:model="angebotBetragNetto" type="number" step="0.01" label="Betrag netto (€, optional)" />
                                        <flux:input wire:model="angebotBetrag" type="number" step="0.01" label="Betrag brutto (€, optional)" />
                                        <flux:input type="file" wire:model="angebotPdf" label="Angebots-PDF" accept="application/pdf,.pdf" />
                                        <flux:error name="angebotPdf" class="md:col-span-2" />
                                    </div>
                                    <flux:callout icon="sparkles" variant="secondary">
                                        Wenn Lieferant, Angebotsnummer, Datum, Netto oder Brutto leer bleiben, werden diese Felder automatisch per KI aus dem PDF ermittelt.
                                    </flux:callout>
                                @endif

                                <flux:button type="submit" variant="primary" icon="plus">
                                    {{ $angebotsAuswertung->anwendbar && $angebotTyp === 'begruendung' ? 'Ausnahme-Begründung speichern' : ($bestellung->istAuftragsvergabe() ? 'Angebot speichern' : 'Vergleichsangebot speichern') }}
                                </flux:button>
                            </form>
                        @else
                            <flux:text class="text-zinc-500 mb-4">
                                Angebote können im Status „Entwurf“ oder „Abgelehnt“ ergänzt werden.
                            </flux:text>
                        @endif

                        @if ($bestellung->angebote->isEmpty())
                            <x-intranet-app-bestellungen::hwk-empty bare icon="document-text" title="Keine Angebote">
                                {{ $bestellung->istAuftragsvergabe() ? 'Keine Angebote erfasst.' : 'Keine Angebote oder Begründungen erfasst.' }}
                            </x-intranet-app-bestellungen::hwk-empty>
                        @else
                            <flux:table class="w-full">
                                <colgroup>
                                    @unless ($bestellung->istAuftragsvergabe())
                                        <col style="width: 5%" />
                                    @endunless
                                    <col style="width: {{ $bestellung->istAuftragsvergabe() ? '20%' : '15%' }}" />
                                    <col style="width: 13%" />
                                    <col style="width: 12%" />
                                    <col style="width: 13%" />
                                    <col style="width: 13%" />
                                    <col style="width: 9%" />
                                    <col style="width: 6%" />
                                    <col style="width: 6%" />
                                    <col style="width: 8%" />
                                </colgroup>
                                <flux:table.columns>
                                    @unless ($bestellung->istAuftragsvergabe())
                                        <flux:table.column class="px-1">Typ</flux:table.column>
                                    @endunless
                                    <flux:table.column>Lieferant</flux:table.column>
                                    <flux:table.column>Nummer</flux:table.column>
                                    <flux:table.column>Datum</flux:table.column>
                                    <flux:table.column align="end">Netto</flux:table.column>
                                    <flux:table.column align="end">Brutto</flux:table.column>
                                    <flux:table.column align="center" class="px-1">Analyse</flux:table.column>
                                    <flux:table.column align="center" class="px-1">D3</flux:table.column>
                                    <flux:table.column align="center" class="px-1">PDF</flux:table.column>
                                    <flux:table.column align="end" class="px-1">Aktion</flux:table.column>
                                </flux:table.columns>
                                <flux:table.rows>
                                    @foreach ($bestellung->angebote as $angebot)
                                        <flux:table.row :key="$angebot->id">
                                            @unless ($bestellung->istAuftragsvergabe())
                                                <flux:table.cell align="center" class="px-1">
                                                    @if ($angebot->typ === 'begruendung')
                                                        <flux:tooltip content="Begründung">
                                                            <flux:icon.chat-bubble-left-ellipsis variant="mini" class="text-amber-600 dark:text-amber-400" />
                                                        </flux:tooltip>
                                                    @else
                                                        <flux:tooltip content="Angebot">
                                                            <flux:icon.document-text variant="mini" class="text-[#073070] dark:text-[#d0e3f9]" />
                                                        </flux:tooltip>
                                                    @endif
                                                </flux:table.cell>
                                            @endunless
                                            <flux:table.cell class="overflow-hidden">
                                                @include('intranet-app-bestellungen::livewire.apps.bestellungen.partials.angebot-lieferant', ['angebot' => $angebot])
                                            </flux:table.cell>
                                            <flux:table.cell class="overflow-hidden">
                                                <span class="block truncate">{{ $angebot->nummer }}</span>
                                            </flux:table.cell>
                                            <flux:table.cell>{{ $angebot->angebotsdatum?->format('d.m.Y') }}</flux:table.cell>
                                            <flux:table.cell align="end" class="tabular-nums">
                                                @if ($angebot->betrag_netto)
                                                    {{ number_format((float) $angebot->betrag_netto, 2, ',', '.') }} €
                                                @endif
                                            </flux:table.cell>
                                            <flux:table.cell align="end" class="tabular-nums">
                                                @if ($angebot->betrag)
                                                    {{ number_format((float) $angebot->betrag, 2, ',', '.') }} €
                                                @endif
                                            </flux:table.cell>
                                            <flux:table.cell align="center" class="px-1">
                                                @include('intranet-app-bestellungen::livewire.apps.bestellungen.partials.angebot-analyse-status', ['angebot' => $angebot])
                                            </flux:table.cell>
                                            <flux:table.cell align="center" class="px-1">
                                                @if (filled($angebot->d3id) && ($angebotD3Url = $this->d3OneUrl($angebot->d3id)))
                                                    <flux:tooltip content="In D3 öffnen">
                                                        <a
                                                            href="{{ $angebotD3Url }}"
                                                            target="_blank"
                                                            rel="noopener noreferrer"
                                                            class="inline-flex text-emerald-600 hover:text-emerald-500 dark:text-emerald-400 dark:hover:text-emerald-300"
                                                            aria-label="In D3 öffnen"
                                                        >
                                                            <flux:icon.cloud variant="mini" />
                                                        </a>
                                                    </flux:tooltip>
                                                @else
                                                    <flux:tooltip content="Lokal, Übertragung beim Bestellen">
                                                        <flux:icon.cloud variant="mini" class="text-zinc-400" />
                                                    </flux:tooltip>
                                                @endif
                                            </flux:table.cell>
                                            <flux:table.cell align="center" class="px-1">
                                                @if (filled($angebot->pdf_path) || ($angebot->typ === 'begruendung' && filled($angebot->begruendung)))
                                                    <flux:button
                                                        size="xs"
                                                        variant="ghost"
                                                        icon="document-text"
                                                        :tooltip="$angebot->typ === 'begruendung' ? 'Begründung anzeigen' : 'Angebot anzeigen'"
                                                        :href="route('apps.bestellungen.angebot.pdf.inline', ['bestellung' => $bestellung, 'angebot' => $angebot])"
                                                        target="_blank"
                                                        rel="noopener noreferrer"
                                                    />
                                                @else
                                                    <span class="text-zinc-400">—</span>
                                                @endif
                                            </flux:table.cell>
                                            <flux:table.cell align="end" class="px-1">
                                                @if ($this->kannAngeboteErfassen())
                                                    <div class="flex items-center justify-end gap-0.5">
                                                        @if ($angebot->typ === 'angebot')
                                                            <flux:button
                                                                size="xs"
                                                                variant="ghost"
                                                                icon="pencil-square"
                                                                tooltip="Angaben"
                                                                wire:click="angebotAngabenOeffnen({{ $angebot->id }})"
                                                            />
                                                        @endif
                                                        <flux:button
                                                            size="xs"
                                                            variant="danger"
                                                            icon="trash"
                                                            tooltip="Löschen"
                                                            wire:click="angebotLoeschen({{ $angebot->id }})"
                                                            wire:confirm="Angebot inkl. PDF-Datei wirklich löschen?"
                                                        />
                                                    </div>
                                                @else
                                                    <span class="text-zinc-400">—</span>
                                                @endif
                                            </flux:table.cell>
                                        </flux:table.row>
                                    @endforeach
                                </flux:table.rows>
                            </flux:table>
                        @endif
                        </div>
                    </flux:tab.panel>

                    <flux:tab.panel name="d3">
                        <div class="space-y-4">
                            <div class="flex flex-wrap gap-2">
                                <flux:badge :color="$this->d3DokumenteAmpel['bestellschein'] ? 'emerald' : 'zinc'" size="sm">
                                    Bestellschein
                                </flux:badge>
                                <flux:badge :color="$this->d3DokumenteAmpel['rechnung'] ? 'emerald' : 'zinc'" size="sm">
                                    Rechnung
                                </flux:badge>
                                <flux:badge :color="$this->d3DokumenteAmpel['lieferschein'] ? 'emerald' : 'zinc'" size="sm">
                                    Lieferschein
                                </flux:badge>
                            </div>

                            @if (! $d3DokumenteGeladen)
                                <flux:button size="sm" icon="magnifying-glass" wire:click="ladeD3Dokumente">
                                    D3-Dokumente laden
                                </flux:button>
                            @endif

                            @if ($d3DokumenteGeladen && empty($d3Dokumente))
                                <x-intranet-app-bestellungen::hwk-empty bare icon="cloud" title="Keine Dokumente">
                                    Keine D3-Dokumente zur BEN gefunden.
                                </x-intranet-app-bestellungen::hwk-empty>
                            @endif

                            @if (! empty($d3Dokumente))
                                <flux:table>
                                    <flux:table.columns>
                                        <flux:table.column>Art</flux:table.column>
                                        <flux:table.column>D3-ID</flux:table.column>
                                        <flux:table.column>Datei / Titel</flux:table.column>
                                    </flux:table.columns>
                                    <flux:table.rows>
                                        @foreach ($d3Dokumente as $doc)
                                            <flux:table.row :key="'d3-doc-'.$doc['id']">
                                                <flux:table.cell>{{ $doc['art'] }}</flux:table.cell>
                                                <flux:table.cell>
                                                    @if (! empty($doc['url']))
                                                        <a href="{{ $doc['url'] }}" target="_blank" rel="noopener noreferrer" class="text-[#073070] hover:underline dark:text-[#d0e3f9]">
                                                            {{ $doc['id'] }}
                                                        </a>
                                                    @else
                                                        {{ $doc['id'] }}
                                                    @endif
                                                </flux:table.cell>
                                                <flux:table.cell>{{ $doc['filename'] ?? $doc['caption'] ?? '—' }}</flux:table.cell>
                                            </flux:table.row>
                                        @endforeach
                                    </flux:table.rows>
                                </flux:table>
                            @endif
                        </div>
                    </flux:tab.panel>

                    <flux:tab.panel name="notizen">
                        <form wire:submit="notizSpeichern" class="space-y-3 mb-6">
                            <flux:textarea wire:model="notizText" label="Neue Notiz" placeholder="Interne Notiz erfassen…" rows="3" />
                            <flux:button type="submit" variant="primary" icon="plus">Notiz hinzufügen</flux:button>
                        </form>

                        @if ($bestellung->notizen->isEmpty())
                            <x-intranet-app-bestellungen::hwk-empty bare icon="chat-bubble-left-ellipsis" title="Keine Notizen">
                                Noch keine Notizen.
                            </x-intranet-app-bestellungen::hwk-empty>
                        @else
                            <ul class="space-y-3">
                                @foreach ($bestellung->notizen as $notiz)
                                    <li wire:key="notiz-{{ $notiz->id }}" class="border rounded-lg p-3">
                                        <div class="flex items-center justify-between text-sm text-zinc-500">
                                            <span>{{ optional($notiz->user)->name }}</span>
                                            <span>{{ $notiz->created_at?->format('d.m.Y H:i') }}</span>
                                        </div>
                                        <div class="mt-2 whitespace-pre-wrap">{{ $notiz->text }}</div>
                                        @if ($notiz->an_d3_gesendet)
                                            <flux:badge size="sm" color="emerald" class="mt-2">an D3 gesendet</flux:badge>
                                        @endif
                                    </li>
                                @endforeach
                            </ul>
                        @endif
                    </flux:tab.panel>

                    <flux:tab.panel name="verlauf">
                        @if ($bestellung->aktionen->isEmpty())
                            <x-intranet-app-bestellungen::hwk-empty bare icon="clock" title="Keine Aktivitäten">
                                Noch keine Aktivitäten.
                            </x-intranet-app-bestellungen::hwk-empty>
                        @else
                            <ol class="relative border-l-2 border-[#d0e3f9] dark:border-white/10 ml-4 space-y-4">
                                @foreach ($bestellung->aktionen as $aktion)
                                    <li wire:key="aktion-{{ $aktion->id }}" class="ml-4">
                                        <span class="absolute -left-2 mt-1 h-3 w-3 rounded-full bg-[#073070]"></span>
                                        <div class="text-sm text-zinc-500">{{ $aktion->created_at?->format('d.m.Y H:i') }}</div>
                                        <div class="font-medium">{{ $aktion->typ?->label() ?? $aktion->typ }}</div>
                                        @if ($aktion->user)
                                            <div class="text-sm">durch {{ $aktion->user->name }}</div>
                                        @endif
                                        @if ($aktion->nachricht)
                                            <div class="text-sm text-zinc-600 dark:text-zinc-300 mt-1">{{ $aktion->nachricht }}</div>
                                        @endif
                                    </li>
                                @endforeach
                            </ol>
                        @endif
                    </flux:tab.panel>
                </flux:tab.group>
            </section>
            </div>

            <div x-show="stammdatenOffen" x-cloak>
            <section class="glass-card overflow-hidden p-5">
                <div class="mb-3 flex items-center justify-between gap-2">
                    <flux:heading size="lg">Stammdaten</flux:heading>
                    <flux:button size="sm" variant="ghost" icon="chevron-right" x-on:click="stammdatenOffen = false">
                        Ausblenden
                    </flux:button>
                </div>
                <dl class="hwk-rows divide-y divide-[#d0e3f9] dark:divide-white/10">
                    @if ($bestellung->projekt)
                        <div>
                            <dt>Projekt</dt>
                            <dd>
                                <a
                                    href="{{ route('apps.bestellungen.projekte.detail', $bestellung->projekt) }}"
                                    wire:navigate
                                    class="text-[#073070] hover:underline dark:text-[#d0e3f9]"
                                >
                                    {{ $bestellung->projekt->name }}
                                </a>
                            </dd>
                        </div>
                    @endif
                    <div>
                        <dt>Art</dt>
                        <dd>{{ $bestellung->typ?->label() ?? $bestellung->typ }}</dd>
                    </div>
                    @if ($bestellung->istIntern() && $bestellung->internerEmpfaenger)
                        <div>
                            <dt>Interner Empfänger</dt>
                            <dd>{{ $bestellung->internerEmpfaenger->vorname }} {{ $bestellung->internerEmpfaenger->nachname }}</dd>
                        </div>
                    @endif
                    <div>
                        <dt>{{ $bestellung->istAuftragsvergabe() ? 'Auftragnehmer' : 'Lieferant' }}{{ $bestellung->benoetigtFinalenLieferantenVorD3() ? ' (vorläufig)' : '' }}</dt>
                        <dd>
                            @if ($bestellung->istAuftragsvergabe() && ! filled($bestellung->lieferantenname))
                                Wird mit dem gewählten Angebot festgelegt
                            @else
                                {{ $bestellung->lieferantenname }}@if (filled($bestellung->lieferantennummer)) ({{ $bestellung->lieferantennummer }})@endif
                            @endif
                        </dd>
                    </div>
                    <div>
                        <dt>Kostenstelle</dt>
                        <dd>{{ $bestellung->kostenstelle }}</dd>
                    </div>
                    <div>
                        <dt>Haushaltsjahr</dt>
                        <dd>{{ $bestellung->haushaltsjahr }}</dd>
                    </div>
                    <div>
                        <dt>Ersteller</dt>
                        <dd>{{ optional($bestellung->user)->name }}</dd>
                    </div>
                    <div>
                        <dt>Aktueller Freigeber</dt>
                        @php($aktuelleFreigeber = $this->aktuelleFreigeberNamen())
                        @if ($aktuelleFreigeber !== [])
                            <dd>{{ implode(', ', $aktuelleFreigeber) }}</dd>
                        @elseif ($bestellung->status?->isFreigabePending())
                            <dd class="text-amber-600">Nicht zugewiesen (Wertgrenzen/Freigeber prüfen)</dd>
                        @else
                            <dd>—</dd>
                        @endif
                    </div>
                    <div>
                        <dt>Besteller</dt>
                        <dd>{{ optional($bestellung->besteller)->name ?? optional($bestellung->user)->name ?? '—' }}</dd>
                    </div>
                    <div>
                        <dt>{{ $bestellung->istAuftragsvergabe() ? 'Gesamtbetrag (netto)' : 'Gesamtbetrag' }}</dt>
                        <dd class="text-lg font-semibold text-emerald-600">
                            {{ number_format((float) $bestellung->gesamtbetrag, 2, ',', '.') }} €
                        </dd>
                    </div>
                    @if ($bestellung->d3id)
                        <div>
                            <dt>D3-ID</dt>
                            <dd>
                                @if ($this->d3OneUrl())
                                    <a
                                        href="{{ $this->d3OneUrl() }}"
                                        target="_blank"
                                        rel="noopener noreferrer"
                                        class="text-[#073070] hover:underline dark:text-[#d0e3f9]"
                                    >
                                        {{ $bestellung->d3id }}
                                    </a>
                                @else
                                    {{ $bestellung->d3id }}
                                @endif
                            </dd>
                        </div>
                    @endif
                    @if ($bestellung->wiederholt_von_id)
                        <div>
                            <dt>Erstellt aus Vorlage</dt>
                            <dd>{{ optional($bestellung->vorlage)->nummer }}</dd>
                        </div>
                    @endif
                </dl>

                @if (! empty($bestellung->kontierung))
                    <flux:separator class="my-4" />
                    <flux:heading size="md" class="mb-2">Kontierung</flux:heading>
                    <table class="w-full text-sm">
                        <thead class="text-zinc-500">
                            <tr class="text-left">
                                <th>Kst.</th>
                                <th>Kurs-Nr.</th>
                                <th>Raum</th>
                                <th class="text-right">%</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($bestellung->kontierung as $kont)
                                <tr class="border-t border-zinc-100 dark:border-zinc-800">
                                    <td class="py-1">{{ $kont['kostenstelle'] ?? '—' }}</td>
                                    <td>{{ $kont['kursnummer'] ?? '' }}</td>
                                    <td>{{ $kont['raum'] ?? '' }}</td>
                                    <td class="text-right">{{ number_format((float) ($kont['aufteilung'] ?? 0), 2, ',', '.') }} %</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                @endif
            </section>
            </div>
        </div>

        <flux:modal name="freigeben-modal" :show="false">
            <form wire:submit="freigeben" class="space-y-4">
                <flux:heading size="lg">Bestellung freigeben</flux:heading>
                <flux:text>{{ $bestellung->nummer }} – {{ number_format((float) $bestellung->gesamtbetrag, 2, ',', '.') }} €</flux:text>
                @if ($this->freigabeIstLetzteUnterschrift())
                    @include('intranet-app-bestellungen::livewire.apps.bestellungen.partials.unterschrift-hinweis')
                @endif
                <flux:textarea wire:model="freigabeNachricht" label="Optionale Nachricht" rows="2" />
                <div class="flex justify-end gap-2">
                    <flux:modal.close>
                        <flux:button type="button" variant="ghost">Abbrechen</flux:button>
                    </flux:modal.close>
                    <flux:button type="submit" variant="primary" icon="check">Freigeben</flux:button>
                </div>
            </form>
        </flux:modal>

        <flux:modal name="einreichen-modal" class="w-full max-w-2xl" :show="false">
            <form wire:submit="einreichen" class="space-y-4">
                <flux:heading size="lg">Zur Freigabe einreichen</flux:heading>
                <flux:text>{{ $bestellung->nummer }} – {{ number_format((float) $bestellung->gesamtbetrag, 2, ',', '.') }} €</flux:text>
                @if ($this->einreichenUnterschreibt())
                    @include('intranet-app-bestellungen::livewire.apps.bestellungen.partials.unterschrift-hinweis')
                @else
                <flux:field>
                    <flux:label>Freigeber auswählen</flux:label>
                    <flux:select
                        variant="listbox"
                        searchable
                        clearable
                        wire:model="einreichenAnUserId"
                        placeholder="Freigeber suchen…"
                    >
                        @foreach ($this->moeglicheEinreichFreigeberOptions() as $id => $name)
                            <flux:select.option value="{{ $id }}">{{ $name }}</flux:select.option>
                        @endforeach
                    </flux:select>
                    <flux:error name="einreichenAnUserId" />
                </flux:field>
                @if ($einreichFreigeberHinweise !== [])
                    <flux:callout icon="information-circle" variant="secondary">
                        <flux:callout.heading>Abwesenheiten / Hinweise</flux:callout.heading>
                        <flux:callout.text>
                            <ul class="mt-1 max-h-40 list-disc space-y-1 overflow-y-auto pl-4 text-sm">
                                @foreach ($einreichFreigeberHinweise as $hinweis)
                                    <li>{{ $hinweis }}</li>
                                @endforeach
                            </ul>
                        </flux:callout.text>
                    </flux:callout>
                @endif
                @endif
                <div class="flex justify-end gap-2">
                    <flux:modal.close>
                        <flux:button type="button" variant="ghost">Abbrechen</flux:button>
                    </flux:modal.close>
                    <flux:button type="submit" variant="primary" icon="paper-airplane">Einreichen</flux:button>
                </div>
            </form>
        </flux:modal>

        <flux:modal name="ablehnen-modal" :show="false">
            <form wire:submit="ablehnen" class="space-y-4">
                <flux:heading size="lg">Bestellung ablehnen</flux:heading>
                <flux:textarea wire:model="ablehnenGrund" label="Grund" rows="3" required />
                <div class="flex justify-end gap-2">
                    <flux:modal.close>
                        <flux:button type="button" variant="ghost">Abbrechen</flux:button>
                    </flux:modal.close>
                    <flux:button type="submit" variant="danger" icon="x-mark">Ablehnen</flux:button>
                </div>
            </form>
        </flux:modal>

        <flux:modal name="weiterleiten-modal" :show="false">
            <form wire:submit="weiterleiten" class="space-y-4">
                <flux:heading size="lg">Bestellung weiterleiten</flux:heading>
                <flux:field>
                    <flux:label>Neuer Freigeber</flux:label>
                    <flux:select
                        variant="listbox"
                        searchable
                        clearable
                        wire:model="weiterleitenAnUserId"
                        placeholder="User suchen…"
                    >
                        @foreach ($this->moeglicheFreigeberOptions() as $id => $name)
                            <flux:select.option value="{{ $id }}">{{ $name }}</flux:select.option>
                        @endforeach
                    </flux:select>
                    <flux:error name="weiterleitenAnUserId" />
                </flux:field>
                <flux:textarea wire:model="weiterleitenNachricht" label="Nachricht (optional)" rows="2" />
                <div class="flex justify-end gap-2">
                    <flux:modal.close>
                        <flux:button type="button" variant="ghost">Abbrechen</flux:button>
                    </flux:modal.close>
                    <flux:button type="submit" variant="primary" icon="arrow-right">Weiterleiten</flux:button>
                </div>
            </form>
        </flux:modal>

        <flux:modal name="bestellen-lieferant" class="max-w-lg">
            <flux:heading size="lg">Tatsächlichen Lieferanten hinterlegen</flux:heading>
            <flux:text class="mt-1 mb-4">
                Bevor der Bestellschein nach D3 übertragen wird, wählen Sie den Lieferanten, bei dem die Bestellung tatsächlich aufgegeben wurde.
            </flux:text>

            <flux:field>
                <flux:label>Lieferant</flux:label>
                <flux:select
                    variant="combobox"
                    wire:model.live="bestellenLieferantennummer"
                    :filter="false"
                    clearable
                    placeholder="Lieferant wählen…"
                >
                    <x-slot name="input">
                        <flux:select.input
                            wire:model.live.debounce.250ms="bestellenLieferantSearch"
                            placeholder="Name oder Nummer eingeben…"
                        />
                    </x-slot>

                    @foreach ($this->bestellenLieferantenSuggestions as $lieferant)
                        <flux:select.option
                            wire:key="bestellen-lf-{{ $lieferant->lieferantennummer }}"
                            value="{{ $lieferant->lieferantennummer }}"
                        >
                            {{ $lieferant->lieferantenname }} ({{ $lieferant->lieferantennummer }})
                        </flux:select.option>
                    @endforeach

                    <x-slot name="empty">
                        <flux:select.option.empty when-loading="Suche läuft…">
                            Keine Lieferanten gefunden.
                        </flux:select.option.empty>
                    </x-slot>
                </flux:select>
                <flux:error name="bestellenLieferantennummer" />
            </flux:field>

            <div class="flex gap-2 justify-end mt-6">
                <flux:modal.close>
                    <flux:button variant="ghost">Abbrechen</flux:button>
                </flux:modal.close>
                <flux:button
                    variant="primary"
                    icon="paper-airplane"
                    wire:click="bestellenMitLieferant"
                    wire:loading.attr="disabled"
                    wire:target="bestellenMitLieferant"
                >
                    <span wire:loading.remove wire:target="bestellenMitLieferant">Bestellen und an D3 senden</span>
                    <span wire:loading wire:target="bestellenMitLieferant" class="inline-flex items-center gap-2">
                        <flux:icon name="arrow-path" class="size-4 animate-spin" />
                        Wird übertragen…
                    </span>
                </flux:button>
            </div>
        </flux:modal>

        <flux:modal name="angebote-waehlen" class="w-full max-w-2xl">
            <flux:heading size="lg">Angebote auswählen</flux:heading>
            <flux:text class="mt-1 mb-4">
                Jedes gewählte Angebot wird eine eigene Zeile auf dem Auftragsschein. Der Anbieter wird der Auftragnehmer. Weitere Zeilen müssen vom selben Anbieter stammen.
            </flux:text>

            @if ($this->waehlbareAngebote->isEmpty())
                <x-intranet-app-bestellungen::hwk-empty bare icon="document-text" title="Keine freien Angebote">
                    Es liegen keine freien Angebote vor. Laden Sie zuerst ein Angebot hoch oder erfassen Sie eine Zeile manuell.
                </x-intranet-app-bestellungen::hwk-empty>
            @else
                <div class="max-h-96 space-y-2 overflow-y-auto">
                    @foreach ($this->waehlbareAngebote as $angebot)
                        <label wire:key="wahl-angebot-{{ $angebot->id }}" class="flex gap-3 rounded-lg border border-[#d0e3f9] p-3 dark:border-white/10 {{ $angebot->angabenVollstaendig() ? 'cursor-pointer' : 'opacity-80' }}">
                            <flux:checkbox
                                wire:model="ausgewaehlteAngebotIds"
                                value="{{ $angebot->id }}"
                                :disabled="! $angebot->angabenVollstaendig()"
                            />
                            <span class="min-w-0 flex-1 text-sm">
                                <span class="block font-medium">
                                    @include('intranet-app-bestellungen::livewire.apps.bestellungen.partials.angebot-lieferant', ['angebot' => $angebot])
                                </span>
                                <span class="mt-1 block text-zinc-500">
                                    {{ $angebot->angebotsdatum?->format('d.m.Y') ?: 'Datum offen' }}
                                    · Nr. {{ $angebot->nummer ?: 'offen' }}
                                    · netto {{ $angebot->betrag_netto !== null ? number_format((float) $angebot->betrag_netto, 2, ',', '.').' €' : 'offen' }}
                                    · brutto {{ $angebot->betrag !== null ? number_format((float) $angebot->betrag, 2, ',', '.').' €' : 'offen' }}
                                </span>
                                <span class="mt-1 flex flex-wrap items-center gap-1">
                                    @include('intranet-app-bestellungen::livewire.apps.bestellungen.partials.angebot-analyse-status', ['angebot' => $angebot])
                                    @if ($this->angebotWeichtVomAuftragnehmerAb($angebot))
                                        <flux:badge size="sm" color="amber">Lieferant weicht vom Auftragnehmer ab</flux:badge>
                                    @endif
                                    @unless ($angebot->angabenVollstaendig())
                                        <span class="text-xs text-amber-700 dark:text-amber-400">Fehlt: {{ implode(', ', $angebot->fehlendeAngaben()) }}</span>
                                    @endunless
                                </span>
                            </span>
                        </label>
                    @endforeach
                </div>
            @endif

            <div class="mt-6 flex justify-end gap-2">
                <flux:modal.close>
                    <flux:button variant="ghost">Abbrechen</flux:button>
                </flux:modal.close>
                <flux:button variant="primary" icon="check" wire:click="angeboteAlsAuftragszeilenUebernehmen">
                    Übernehmen
                </flux:button>
            </div>
        </flux:modal>

        <flux:modal name="angebot-angaben" class="w-full max-w-2xl">
            <flux:heading size="lg">Angebotsangaben</flux:heading>
            <flux:text class="mt-1 mb-4">
                Wählen Sie den Lieferanten aus den Stammdaten. Nummer, Datum und Netto können Sie hier ergänzen oder korrigieren.
            </flux:text>
            <div class="grid gap-3 md:grid-cols-2">
                <div class="md:col-span-2">
                    @if (filled($angebotAngabenErkannterLieferant))
                        <flux:text class="mb-2 text-sm">Erkannt: {{ $angebotAngabenErkannterLieferant }}</flux:text>
                    @endif
                    <flux:field>
                        <flux:label>Lieferant</flux:label>
                        <flux:select
                            variant="combobox"
                            wire:model.live="angebotAngabenLieferantennummer"
                            :filter="false"
                            clearable
                            placeholder="Lieferant wählen…"
                        >
                            <x-slot name="input">
                                <flux:select.input
                                    wire:model.live.debounce.250ms="angebotAngabenLieferantSearch"
                                    placeholder="Name oder Nummer eingeben…"
                                />
                            </x-slot>
                            @foreach ($this->angebotAngabenLieferantenSuggestions as $lieferant)
                                <flux:select.option
                                    wire:key="angaben-lf-{{ $lieferant->lieferantennummer }}"
                                    value="{{ $lieferant->lieferantennummer }}"
                                >
                                    {{ $lieferant->lieferantenname }} ({{ $lieferant->lieferantennummer }})
                                </flux:select.option>
                            @endforeach
                            <x-slot name="empty">
                                <flux:select.option.empty when-loading="Suche läuft…">
                                    Keine Lieferanten gefunden.
                                </flux:select.option.empty>
                            </x-slot>
                        </flux:select>
                        <flux:error name="angebotAngabenLieferantennummer" />
                    </flux:field>
                    <div class="mt-2 flex justify-end">
                        <flux:button
                            type="button"
                            variant="outline"
                            size="sm"
                            wire:click="angebotFehlendenLieferantVorbereiten"
                        >
                            Lieferant fehlt
                        </flux:button>
                    </div>
                </div>
                <div>
                    <flux:input wire:model="angebotAngabenNummer" label="Angebots-Nr." required />
                    <flux:error name="angebotAngabenNummer" />
                </div>
                <div>
                    <flux:input type="date" wire:model="angebotAngabenDatum" label="Angebotsdatum" required />
                    <flux:error name="angebotAngabenDatum" />
                </div>
                <div>
                    <flux:input type="number" step="0.01" min="0" wire:model="angebotAngabenNetto" label="Netto (€)" required />
                    <flux:error name="angebotAngabenNetto" />
                </div>
                <div>
                    <flux:input type="number" step="0.01" min="0" wire:model="angebotAngabenBrutto" label="Brutto (€, optional)" />
                    <flux:error name="angebotAngabenBrutto" />
                </div>
            </div>
            <div class="mt-6 flex justify-end gap-2">
                <flux:modal.close>
                    <flux:button variant="ghost">Abbrechen</flux:button>
                </flux:modal.close>
                <flux:button variant="primary" icon="check" wire:click="angebotAngabenSpeichern">
                    Angaben speichern
                </flux:button>
            </div>
        </flux:modal>

        <flux:modal name="angebot-fehlender-lieferant" class="max-w-lg">
            <flux:heading size="lg">Lieferant fehlt</flux:heading>
            <flux:text class="mt-1 mb-4">
                Melden Sie einen fehlenden Lieferanten. Die Buchhaltung wird informiert und der Platzhalter-Lieferant wird für dieses Angebot gesetzt.
            </flux:text>
            <div class="space-y-4">
                <flux:input wire:model="fehlenderLieferantName" label="Name" placeholder="Name des Lieferanten" required />
                <flux:error name="fehlenderLieferantName" />
                <flux:textarea wire:model="fehlenderLieferantAdresse" label="Adresse" placeholder="Straße, PLZ Ort" rows="3" />
                <flux:error name="fehlenderLieferantAdresse" />
                <flux:input wire:model="fehlenderLieferantIban" label="IBAN" placeholder="DE…" />
                <flux:error name="fehlenderLieferantIban" />
                <flux:input wire:model="fehlenderLieferantWebseite" label="Webseite" placeholder="https://…" />
                <flux:error name="fehlenderLieferantWebseite" />
            </div>
            <div class="mt-6 flex justify-end gap-2">
                <flux:modal.close>
                    <flux:button variant="ghost">Abbrechen</flux:button>
                </flux:modal.close>
                <flux:button variant="primary" wire:click="angebotFehlendenLieferantMelden" wire:loading.attr="disabled" wire:target="angebotFehlendenLieferantMelden">
                    <span wire:loading.remove wire:target="angebotFehlendenLieferantMelden">Melden</span>
                    <span wire:loading wire:target="angebotFehlendenLieferantMelden">Wird gesendet…</span>
                </flux:button>
            </div>
        </flux:modal>

        <flux:modal name="zeile-manuell" class="w-full max-w-2xl">
            <flux:heading size="lg">Zeile ohne Angebot</flux:heading>
            <flux:text class="mt-1 mb-4">
                @if (filled($bestellung->lieferantenname))
                    Datum, Nummer und Betrag werden von Hand erfasst. Auftragnehmer bleibt {{ $bestellung->lieferantenname }}.
                @else
                    Datum, Nummer und Betrag werden von Hand erfasst. Der gewählte Lieferant wird der Auftragnehmer.
                @endif
            </flux:text>

            @if (! filled($bestellung->lieferantenname))
                <flux:field class="mb-3">
                    <flux:label>Auftragnehmer</flux:label>
                    <flux:select
                        variant="combobox"
                        wire:model.live="manuelleZeileLieferantennummer"
                        :filter="false"
                        clearable
                        placeholder="Lieferant wählen…"
                    >
                        <x-slot name="input">
                            <flux:select.input
                                wire:model.live.debounce.250ms="manuelleZeileLieferantSearch"
                                placeholder="Name oder Nummer eingeben…"
                            />
                        </x-slot>
                        @foreach ($this->manuelleLieferantenSuggestions as $lieferant)
                            <flux:select.option
                                wire:key="manuell-lf-{{ $lieferant->lieferantennummer }}"
                                value="{{ $lieferant->lieferantennummer }}"
                            >
                                {{ $lieferant->lieferantenname }} ({{ $lieferant->lieferantennummer }})
                            </flux:select.option>
                        @endforeach
                    </flux:select>
                    <flux:error name="manuelleZeileLieferantennummer" />
                </flux:field>
            @endif

            <div class="grid gap-3 md:grid-cols-2">
                <div>
                    <flux:input type="date" wire:model="manuelleZeileDatum" label="Angebotsdatum" required />
                    <flux:error name="manuelleZeileDatum" />
                </div>
                <div>
                    <flux:input wire:model="manuelleZeileNummer" label="Angebots-Nr." required />
                    <flux:error name="manuelleZeileNummer" />
                </div>
                <div>
                    <flux:input type="number" step="0.01" min="0" wire:model="manuelleZeileNetto" label="Netto (€)" required />
                    <flux:error name="manuelleZeileNetto" />
                </div>
                <div>
                    <flux:input
                        type="number"
                        step="0.01"
                        min="0"
                        wire:model="manuelleZeileBrutto"
                        :label="$bestellung->nutztAuftragsvorlage() ? 'Brutto (€)' : 'Brutto (€, optional)'"
                        :required="$bestellung->nutztAuftragsvorlage()"
                    />
                    <flux:error name="manuelleZeileBrutto" />
                </div>
                @unless ($bestellung->nutztAuftragsvorlage())
                    <div class="md:col-span-2">
                        <flux:textarea wire:model="manuelleZeileFreitext" label="Freitext" rows="3" />
                        <flux:error name="manuelleZeileFreitext" />
                    </div>
                @endunless
            </div>

            <div class="mt-6 flex justify-end gap-2">
                <flux:modal.close>
                    <flux:button variant="ghost">Abbrechen</flux:button>
                </flux:modal.close>
                <flux:button variant="primary" icon="check" wire:click="manuelleAuftragszeileSpeichern">
                    Zeile anlegen
                </flux:button>
            </div>
        </flux:modal>
    </x-intranet-app-bestellungen::bestellungen-layout>
</div>
