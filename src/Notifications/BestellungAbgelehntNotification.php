<?php

declare(strict_types=1);

namespace Hwkdo\IntranetAppBestellungen\Notifications;

use Hwkdo\IntranetAppBase\Notifications\IntranetNotification;
use Hwkdo\IntranetAppBestellungen\IntranetAppBestellungen;
use Hwkdo\IntranetAppBestellungen\Models\Bestellung;
use Illuminate\Notifications\Messages\MailMessage;
use NotificationChannels\WebPush\WebPushMessage;

class BestellungAbgelehntNotification extends IntranetNotification
{
    public function __construct(
        public readonly Bestellung $bestellung,
        public readonly string $grund,
    ) {
        parent::__construct();
    }

    public function typeKey(): string
    {
        return 'bestellungen.order_rejected';
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('Bestellung abgelehnt: '.$this->bestellung->nummer)
            ->line('Ihre Bestellung '.$this->bestellung->nummer.' wurde abgelehnt.')
            ->line('Grund: '.$this->grund)
            ->action('Bestellung öffnen', route('apps.bestellungen.detail', $this->bestellung));
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return $this->inboxPayload(
            title: 'Bestellung abgelehnt',
            body: $this->bestellung->nummer.': '.$this->grund,
            url: route('apps.bestellungen.detail', $this->bestellung),
            appIdentifier: IntranetAppBestellungen::identifier(),
        );
    }

    public function toWebPush(object $notifiable, mixed $notification): WebPushMessage
    {
        return (new WebPushMessage)
            ->title('Bestellung abgelehnt')
            ->body($this->bestellung->nummer.': '.$this->grund)
            ->data(['url' => route('apps.bestellungen.detail', $this->bestellung)]);
    }

    public function toTeams(object $notifiable): array
    {
        return [
            'preview' => 'Bestellung '.$this->bestellung->nummer.' wurde abgelehnt.',
            'topic' => 'Bestellungen',
            'url' => route('apps.bestellungen.detail', $this->bestellung),
        ];
    }
}
