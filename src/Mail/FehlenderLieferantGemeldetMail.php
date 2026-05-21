<?php

declare(strict_types=1);

namespace Hwkdo\IntranetAppBestellungen\Mail;

use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Address;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class FehlenderLieferantGemeldetMail extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public function __construct(
        public readonly User $melder,
        public readonly string $lieferantName,
        public readonly ?string $adresse,
        public readonly ?string $iban,
        public readonly ?string $webseite,
    ) {}

    public function envelope(): Envelope
    {
        $melderName = trim($this->melder->vorname.' '.$this->melder->nachname);

        return new Envelope(
            subject: 'Fehlender Lieferant',
            replyTo: [
                new Address(
                    (string) $this->melder->email,
                    $melderName !== '' ? $melderName : (string) $this->melder->email,
                ),
            ],
        );
    }

    public function content(): Content
    {
        $this->melder->loadMissing('standort');

        return new Content(
            markdown: 'intranet-app-bestellungen::emails.fehlender-lieferant-gemeldet',
            with: [
                'melder' => $this->melder,
                'lieferantName' => $this->lieferantName,
                'adresse' => $this->adresse,
                'iban' => $this->iban,
                'webseite' => $this->webseite,
                'standort' => $this->melder->standort?->name ?? '—',
                'raum' => filled($this->melder->raum) ? (string) $this->melder->raum : '—',
                'telefon' => filled($this->melder->telefon) ? (string) $this->melder->telefon : '—',
            ],
        );
    }
}
