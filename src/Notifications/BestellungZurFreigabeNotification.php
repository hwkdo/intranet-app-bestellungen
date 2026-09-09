<?php

declare(strict_types=1);

namespace Hwkdo\IntranetAppBestellungen\Notifications;

use Hwkdo\IntranetAppBase\Notifications\IntranetNotification;
use Hwkdo\IntranetAppBestellungen\IntranetAppBestellungen;
use Hwkdo\IntranetAppBestellungen\Models\Bestellung;
use Illuminate\Notifications\Messages\MailMessage;
use NotificationChannels\WebPush\WebPushMessage;

class BestellungZurFreigabeNotification extends IntranetNotification
{
    public function __construct(
        public readonly Bestellung $bestellung,
    ) {
        parent::__construct();
    }

    public function typeKey(): string
    {
        return 'bestellungen.pending_approval';
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('Bestellung zur Freigabe: '.$this->bestellung->nummer)
            ->line('Ihnen wurde die Bestellung '.$this->bestellung->nummer.' zur Freigabe zugewiesen.')
            ->action('Bestellung öffnen', route('apps.bestellungen.detail', [
                'bestellung' => $this->bestellung,
                'aktion' => 'freigeben',
            ]));
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return $this->inboxPayload(
            title: 'Bestellung zur Freigabe',
            body: $this->bestellung->nummer.' wartet auf Ihre Freigabe.',
            url: route('apps.bestellungen.detail', [
                'bestellung' => $this->bestellung,
                'aktion' => 'freigeben',
            ]),
            appIdentifier: IntranetAppBestellungen::identifier(),
        );
    }

    public function toWebPush(object $notifiable, mixed $notification): WebPushMessage
    {
        return (new WebPushMessage)
            ->title('Bestellung zur Freigabe')
            ->body($this->bestellung->nummer.' wartet auf Ihre Freigabe.')
            ->data(['url' => route('apps.bestellungen.detail', [
                'bestellung' => $this->bestellung,
                'aktion' => 'freigeben',
            ])]);
    }

    public function toTeams(object $notifiable): array
    {
        return [
            'preview' => 'Bestellung '.$this->bestellung->nummer.' zur Freigabe zugewiesen.',
            'topic' => 'Bestellungen',
            'url' => route('apps.bestellungen.detail', [
                'bestellung' => $this->bestellung,
                'aktion' => 'freigeben',
            ]),
        ];
    }
}
