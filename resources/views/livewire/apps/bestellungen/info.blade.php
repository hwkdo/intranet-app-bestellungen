<div>
    <x-intranet-app-bestellungen::bestellungen-layout heading="App-Info" subheading="Installierte Version und Release-Historie">
        <x-intranet-app-bestellungen::hwk-card title="Version" icon="information-circle" description="Installierte Version und Release-Historie dieser App." padded>
            @livewire('intranet-app-base::app-info', ['appIdentifier' => 'bestellungen'])
        </x-intranet-app-bestellungen::hwk-card>
    </x-intranet-app-bestellungen::bestellungen-layout>
</div>
