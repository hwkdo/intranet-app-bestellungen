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
                            <flux:badge color="sky" icon="cloud">in D3</flux:badge>
                        @endif
                    </div>
                    <div class="flex flex-wrap gap-2">
                        <flux:button
                            size="sm"
                            variant="ghost"
                            icon="document-text"
                            target="_blank"
                            :href="route('apps.bestellungen.pdf.inline', $bestellung)"
                        >
                            Bestellschein
                        </flux:button>
                        <flux:button size="sm" variant="ghost" icon="document-duplicate" wire:click="wiederholen">
                            Wiederholen
                        </flux:button>
                        @if ($this->kannEinreichen())
                            <flux:button size="sm" variant="primary" icon="paper-airplane" wire:click="einreichen">
                                Zur Freigabe einreichen
                            </flux:button>
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
                            <flux:button size="sm" variant="primary" icon="paper-airplane" wire:click="bestellen" wire:confirm="Bestellung an D3 senden?">
                                Bestellen
                            </flux:button>
                        @endif
                    </div>
                </div>

                <flux:tab.group>
                    <flux:tabs wire:model.live="activeTab">
                        <flux:tab name="positionen" icon="list-bullet">Positionen</flux:tab>
                        <flux:tab name="angebote" icon="document-text">Angebote / Begründung</flux:tab>
                        <flux:tab name="notizen" icon="chat-bubble-left-ellipsis">Notizen</flux:tab>
                        <flux:tab name="verlauf" icon="clock">Verlauf</flux:tab>
                    </flux:tabs>

                    <flux:tab.panel name="positionen">
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
                    </flux:tab.panel>

                    <flux:tab.panel name="angebote">
                        <form wire:submit="angebotSpeichern" class="space-y-3 mb-6">
                            <div class="grid gap-3 md:grid-cols-2">
                                <flux:select wire:model="angebotTyp" label="Typ">
                                    <flux:select.option value="angebot">Vergleichsangebot</flux:select.option>
                                    <flux:select.option value="begruendung">Ausnahme-Begründung</flux:select.option>
                                </flux:select>
                                <flux:input wire:model="angebotLieferant" label="Lieferant" />
                                <flux:input wire:model="angebotNummer" label="Angebots-Nr." />
                                <flux:input wire:model="angebotBetrag" type="number" step="0.01" label="Betrag (€)" />
                                <flux:textarea wire:model="angebotBegruendung" label="Begründung" rows="3" class="md:col-span-2" />
                                <flux:input type="file" wire:model="angebotPdf" label="PDF (optional)" class="md:col-span-2" />
                            </div>
                            <flux:button type="submit" variant="primary" icon="plus">Angebot/Begründung speichern</flux:button>
                        </form>

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
                                                    <flux:badge color="emerald" size="sm">übertragen</flux:badge>
                                                @else
                                                    <flux:badge color="zinc" size="sm">offen</flux:badge>
                                                @endif
                                            </flux:table.cell>
                                        </flux:table.row>
                                    @endforeach
                                </flux:table.rows>
                            </flux:table>
                        @endif
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
                    <div>
                        <dt class="text-zinc-500">Lieferant</dt>
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
                            <dd>{{ $bestellung->d3id }}</dd>
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
    </x-intranet-app-bestellungen::bestellungen-layout>
</div>
