<?php

declare(strict_types=1);

namespace Hwkdo\IntranetAppBestellungen\Notifications;

use Hwkdo\IntranetAppBase\Notifications\IntranetNotification;
use Hwkdo\IntranetAppBestellungen\IntranetAppBestellungen;
use Illuminate\Notifications\Messages\MailMessage;
use NotificationChannels\WebPush\WebPushMessage;

class LieferantCacheAktualisiertNotification extends IntranetNotification
{
    public function __construct(
        public readonly string $lieferantName,
    ) {
        parent::__construct();
    }

    public function typeKey(): string
    {
        return 'bestellungen.supplier_cache_updated';
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('Lieferanten-Cache aktualisiert')
            ->line('Das Rechnungswesen hat den Lieferanten-Cache aktualisiert.')
            ->line('Sie können jetzt nach „'.$this->lieferantName.'“ suchen.')
            ->action('Bestellungen öffnen', route('apps.bestellungen.meine'));
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return $this->inboxPayload(
            title: 'Lieferanten-Cache aktualisiert',
            body: 'Sie können jetzt nach „'.$this->lieferantName.'“ suchen.',
            url: route('apps.bestellungen.meine'),
            appIdentifier: IntranetAppBestellungen::identifier(),
        );
    }

    public function toWebPush(object $notifiable, mixed $notification): WebPushMessage
    {
        return (new WebPushMessage)
            ->title('Lieferanten-Cache aktualisiert')
            ->body('Sie können jetzt nach „'.$this->lieferantName.'“ suchen.')
            ->data(['url' => route('apps.bestellungen.meine')]);
    }

    public function toTeams(object $notifiable): array
    {
        return [
            'preview' => 'Der Lieferanten-Cache wurde aktualisiert. Suche nach „'.$this->lieferantName.'“.',
            'topic' => 'Bestellungen',
            'url' => route('apps.bestellungen.meine'),
        ];
    }
}
