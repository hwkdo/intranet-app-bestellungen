<div>
    <x-intranet-app-bestellungen::bestellungen-layout heading="Bestellungen – Meine Einstellungen" subheading="Persönliche Konfiguration">
        <flux:card>
            @livewire('intranet-app-base::user-settings', ['appIdentifier' => 'bestellungen'])
        </flux:card>
    </x-intranet-app-bestellungen::bestellungen-layout>
</div>
