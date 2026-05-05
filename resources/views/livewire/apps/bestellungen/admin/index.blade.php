<div>
    <x-intranet-app-bestellungen::bestellungen-layout heading="Bestellungen-Administration" subheading="Wertgrenzen, Angebotsregeln, Stammdaten und Monitoring">
        <flux:tab.group>
            <flux:tabs wire:model.live="activeTab">
                <flux:tab name="wertgrenzen" icon="adjustments-horizontal">Wertgrenzen &amp; Freigaben</flux:tab>
                <flux:tab name="angebote" icon="check-badge">Angebotsregeln</flux:tab>
                <flux:tab name="arten" icon="rectangle-stack">Bestell-Arten</flux:tab>
                <flux:tab name="anlagen" icon="paper-clip">Anlagen</flux:tab>
                <flux:tab name="stammdaten" icon="database">Stammdaten</flux:tab>
                <flux:tab name="monitoring" icon="eye">Monitoring</flux:tab>
                <flux:tab name="settings" icon="cog-6-tooth">Allgemeine Einstellungen</flux:tab>
            </flux:tabs>

            <flux:tab.panel name="wertgrenzen">
                <livewire:intranet-app-bestellungen::apps.bestellungen.admin.wertgrenzen-editor />
            </flux:tab.panel>

            <flux:tab.panel name="angebote">
                <livewire:intranet-app-bestellungen::apps.bestellungen.admin.angebotsregeln-editor />
            </flux:tab.panel>

            <flux:tab.panel name="arten">
                <livewire:intranet-app-bestellungen::apps.bestellungen.admin.arten-editor />
            </flux:tab.panel>

            <flux:tab.panel name="anlagen">
                <livewire:intranet-app-bestellungen::apps.bestellungen.admin.anlagen-editor />
            </flux:tab.panel>

            <flux:tab.panel name="stammdaten">
                <livewire:intranet-app-bestellungen::apps.bestellungen.admin.stammdaten />
            </flux:tab.panel>

            <flux:tab.panel name="monitoring">
                <livewire:intranet-app-bestellungen::apps.bestellungen.admin.monitoring />
            </flux:tab.panel>

            <flux:tab.panel name="settings">
                @livewire('intranet-app-base::admin-settings', [
                    'appIdentifier' => 'bestellungen',
                    'settingsModelClass' => \Hwkdo\IntranetAppBestellungen\Models\IntranetAppBestellungenSettings::class,
                    'appSettingsClass' => \Hwkdo\IntranetAppBestellungen\Data\AppSettings::class,
                ])
            </flux:tab.panel>
        </flux:tab.group>
    </x-intranet-app-bestellungen::bestellungen-layout>
</div>
