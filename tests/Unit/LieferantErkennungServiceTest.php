<?php

declare(strict_types=1);

use Hwkdo\IntranetAppBestellungen\Models\LieferantCache;
use Hwkdo\IntranetAppBestellungen\Services\Lieferant\LieferantErkennungService;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('ordnet einen erkannten Namen nur bei einem eindeutigen Stammdatentreffer zu', function (): void {
    LieferantCache::query()->create([
        'lieferantennummer' => '100',
        'lieferantenname' => 'Dachdecker Meier GmbH',
    ]);
    LieferantCache::query()->create([
        'lieferantennummer' => '200',
        'lieferantenname' => 'Maler Schulz KG',
    ]);
    LieferantCache::query()->create([
        'lieferantennummer' => '201',
        'lieferantenname' => 'Maler Schulz',
    ]);

    $service = new LieferantErkennungService;

    expect($service->zuordnen('Dachdecker Meier GmbH')?->lieferantennummer)->toBe('100')
        ->and($service->zuordnen('  dachdecker   meier ')?->lieferantennummer)->toBe('100')
        ->and($service->zuordnen('Maler Schulz'))->toBeNull()
        ->and($service->zuordnen('Unbekannt & Söhne'))->toBeNull();
});
