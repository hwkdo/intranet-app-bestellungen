<section class="glass-card overflow-hidden p-5">
    <flux:heading size="lg" class="mb-2">KI-Gateway</flux:heading>
    <flux:text class="mb-6 text-sm text-zinc-500">
        Provider und Modell für die Angebotsextraktion. Leere Felder nutzen die globalen Einstellungen unter Manager → Base Settings.
    </flux:text>

    <div class="mb-4 rounded-xl bg-[#d0e3f9]/30 px-4 py-3 text-sm text-slate-600 dark:bg-white/10 dark:text-white/70">
        <p class="font-medium text-[#073070] dark:text-white">Globale KI-Standards</p>
        <p class="mt-1">Text: <strong class="text-[#073070] dark:text-white">{{ $this->baseAiTextSummary }}</strong></p>
        <p>Bilder: <strong class="text-[#073070] dark:text-white">{{ $this->baseAiImageSummary }}</strong></p>
        <p>Klassifikation: <strong class="text-[#073070] dark:text-white">{{ $this->baseAiClassificationSummary }}</strong></p>
    </div>

    <x-intranet-app-base::admin-ai-settings
        ai-text-provider-override="aiTextProviderOverride"
        ai-text-model-override="aiTextModelOverride"
        ai-image-provider-override="aiImageProviderOverride"
        ai-image-model-override="aiImageModelOverride"
        ai-image-edit-provider-override="aiImageEditProviderOverride"
        ai-image-edit-model-override="aiImageEditModelOverride"
        :image-provider="$aiImageProviderOverride"
        :image-edit-provider="$aiImageEditProviderOverride"
        ai-classification-provider-override="aiClassificationProviderOverride"
        ai-classification-model-override="aiClassificationModelOverride"
    />

    <flux:text class="mt-4 text-sm text-zinc-500">
        Aktuell wirksam für die Angebotsextraktion:
        <strong>{{ $this->effectiveAiTextSummary }}</strong>.
        Bilder: <strong>{{ $this->effectiveAiImageSummary }}</strong>.
        Klassifikation: <strong>{{ $this->effectiveAiClassificationSummary }}</strong>.
    </flux:text>

    <div class="mt-6 flex justify-end">
        <flux:button wire:click="save" variant="primary">
            KI-Einstellungen speichern
        </flux:button>
    </div>
</section>
