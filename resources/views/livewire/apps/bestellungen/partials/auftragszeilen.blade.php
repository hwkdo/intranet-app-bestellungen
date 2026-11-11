<div class="space-y-4">
    <flux:callout icon="information-circle" variant="secondary">
        Jede Zeile entspricht einem Angebot. Der Anbieter des ersten übernommenen Angebots wird der Auftragnehmer. Der Freitext erscheint auf dem Auftragsschein direkt unter der jeweiligen Zeile.
    </flux:callout>

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
                                <flux:input type="number" step="0.01" min="0" wire:model="auftragszeilenDraft.{{ $idx }}.betrag_brutto" label="Brutto (€, optional)" />
                                <flux:error name="auftragszeilenDraft.{{ $idx }}.betrag_brutto" />
                            </div>
                            <div class="md:col-span-4">
                                <flux:textarea
                                    wire:model="auftragszeilenDraft.{{ $idx }}.freitext"
                                    label="Freitext"
                                    description="Steht auf dem Auftragsschein unter dieser Zeile, zum Beispiel ein Hinweis zum Angebot."
                                    rows="3"
                                />
                                <flux:error name="auftragszeilenDraft.{{ $idx }}.freitext" />
                            </div>
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
                            @if (filled($zeile['freitext']))
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
