<?php

declare(strict_types=1);

use Hwkdo\IntranetAppBestellungen\Services\Pdf\BestellscheinPdfService;

it('findet das HWK-Dortmund-PNG aus public/img', function (): void {
    $logoPath = public_path('img/Handwerkskammer-Dortmund-Logo-Header.png');

    expect(is_readable($logoPath))->toBeTrue();

    $service = app(BestellscheinPdfService::class);
    $reflection = new ReflectionClass($service);
    $method = $reflection->getMethod('logoDataUri');
    $method->setAccessible(true);

    $dataUri = $method->invoke($service);

    expect($dataUri)
        ->toStartWith('data:image/png;base64,')
        ->and(strlen($dataUri))->toBeGreaterThan(100);
});
