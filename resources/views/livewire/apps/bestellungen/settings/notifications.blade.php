<div>
    <x-intranet-app-bestellungen::bestellungen-layout heading="Benachrichtigungen" subheading="Legen Sie fest, worüber Sie in dieser App informiert werden.">
        <section class="glass-card overflow-hidden p-5">
            @livewire('intranet-app-base::notification-settings', ['appIdentifier' => 'bestellungen'])
        </section>
    </x-intranet-app-bestellungen::bestellungen-layout>
</div>
