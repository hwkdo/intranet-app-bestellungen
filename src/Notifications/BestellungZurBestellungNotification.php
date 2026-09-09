<?php

declare(strict_types=1);

namespace Hwkdo\IntranetAppBestellungen\Notifications;

use Hwkdo\IntranetAppBase\Notifications\IntranetNotification;
use Hwkdo\IntranetAppBestellungen\IntranetAppBestellungen;
use Hwkdo\IntranetAppBestellungen\Models\Bestellung;
use Illuminate\Notifications\Messages\MailMessage;
use NotificationChannels\WebPush\WebPushMessage;

class BestellungZurBestellungNotification extends IntranetNotification
{
    public function __construct(
        public readonly Bestellung $bestellung,
    ) {
        parent::__construct();
    }

    public function typeKey(): string
    {
        return 'bestellungen.ready_to_order';
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('Interne Bestellung ausführen: '.$this->bestellung->nummer)
            ->line('Die interne Bestellung '.$this->bestellung->nummer.' wurde freigegeben und kann jetzt bestellt werden.')
            ->action('Bestellung öffnen', route('apps.bestellungen.detail', [
                'bestellung' => $this->bestellung,
                'aktion' => 'bestellen',
            ]));
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return $this->inboxPayload(
            title: 'Interne Bestellung ausführen',
            body: $this->bestellung->nummer.' ist freigegeben und wartet auf Bestellung.',
            url: route('apps.bestellungen.detail', [
                'bestellung' => $this->bestellung,
                'aktion' => 'bestellen',
            ]),
            appIdentifier: IntranetAppBestellungen::identifier(),
        );
    }

    public function toWebPush(object $notifiable, mixed $notification): WebPushMessage
    {
        return (new WebPushMessage)
            ->title('Interne Bestellung ausführen')
            ->body($this->bestellung->nummer.' ist freigegeben und wartet auf Bestellung.')
            ->data(['url' => route('apps.bestellungen.detail', [
                'bestellung' => $this->bestellung,
                'aktion' => 'bestellen',
            ])]);
    }

    public function toTeams(object $notifiable): array
    {
        return [
            'preview' => 'Interne Bestellung '.$this->bestellung->nummer.' kann bestellt werden.',
            'topic' => 'Bestellungen',
            'url' => route('apps.bestellungen.detail', [
                'bestellung' => $this->bestellung,
                'aktion' => 'bestellen',
            ]),
        ];
    }
}
