<div class="space-y-4">
    @if ($bestellung->istAuftragsvergabe())
        @if ($this->kannBearbeiten())
            <flux:radio.group wire:model.live="auftragModus" label="Auftragsschein" variant="segmented">
                <flux:radio value="tabelle" label="Freitext je Zeile" />
                <flux:radio value="vorlage" label="Aus Vorlage generieren" />
            </flux:radio.group>
        @else
            <flux:text>
                Auftragsschein: {{ $bestellung->auftrag_modus?->label() ?? 'Freitext je Zeile' }}
            </flux:text>
        @endif
    @endif

    @if ($bestellung->nutztAuftragsvorlage())
        <flux:callout icon="document-text" variant="secondary">
            Das Anschreiben wird aus den Angebotsdaten erzeugt. Sie tragen nur die Ausführungsfrist ein.
        </flux:callout>

        @if ($this->kannBearbeiten())
            @php
                $schreibensLuecken = $this->auftragsschreibenFehlendeAngaben();
            @endphp
            @if ($schreibensLuecken !== [])
                <flux:callout icon="exclamation-triangle" variant="warning">
                    Für das Schreiben fehlen noch: {{ implode(', ', $schreibensLuecken) }}.
                </flux:callout>
            @endif

            <div class="grid gap-3 md:grid-cols-2">
                <flux:field>
                    <flux:label>Ausführungsfrist</flux:label>
                    <flux:date-picker wire:model="ausfuehrungsfrist" locale="de-DE" />
                    <flux:error name="ausfuehrungsfrist" />
                </flux:field>
            </div>

            <div class="space-y-3 rounded-lg border border-zinc-200 p-4 dark:border-zinc-700">
                <div class="flex flex-wrap items-center gap-2">
                    <flux:heading size="sm">Ansprechpartner beim Auftragnehmer</flux:heading>
                    @if ($this->auftragnehmerAusAngebotErkannt())
                        <flux:badge size="sm" color="sky">aus Angebot erkannt</flux:badge>
                    @endif
                </div>
                <flux:text class="text-zinc-500">Vorbelegt aus dem Angebot, bitte prüfen. Leer lassen, wenn unbekannt.</flux:text>
                <div class="grid gap-3 md:grid-cols-3">
                    <div>
                        <flux:select wire:model="auftragnehmerAnrede" label="Anrede" placeholder="Keine Angabe" clearable>
                            <flux:select.option value="herr">Herr</flux:select.option>
                            <flux:select.option value="frau">Frau</flux:select.option>
                        </flux:select>
                        <flux:error name="auftragnehmerAnrede" />
                    </div>
                    <div>
                        <flux:input wire:model="auftragnehmerAnsprechpartner" label="Name" />
                        <flux:error name="auftragnehmerAnsprechpartner" />
                    </div>
                    <div>
                        <flux:input type="email" wire:model="auftragnehmerEmail" label="E-Mail" />
                        <flux:error name="auftragnehmerEmail" />
                    </div>
                </div>
            </div>

            <div class="flex flex-wrap justify-end gap-2">
                <flux:button
                    size="sm"
                    icon="eye"
                    :href="route('apps.bestellungen.pdf.inline', ['bestellung' => $bestellung, 'typ' => 'auftragsvergabe'])"
                    target="_blank"
                >
                    Vorschau
                </flux:button>
                <flux:button size="sm" variant="primary" icon="check" wire:click="auftragsschreibenSpeichern">
                    Auftragsschreiben speichern
                </flux:button>
            </div>
        @else
            <dl class="grid gap-2 text-sm md:grid-cols-2">
                <div>
                    <dt class="text-zinc-500">Ausführungsfrist</dt>
                    <dd>{{ $bestellung->ausfuehrungsfrist?->format('d.m.Y') ?? '—' }}</dd>
                </div>
                <div>
                    <dt class="text-zinc-500">Ansprechpartner</dt>
                    <dd>
                        {{ trim(collect([$bestellung->auftragnehmer_anrede === 'herr' ? 'Herr' : ($bestellung->auftragnehmer_anrede === 'frau' ? 'Frau' : null), $bestellung->auftragnehmer_ansprechpartner])->filter()->implode(' ')) ?: '—' }}
                        @if (filled($bestellung->auftragnehmer_email))
                            <span class="text-zinc-500">({{ $bestellung->auftragnehmer_email }})</span>
                        @endif
                    </dd>
                </div>
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
        <flux:text class="text-zinc-500">Noch keine Auftragszeilen. Wählen Sie hochgeladene Angebote aus oder erfassen Sie eine Zeile manuell.</flux:text>
    @else
        <div class="space-y-4">
            @foreach ($auftragszeilenDraft as $idx => $zeile)
                <div wire:key="auftragszeile-{{ $zeile['id'] }}" class="rounded-lg border border-zinc-200 p-4 dark:border-zinc-700">
                    <div class="mb-3 flex flex-wrap items-center justify-between gap-2">
                        <div class="flex items-center gap-2">
                            <flux:heading size="sm">Zeile {{ $zeile['nr'] }}</flux:heading>
                            @if ($zeile['angebot_id'])
                                <flux:badge size="sm" color="sky">aus Angebot</flux:badge>
                            @else
                                <flux:badge size="sm" color="zinc">manuell</flux:badge>
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
                                <flux:input type="date" wire:model="auftragszeilenDraft.{{ $idx }}.angebotsdatum" label="Angebotsdatum" />
                                <flux:error name="auftragszeilenDraft.{{ $idx }}.angebotsdatum" />
                            </div>
                            <div>
                                <flux:input wire:model="auftragszeilenDraft.{{ $idx }}.angebotsnummer" label="Angebots-Nr." />
                                <flux:error name="auftragszeilenDraft.{{ $idx }}.angebotsnummer" />
                            </div>
                            <div>
                                <flux:input type="number" step="0.01" min="0" wire:model="auftragszeilenDraft.{{ $idx }}.betrag_netto" label="Netto (€)" />
                                <flux:error name="auftragszeilenDraft.{{ $idx }}.betrag_netto" />
                            </div>
                            <div>
                                <flux:input
                                    type="number"
                                    step="0.01"
                                    min="0"
                                    wire:model="auftragszeilenDraft.{{ $idx }}.betrag_brutto"
                                    :label="$bestellung->nutztAuftragsvorlage() ? 'Brutto (€)' : 'Brutto (€, optional)'"
                                    :description="$bestellung->nutztAuftragsvorlage() ? 'Pflicht für das Auftragsschreiben, inklusive Umsatzsteuer.' : null"
                                />
                                <flux:error name="auftragszeilenDraft.{{ $idx }}.betrag_brutto" />
                            </div>
                            @unless ($bestellung->nutztAuftragsvorlage())
                                <div class="md:col-span-4">
                                    <flux:textarea
                                        wire:model="auftragszeilenDraft.{{ $idx }}.freitext"
                                        label="Freitext"
                                        description="Steht auf dem Auftragsschein unter dieser Zeile, zum Beispiel ein Hinweis zum Angebot."
                                        rows="3"
                                    />
                                    <flux:error name="auftragszeilenDraft.{{ $idx }}.freitext" />
                                </div>
                            @endunless
                        </div>
                        <div class="mt-3 flex justify-end">
                            <flux:button size="sm" variant="primary" icon="check" wire:click="auftragszeileSpeichern({{ $idx }})">
                                Zeile speichern
                            </flux:button>
                        </div>
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
