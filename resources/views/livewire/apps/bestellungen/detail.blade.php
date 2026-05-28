<div>
    <x-intranet-app-bestellungen::bestellungen-layout
        :heading="'Bestellung ' . $bestellung->nummer"
        :subheading="$bestellung->betreff ?? 'Detail'"
    >
        <div class="grid gap-6 lg:grid-cols-3">
            <flux:card class="lg:col-span-2">
                <div class="flex flex-wrap items-center justify-between gap-3 mb-4">
                    <div class="flex items-center gap-3">
                        <flux:badge :color="$bestellung->status?->color()">
                            {{ $bestellung->status?->label() }}
                        </flux:badge>
                        <flux:text class="text-zinc-500">{{ $bestellung->nummer }}</flux:text>
                        @if ($bestellung->istInD3())
                            @if ($this->d3OneUrl())
                                <a
                                    href="{{ $this->d3OneUrl() }}"
                                    target="_blank"
                                    rel="noopener noreferrer"
                                    class="inline-flex"
                                >
                                    <flux:badge color="sky" icon="cloud">in D3</flux:badge>
                                </a>
                            @else
                                <flux:badge color="sky" icon="cloud">in D3</flux:badge>
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
                        <flux:button size="sm" variant="ghost" icon="document-duplicate" wire:click="wiederholen">
                            Wiederholen
                        </flux:button>
                        @if ($this->kannEinreichen())
                            <flux:button size="sm" variant="primary" icon="paper-airplane" wire:click="einreichenModalOeffnen">
                                Zur Freigabe einreichen
                            </flux:button>
                        @elseif ($bestellung->status === \Hwkdo\IntranetAppBestellungen\Enums\BestellungStatus::Entwurf && $bestellung->user_id === auth()->id())
                            <flux:tooltip content="Angebotsvoraussetzungen auf dem Reiter „Angebote / Begründung“ erfüllen">
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
                            @if ($bestellung->istIntern())
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

                <flux:tab.group>
                    <flux:tabs wire:model.live="activeTab">
                        <flux:tab name="positionen" icon="list-bullet">Positionen</flux:tab>
                        <flux:tab name="angebote" icon="document-text">Angebote / Begründung</flux:tab>
                        <flux:tab name="d3" icon="cloud">D3-Dokumente</flux:tab>
                        <flux:tab name="notizen" icon="chat-bubble-left-ellipsis">Notizen</flux:tab>
                        <flux:tab name="verlauf" icon="clock">Verlauf</flux:tab>
                    </flux:tabs>

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
                                    <div wire:key="pos-draft-{{ $idx }}" class="grid gap-3 md:grid-cols-12 items-end rounded border border-zinc-200 p-3 dark:border-zinc-700">
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
                                                            class="h-12 w-16 rounded border border-zinc-200 dark:border-zinc-700"
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

                    <flux:tab.panel name="angebote">
                        @php($angebotsAuswertung = $this->angebotsregelAuswertung)
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

                        @if ($this->kannAngeboteErfassen())
                            @if ($angebotsAuswertung->hatAusnahmeBegruendung)
                                <flux:callout icon="information-circle" variant="secondary" class="mb-4">
                                    Es ist bereits eine Ausnahme-Begründung erfasst. Weitere Vergleichsangebote sind optional.
                                </flux:callout>
                            @endif

                            <form wire:submit="angebotSpeichern" class="space-y-3 mb-6">
                                <flux:select wire:model.live="angebotTyp" label="Typ">
                                    <flux:select.option value="angebot">Vergleichsangebot (PDF)</flux:select.option>
                                    @if (! $angebotsAuswertung->hatAusnahmeBegruendung)
                                        <flux:select.option value="begruendung">Ausnahme-Begründung (Text)</flux:select.option>
                                    @endif
                                </flux:select>

                                @if ($angebotTyp === 'begruendung')
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
                                        <flux:input wire:model="angebotBetrag" type="number" step="0.01" label="Betrag (€, optional)" />
                                        <flux:input type="file" wire:model="angebotPdf" label="Angebots-PDF" accept="application/pdf,.pdf" class="md:col-span-2" />
                                        <flux:error name="angebotPdf" class="md:col-span-2" />
                                    </div>
                                @endif

                                <flux:button type="submit" variant="primary" icon="plus">
                                    {{ $angebotTyp === 'begruendung' ? 'Ausnahme-Begründung speichern' : 'Vergleichsangebot speichern' }}
                                </flux:button>
                            </form>
                        @else
                            <flux:text class="text-zinc-500 mb-4">
                                Angebote können im Status „Entwurf“ oder „Abgelehnt“ ergänzt werden.
                            </flux:text>
                        @endif

                        @if ($bestellung->angebote->isEmpty())
                            <flux:text class="text-zinc-500">Keine Angebote oder Begründungen erfasst.</flux:text>
                        @else
                            <flux:table>
                                <flux:table.columns>
                                    <flux:table.column>Typ</flux:table.column>
                                    <flux:table.column>Lieferant</flux:table.column>
                                    <flux:table.column>Nummer</flux:table.column>
                                    <flux:table.column class="text-right">Betrag</flux:table.column>
                                    <flux:table.column>D3</flux:table.column>
                                    <flux:table.column>PDF</flux:table.column>
                                </flux:table.columns>
                                <flux:table.rows>
                                    @foreach ($bestellung->angebote as $angebot)
                                        <flux:table.row :key="$angebot->id">
                                            <flux:table.cell>
                                                <flux:badge size="sm" :color="$angebot->typ === 'begruendung' ? 'amber' : 'sky'">
                                                    {{ $angebot->typ === 'begruendung' ? 'Begründung' : 'Angebot' }}
                                                </flux:badge>
                                            </flux:table.cell>
                                            <flux:table.cell>{{ $angebot->lieferantenname }}</flux:table.cell>
                                            <flux:table.cell>{{ $angebot->nummer }}</flux:table.cell>
                                            <flux:table.cell class="text-right">
                                                @if ($angebot->betrag)
                                                    {{ number_format((float) $angebot->betrag, 2, ',', '.') }} €
                                                @endif
                                            </flux:table.cell>
                                            <flux:table.cell>
                                                @if ($angebot->d3id)
                                                    <flux:badge color="emerald" size="sm">in D3</flux:badge>
                                                @else
                                                    <flux:badge color="zinc" size="sm">lokal (Push bei Bestellen)</flux:badge>
                                                @endif
                                            </flux:table.cell>
                                            <flux:table.cell>
                                                @if (filled($angebot->pdf_path) || ($angebot->typ === 'begruendung' && filled($angebot->begruendung)))
                                                    <flux:button
                                                        size="sm"
                                                        variant="ghost"
                                                        icon="document-text"
                                                        :href="route('apps.bestellungen.angebot.pdf.inline', ['bestellung' => $bestellung, 'angebot' => $angebot])"
                                                        target="_blank"
                                                        rel="noopener noreferrer"
                                                    >
                                                        {{ $angebot->typ === 'begruendung' ? 'Begründung anzeigen' : 'Angebot anzeigen' }}
                                                    </flux:button>
                                                @else
                                                    <flux:text class="text-zinc-400 text-sm">—</flux:text>
                                                @endif
                                            </flux:table.cell>
                                        </flux:table.row>
                                    @endforeach
                                </flux:table.rows>
                            </flux:table>
                        @endif
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
                                <flux:text class="text-zinc-500">Keine D3-Dokumente zur BEN gefunden.</flux:text>
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
                                                        <a href="{{ $doc['url'] }}" target="_blank" rel="noopener noreferrer" class="text-sky-600 hover:underline">
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
                            <flux:text class="text-zinc-500">Noch keine Notizen.</flux:text>
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
                            <flux:text class="text-zinc-500">Noch keine Aktivitäten.</flux:text>
                        @else
                            <ol class="relative border-l-2 border-zinc-200 dark:border-zinc-700 ml-4 space-y-4">
                                @foreach ($bestellung->aktionen as $aktion)
                                    <li wire:key="aktion-{{ $aktion->id }}" class="ml-4">
                                        <span class="absolute -left-2 mt-1 h-3 w-3 rounded-full bg-sky-500"></span>
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
            </flux:card>

            <flux:card>
                <flux:heading size="lg" class="mb-3">Stammdaten</flux:heading>
                <dl class="space-y-2 text-sm">
                    @if ($bestellung->projekt)
                        <div>
                            <dt class="text-zinc-500">Projekt</dt>
                            <dd>
                                <a
                                    href="{{ route('apps.bestellungen.projekte.detail', $bestellung->projekt) }}"
                                    wire:navigate
                                    class="text-blue-600 hover:underline dark:text-blue-400"
                                >
                                    {{ $bestellung->projekt->name }}
                                </a>
                            </dd>
                        </div>
                    @endif
                    <div>
                        <dt class="text-zinc-500">Art</dt>
                        <dd>{{ $bestellung->typ?->label() ?? $bestellung->typ }}</dd>
                    </div>
                    @if ($bestellung->istIntern() && $bestellung->internerEmpfaenger)
                        <div>
                            <dt class="text-zinc-500">Interner Empfänger</dt>
                            <dd>{{ $bestellung->internerEmpfaenger->vorname }} {{ $bestellung->internerEmpfaenger->nachname }}</dd>
                        </div>
                    @endif
                    <div>
                        <dt class="text-zinc-500">Lieferant{{ $bestellung->benoetigtFinalenLieferantenVorD3() ? ' (vorläufig)' : '' }}</dt>
                        <dd>{{ $bestellung->lieferantenname }} ({{ $bestellung->lieferantennummer }})</dd>
                    </div>
                    <div>
                        <dt class="text-zinc-500">Kostenstelle</dt>
                        <dd>{{ $bestellung->kostenstelle }}</dd>
                    </div>
                    <div>
                        <dt class="text-zinc-500">Haushaltsjahr</dt>
                        <dd>{{ $bestellung->haushaltsjahr }}</dd>
                    </div>
                    <div>
                        <dt class="text-zinc-500">Ersteller</dt>
                        <dd>{{ optional($bestellung->user)->name }}</dd>
                    </div>
                    <div>
                        <dt class="text-zinc-500">Aktueller Freigeber</dt>
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
                        <dt class="text-zinc-500">Besteller</dt>
                        <dd>{{ optional($bestellung->besteller)->name ?? optional($bestellung->user)->name ?? '—' }}</dd>
                    </div>
                    <div>
                        <dt class="text-zinc-500">Gesamtbetrag</dt>
                        <dd class="text-lg font-semibold text-emerald-600">
                            {{ number_format((float) $bestellung->gesamtbetrag, 2, ',', '.') }} €
                        </dd>
                    </div>
                    @if ($bestellung->d3id)
                        <div>
                            <dt class="text-zinc-500">D3-ID</dt>
                            <dd>
                                @if ($this->d3OneUrl())
                                    <a
                                        href="{{ $this->d3OneUrl() }}"
                                        target="_blank"
                                        rel="noopener noreferrer"
                                        class="text-sky-600 hover:underline"
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
                            <dt class="text-zinc-500">Erstellt aus Vorlage</dt>
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
            </flux:card>
        </div>

        <flux:modal name="freigeben-modal" :show="false">
            <form wire:submit="freigeben" class="space-y-4">
                <flux:heading size="lg">Bestellung freigeben</flux:heading>
                <flux:text>{{ $bestellung->nummer }} – {{ number_format((float) $bestellung->gesamtbetrag, 2, ',', '.') }} €</flux:text>
                <flux:textarea wire:model="freigabeNachricht" label="Optionale Nachricht" rows="2" />
                <div class="flex justify-end gap-2">
                    <flux:modal.close>
                        <flux:button type="button" variant="ghost">Abbrechen</flux:button>
                    </flux:modal.close>
                    <flux:button type="submit" variant="primary" icon="check">Freigeben</flux:button>
                </div>
            </form>
        </flux:modal>

        <flux:modal name="einreichen-modal" :show="false">
            <form wire:submit="einreichen" class="space-y-4">
                <flux:heading size="lg">Zur Freigabe einreichen</flux:heading>
                <flux:text>{{ $bestellung->nummer }} – {{ number_format((float) $bestellung->gesamtbetrag, 2, ',', '.') }} €</flux:text>
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
                @foreach ($this->einreichFreigeberHinweise as $hinweis)
                    <flux:callout icon="information-circle">{{ $hinweis }}</flux:callout>
                @endforeach
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
    </x-intranet-app-bestellungen::bestellungen-layout>
</div>
