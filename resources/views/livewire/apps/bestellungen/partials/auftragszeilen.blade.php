<div class="space-y-4">
    @if ($bestellung->istAuftragsvergabe())
        @if ($this->kannBearbeiten())
            <flux:select wire:model.live="auftragsscheinWahl" label="Auftragsschein">
                <flux:select.option value="tabelle">Freitext je Zeile</flux:select.option>
                @foreach ($this->auftragsvorlagen as $vorlage)
                    <flux:select.option value="vorlage-{{ $vorlage->id }}">{{ $vorlage->name }}</flux:select.option>
                @endforeach
            </flux:select>
        @else
            <flux:text>
                Auftragsschein: {{ $bestellung->nutztAuftragsvorlage() ? ($bestellung->auftragsvorlage?->name ?? 'Aus Vorlage') : 'Freitext je Zeile' }}
            </flux:text>
        @endif
    @endif

    @if ($bestellung->nutztAuftragsvorlage())
        @if ($auftragszeilenDraft === [])
            <flux:callout icon="information-circle" variant="warning">
                Wählen Sie zuerst das Angebot, das beauftragt werden soll.
            </flux:callout>
            @if ($this->kannBearbeiten())
                <div class="flex justify-end">
                    <flux:button size="sm" icon="eye" disabled>Vorschau</flux:button>
                </div>
            @endif
        @elseif ($this->kannBearbeiten())
            <section class="space-y-4 rounded-lg border border-[#d0e3f9] p-4 dark:border-white/10">
                <div>
                    <flux:heading size="sm">Anschreiben</flux:heading>
                    <flux:text class="text-zinc-500">
                        @if ($this->vorlageVerwendet('freitext'))
                            Den Brieftext schreiben Sie selbst. **fett** und Leerzeilen werden übernommen.
                        @else
                            Die gewählte Vorlage erzeugt den Brieftext. Ergänzen Sie die abgefragten Angaben.
                        @endif
                    </flux:text>
                </div>

                @php
                    $schreibensLuecken = $this->auftragsschreibenFehlendeAngaben();
                @endphp
                @if ($schreibensLuecken !== [])
                    <flux:callout icon="exclamation-triangle" variant="warning">
                        Für das Schreiben fehlen noch: {{ implode(', ', $schreibensLuecken) }}.
                    </flux:callout>
                @endif

                @if ($this->vorlageVerwendet('freitext'))
                    <div class="space-y-2">
                        <flux:textarea wire:model.live.debounce.400ms="vorlageFreitext" label="Brieftext" rows="10" />
                        <flux:error name="vorlageFreitext" />
                        @if ($this->vorlageFreitextIstGeaendert())
                            <div class="flex justify-end">
                                <flux:button size="sm" variant="ghost" icon="arrow-uturn-left" wire:click="vorlageFreitextVerwerfen">
                                    Brieftext rückgängig machen
                                </flux:button>
                            </div>
                        @endif
                    </div>
                @endif

                @if ($this->vorlageVerwendet('ausfuehrungsfrist'))
                    <div class="grid gap-3 md:grid-cols-2">
                        <flux:field>
                            <flux:label>Ausführungsfrist</flux:label>
                            <flux:date-picker wire:model.live="ausfuehrungsfrist" locale="de-DE" />
                            <flux:error name="ausfuehrungsfrist" />
                        </flux:field>
                    </div>
                @endif

                @if ($this->vorlageVerwendet('ansprechpartner'))
                <div class="space-y-3">
                    <div class="flex flex-wrap items-center gap-2">
                        <flux:heading size="sm">Ansprechpartner beim Auftragnehmer</flux:heading>
                        @if ($this->auftragnehmerAusAngebotErkannt())
                            <flux:badge size="sm" color="zinc">aus Angebot erkannt</flux:badge>
                        @endif
                        @if (! $this->auftragnehmerIstGeaendert())
                            <flux:badge size="sm" color="zinc">Gespeichert</flux:badge>
                        @endif
                    </div>
                    <flux:text class="text-zinc-500">Steht im Brief unter „Zu Hd.“. Leer lassen, wenn unbekannt.</flux:text>
                    <div class="grid gap-3 md:grid-cols-3">
                        <div>
                            <flux:select wire:model.live="auftragnehmerAnrede" label="Anrede" placeholder="Keine Angabe" clearable>
                                <flux:select.option value="herr">Herr</flux:select.option>
                                <flux:select.option value="frau">Frau</flux:select.option>
                            </flux:select>
                            <flux:error name="auftragnehmerAnrede" />
                        </div>
                        <div>
                            <flux:input wire:model.live.debounce.400ms="auftragnehmerAnsprechpartner" label="Name" />
                            <flux:error name="auftragnehmerAnsprechpartner" />
                        </div>
                        <div>
                            <flux:input type="email" wire:model.live.debounce.400ms="auftragnehmerEmail" label="E-Mail" />
                            <flux:error name="auftragnehmerEmail" />
                        </div>
                    </div>
                    @if ($this->auftragnehmerIstGeaendert())
                        <div class="flex flex-wrap justify-end gap-2">
                            <flux:button size="sm" variant="ghost" icon="arrow-uturn-left" wire:click="auftragnehmerAenderungenVerwerfen">
                                Änderungen rückgängig machen
                            </flux:button>
                            <flux:button size="sm" variant="primary" icon="check" wire:click="auftragsschreibenSpeichern">
                                Änderung speichern
                            </flux:button>
                        </div>
                    @endif
                </div>
                @endif

                <div class="flex justify-end">
                    <flux:button
                        size="sm"
                        icon="eye"
                        :href="route('apps.bestellungen.pdf.inline', ['bestellung' => $bestellung, 'typ' => 'auftragsvergabe'])"
                        target="_blank"
                    >
                        Vorschau
                    </flux:button>
                </div>
            </section>
        @else
            <dl class="grid gap-2 text-sm md:grid-cols-2">
                @if ($this->vorlageVerwendet('freitext'))
                    <div class="md:col-span-2">
                        <dt class="text-zinc-500">Brieftext</dt>
                        <dd class="whitespace-pre-line">{{ data_get($bestellung->vorlage_werte, 'freitext') ?: '—' }}</dd>
                    </div>
                @endif
                @if ($this->vorlageVerwendet('ausfuehrungsfrist'))
                    <div>
                        <dt class="text-zinc-500">Ausführungsfrist</dt>
                        <dd>{{ $bestellung->ausfuehrungsfrist?->format('d.m.Y') ?? '—' }}</dd>
                    </div>
                @endif
                @if ($this->vorlageVerwendet('ansprechpartner'))
                <div>
                    <dt class="text-zinc-500">Ansprechpartner</dt>
                    <dd>
                        {{ trim(collect([$bestellung->auftragnehmer_anrede === 'herr' ? 'Herr' : ($bestellung->auftragnehmer_anrede === 'frau' ? 'Frau' : null), $bestellung->auftragnehmer_ansprechpartner])->filter()->implode(' ')) ?: '—' }}
                        @if (filled($bestellung->auftragnehmer_email))
                            <span class="text-zinc-500">({{ $bestellung->auftragnehmer_email }})</span>
                        @endif
                    </dd>
                </div>
                @endif
            </dl>
            <flux:button
                size="sm"
                icon="eye"
                :href="route('apps.bestellungen.pdf.inline', ['bestellung' => $bestellung, 'typ' => 'auftragsvergabe'])"
                target="_blank"
            >
                Vorschau
            </flux:button>
        @endif
    @else
        <flux:callout icon="information-circle" variant="secondary">
            Jede Zeile entspricht einem Angebot. Der Anbieter des ersten übernommenen Angebots wird der Auftragnehmer. Der Freitext erscheint auf dem Auftragsschein direkt unter der jeweiligen Zeile.
        </flux:callout>
    @endif

    <div>
        <flux:heading size="sm">Auftragszeilen</flux:heading>
        <flux:text class="text-zinc-500">
            @if ($this->kannBearbeiten())
                Eine übernommene Zeile ist bereits gespeichert. Der Button erscheint, sobald Sie etwas ändern.
            @else
                Beauftragte Angebote dieser Auftragsvergabe.
            @endif
        </flux:text>
    </div>

    @if ($this->kannBearbeiten())
        <div class="flex flex-wrap gap-2">
            <flux:button size="sm" icon="document-text" x-on:click="$flux.modal('angebote-waehlen').show()">
                Angebote auswählen
            </flux:button>
            <flux:button size="sm" icon="plus" variant="ghost" x-on:click="$flux.modal('zeile-manuell').show()">
                Zeile ohne Angebot
            </flux:button>
        </div>
    @endif

    @if ($auftragszeilenDraft === [])
        <x-intranet-app-bestellungen::hwk-empty bare icon="document-check" title="Keine Auftragszeilen">
            Noch keine Auftragszeilen. Wählen Sie hochgeladene Angebote aus oder erfassen Sie eine Zeile manuell.
        </x-intranet-app-bestellungen::hwk-empty>
    @else
        <div class="space-y-4">
            @foreach ($auftragszeilenDraft as $idx => $zeile)
                <div wire:key="auftragszeile-{{ $zeile['id'] }}" class="rounded-lg border border-[#d0e3f9] p-4 dark:border-white/10">
                    <div class="mb-3 flex flex-wrap items-center justify-between gap-2">
                        <div class="flex flex-wrap items-center gap-2">
                            <flux:heading size="sm">Zeile {{ $zeile['nr'] }}</flux:heading>
                            @if ($zeile['angebot_id'])
                                <flux:badge size="sm" color="zinc">aus Angebot</flux:badge>
                            @else
                                <flux:badge size="sm" color="zinc">manuell</flux:badge>
                            @endif
                            @if ($this->kannBearbeiten() && ! $this->auftragszeileIstGeaendert($idx))
                                <flux:badge size="sm" color="zinc">Gespeichert</flux:badge>
                            @endif
                        </div>
                        @if ($this->kannBearbeiten())
                            <div class="flex gap-1">
                                <flux:button size="xs" variant="ghost" icon="chevron-up" wire:click="auftragszeileVerschieben({{ $zeile['id'] }}, -1)" />
                                <flux:button size="xs" variant="ghost" icon="chevron-down" wire:click="auftragszeileVerschieben({{ $zeile['id'] }}, 1)" />
                                <flux:button
                                    size="xs"
                                    variant="danger"
                                    icon="trash"
                                    wire:click="auftragszeileEntfernen({{ $zeile['id'] }})"
                                    wire:confirm="Auftragszeile wirklich entfernen?"
                                />
                            </div>
                        @endif
                    </div>

                    @if ($this->kannBearbeiten())
                        <div class="grid gap-3 md:grid-cols-4">
                            <div>
                                <flux:input type="date" wire:model.live="auftragszeilenDraft.{{ $idx }}.angebotsdatum" label="Angebotsdatum" />
                                <flux:error name="auftragszeilenDraft.{{ $idx }}.angebotsdatum" />
                            </div>
                            <div>
                                <flux:input wire:model.live.debounce.400ms="auftragszeilenDraft.{{ $idx }}.angebotsnummer" label="Angebots-Nr." />
                                <flux:error name="auftragszeilenDraft.{{ $idx }}.angebotsnummer" />
                            </div>
                            <div>
                                <flux:input type="number" step="0.01" min="0" wire:model.live.debounce.400ms="auftragszeilenDraft.{{ $idx }}.betrag_netto" label="Netto (€)" />
                                <flux:error name="auftragszeilenDraft.{{ $idx }}.betrag_netto" />
                            </div>
                            <div>
                                <flux:input
                                    type="number"
                                    step="0.01"
                                    min="0"
                                    wire:model.live.debounce.400ms="auftragszeilenDraft.{{ $idx }}.betrag_brutto"
                                    :label="$bestellung->nutztAuftragsvorlage() ? 'Brutto (€)' : 'Brutto (€, optional)'"
                                    :description="$bestellung->nutztAuftragsvorlage() ? 'Pflicht für das Auftragsschreiben, inklusive Umsatzsteuer.' : null"
                                />
                                <flux:error name="auftragszeilenDraft.{{ $idx }}.betrag_brutto" />
                            </div>
                            @unless ($bestellung->nutztAuftragsvorlage())
                                <div class="md:col-span-4">
                                    <flux:textarea
                                        wire:model.live.debounce.400ms="auftragszeilenDraft.{{ $idx }}.freitext"
                                        label="Freitext"
                                        description="Steht auf dem Auftragsschein unter dieser Zeile, zum Beispiel ein Hinweis zum Angebot."
                                        rows="3"
                                    />
                                    <flux:error name="auftragszeilenDraft.{{ $idx }}.freitext" />
                                </div>
                            @endunless
                        </div>
                        @if ($this->auftragszeileIstGeaendert($idx))
                            <div class="mt-3 flex flex-wrap justify-end gap-2">
                                <flux:button size="sm" variant="ghost" icon="arrow-uturn-left" wire:click="auftragszeileAenderungenVerwerfen({{ $idx }})">
                                    Änderungen rückgängig machen
                                </flux:button>
                                <flux:button size="sm" variant="primary" icon="check" wire:click="auftragszeileSpeichern({{ $idx }})">
                                    Änderung speichern
                                </flux:button>
                            </div>
                        @endif
                    @else
                        <dl class="grid gap-2 text-sm md:grid-cols-4">
                            <div>
                                <dt class="text-zinc-500">Angebotsdatum</dt>
                                <dd>{{ filled($zeile['angebotsdatum']) ? \Illuminate\Support\Carbon::parse($zeile['angebotsdatum'])->format('d.m.Y') : '—' }}</dd>
                            </div>
                            <div>
                                <dt class="text-zinc-500">Angebots-Nr.</dt>
                                <dd>{{ $zeile['angebotsnummer'] ?: '—' }}</dd>
                            </div>
                            <div>
                                <dt class="text-zinc-500">Netto</dt>
                                <dd>{{ $zeile['betrag_netto'] !== null ? number_format((float) $zeile['betrag_netto'], 2, ',', '.').' €' : '—' }}</dd>
                            </div>
                            <div>
                                <dt class="text-zinc-500">Brutto</dt>
                                <dd>{{ $zeile['betrag_brutto'] !== null ? number_format((float) $zeile['betrag_brutto'], 2, ',', '.').' €' : '—' }}</dd>
                            </div>
                            @if (! $bestellung->nutztAuftragsvorlage() && filled($zeile['freitext']))
                                <div class="md:col-span-4">
                                    <dt class="text-zinc-500">Freitext</dt>
                                    <dd class="whitespace-pre-line">{{ $zeile['freitext'] }}</dd>
                                </div>
                            @endif
                        </dl>
                    @endif
                </div>
            @endforeach
        </div>

        <div class="text-right text-sm">
            <span class="text-zinc-500">Summe netto</span>
            <strong class="ml-2 text-emerald-600">{{ number_format((float) $bestellung->gesamtbetrag, 2, ',', '.') }} €</strong>
        </div>
    @endif
</div>
