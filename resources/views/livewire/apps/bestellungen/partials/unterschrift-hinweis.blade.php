@if ($this->eigeneUnterschriftDataUri)
    <div class="rounded-xl bg-white p-3 ring-1 ring-[#d0e3f9] dark:bg-white/5 dark:ring-white/10">
        <img src="{{ $this->eigeneUnterschriftDataUri }}" alt="Ihre Unterschrift" class="h-16 w-auto">
        <flux:text class="mt-2 text-slate-600 dark:text-white/70">Mit der Freigabe unterschreiben Sie den Auftragsschein verbindlich.</flux:text>
    </div>
@else
    <flux:callout icon="information-circle" variant="warning">
        <flux:callout.text>
            Sie haben keine registrierte Unterschrift. Nach der Freigabe muss der Schein ausgedruckt, von Ihnen unterschrieben und als Scan hochgeladen werden. Die Registrierung erfolgt an der Zentrale.
        </flux:callout.text>
    </flux:callout>
@endif
