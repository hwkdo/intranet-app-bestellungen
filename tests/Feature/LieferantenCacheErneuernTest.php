<?php

declare(strict_types=1);

use App\Models\User;
use Hwkdo\BueLaravel\Facades\BueLaravel;
use Hwkdo\IntranetAppBestellungen\Models\LieferantCache;
use Hwkdo\IntranetAppBestellungen\Notifications\LieferantCacheAktualisiertNotification;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\URL;

it('zeigt die Bestätigungsseite ohne den Cache zu erneuern', function (): void {
    BueLaravel::shouldReceive('getAllLieferanten')->never();

    $url = URL::temporarySignedRoute('apps.bestellungen.lieferanten-cache.erneuern', now()->addHour());

    $this->get($url)
        ->assertSuccessful()
        ->assertSee('Lieferanten-Cache aktualisieren')
        ->assertSee('Die betroffene Bestellung wird dabei nicht verändert.');
});

it('erneuert den Lieferanten-Cache über den signierten Aufruf', function (): void {
    BueLaravel::shouldReceive('getAllLieferanten')
        ->once()
        ->andReturn(collect([
            (object) [
                'lieferantennummer' => '8800',
                'lieferantenname' => 'Neu GmbH',
            ],
        ]));

    $url = URL::temporarySignedRoute('apps.bestellungen.lieferanten-cache.erneuern', now()->addHour());

    $this->post($url)
        ->assertSuccessful()
        ->assertSee('1 Lieferanten wurden übernommen.');

    expect(LieferantCache::query()->where('lieferantennummer', '8800')->value('lieferantenname'))->toBe('Neu GmbH');
});

it('informiert die meldende Person nach der Cache-Aktualisierung', function (): void {
    Notification::fake();

    $melder = User::factory()->create();

    BueLaravel::shouldReceive('getAllLieferanten')
        ->once()
        ->andReturn(collect([
            (object) [
                'lieferantennummer' => '8800',
                'lieferantenname' => 'Neu GmbH',
            ],
        ]));

    $url = URL::temporarySignedRoute('apps.bestellungen.lieferanten-cache.erneuern', now()->addHour(), [
        'melder' => $melder->getKey(),
        'lieferant' => 'Neu GmbH',
    ]);

    $this->post($url)
        ->assertSuccessful()
        ->assertSee('Die meldende Person wurde informiert.');

    Notification::assertSentTo(
        $melder,
        LieferantCacheAktualisiertNotification::class,
        fn (LieferantCacheAktualisiertNotification $notification): bool => $notification->lieferantName === 'Neu GmbH',
    );
});

it('lehnt einen Aufruf ohne gültige Signatur ab', function (): void {
    $this->get(route('apps.bestellungen.lieferanten-cache.erneuern'))
        ->assertForbidden();

    $this->post(route('apps.bestellungen.lieferanten-cache.erneuern.store'))
        ->assertForbidden();
});
