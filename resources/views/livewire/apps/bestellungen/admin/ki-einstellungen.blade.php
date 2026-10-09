<flux:card class="glass-card">
    <flux:heading size="lg" class="mb-2">KI-Gateway</flux:heading>
    <flux:text class="mb-6 text-sm text-zinc-500">
        Provider und Modell für die Angebotsextraktion. Leere Felder nutzen die globalen Einstellungen unter Manager → Base Settings.
    </flux:text>

    <flux:callout class="mb-4" icon="information-circle">
        <flux:callout.heading>Globale KI-Standards</flux:callout.heading>
        <flux:callout.text>
            Text: <strong>{{ $this->baseAiTextSummary }}</strong><br>
            Bilder: <strong>{{ $this->baseAiImageSummary }}</strong>
        </flux:callout.text>
    </flux:callout>

    <x-intranet-app-base::admin-ai-settings
        ai-text-provider-override="aiTextProviderOverride"
        ai-text-model-override="aiTextModelOverride"
        ai-image-provider-override="aiImageProviderOverride"
        ai-image-model-override="aiImageModelOverride"
    />

    <flux:text class="mt-4 text-sm text-zinc-500">
        Aktuell wirksam für die Angebotsextraktion:
        <strong>{{ $this->effectiveAiTextSummary }}</strong>.
        Bilder: <strong>{{ $this->effectiveAiImageSummary }}</strong>.
    </flux:text>

    <div class="mt-6 flex justify-end">
        <flux:button wire:click="save" variant="primary">
            KI-Einstellungen speichern
        </flux:button>
    </div>
</flux:card>
