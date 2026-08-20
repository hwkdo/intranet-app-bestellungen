<?php

declare(strict_types=1);

namespace Hwkdo\IntranetAppBestellungen\Notifications;

use Hwkdo\IntranetAppBase\Notifications\IntranetNotification;
use Hwkdo\IntranetAppBestellungen\IntranetAppBestellungen;
use Hwkdo\IntranetAppBestellungen\Models\Bestellung;
use Illuminate\Notifications\Messages\MailMessage;
use NotificationChannels\WebPush\WebPushMessage;

class BestellungFreigegebenNotification extends IntranetNotification
{
    public function __construct(
        public readonly Bestellung $bestellung,
    ) {
        parent::__construct();
    }

    public function typeKey(): string
    {
        return 'bestellungen.order_approved';
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('Bestellung freigegeben: '.$this->bestellung->nummer)
            ->line('Ihre Bestellung '.$this->bestellung->nummer.' wurde freigegeben und kann jetzt bestellt werden.')
            ->action('Bestellung öffnen', route('apps.bestellungen.detail', $this->bestellung));
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return $this->inboxPayload(
            title: 'Bestellung freigegeben',
            body: $this->bestellung->nummer.' kann jetzt bestellt werden.',
            url: route('apps.bestellungen.detail', $this->bestellung),
            appIdentifier: IntranetAppBestellungen::identifier(),
        );
    }

    public function toWebPush(object $notifiable, mixed $notification): WebPushMessage
    {
        return (new WebPushMessage)
            ->title('Bestellung freigegeben')
            ->body($this->bestellung->nummer.' kann jetzt bestellt werden.')
            ->data(['url' => route('apps.bestellungen.detail', $this->bestellung)]);
    }

    public function toTeams(object $notifiable): array
    {
        return [
            'preview' => 'Bestellung '.$this->bestellung->nummer.' wurde freigegeben.',
            'topic' => 'Bestellungen',
            'url' => route('apps.bestellungen.detail', $this->bestellung),
        ];
    }
}
