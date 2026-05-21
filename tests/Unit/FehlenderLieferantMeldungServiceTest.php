<?php

declare(strict_types=1);

use App\Models\User;
use Hwkdo\IntranetAppBestellungen\Data\AppSettings;
use Hwkdo\IntranetAppBestellungen\Mail\FehlenderLieferantGemeldetMail;
use Hwkdo\IntranetAppBestellungen\Models\IntranetAppBestellungenSettings;
use Hwkdo\IntranetAppBestellungen\Services\Lieferant\FehlenderLieferantMeldungService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    IntranetAppBestellungenSettings::create([
        'version' => 1,
        'settings' => AppSettings::from([
            'fehlenderLieferantEmpfaengerEmail' => 'rechnungswesen@hwk-do.de',
        ])->toArray(),
    ]);
});

it('stellt eine E-Mail an den konfigurierten Empfänger in die Warteschlange', function (): void {
    Mail::fake();

    $melder = User::factory()->create([
        'vorname' => 'Max',
        'nachname' => 'Mustermann',
        'email' => 'max@example.com',
    ]);

    app(FehlenderLieferantMeldungService::class)->send(
        $melder,
        'Neuer Lieferant GmbH',
        'Musterstraße 1',
        'DE89370400440532013000',
        'https://example.com',
    );

    Mail::assertQueued(FehlenderLieferantGemeldetMail::class, function (FehlenderLieferantGemeldetMail $mail) use ($melder): bool {
        return $mail->hasTo('rechnungswesen@hwk-do.de')
            && $mail->melder->is($melder)
            && $mail->lieferantName === 'Neuer Lieferant GmbH'
            && $mail->adresse === 'Musterstraße 1'
            && $mail->iban === 'DE89370400440532013000'
            && $mail->webseite === 'https://example.com';
    });
});

it('wirft eine Ausnahme wenn kein Empfänger konfiguriert ist', function (): void {
    IntranetAppBestellungenSettings::query()->delete();
    IntranetAppBestellungenSettings::create([
        'version' => 1,
        'settings' => AppSettings::from([
            'fehlenderLieferantEmpfaengerEmail' => '',
        ])->toArray(),
    ]);

    $melder = User::factory()->create();

    app(FehlenderLieferantMeldungService::class)->send($melder, 'Test');
})->throws(InvalidArgumentException::class);
