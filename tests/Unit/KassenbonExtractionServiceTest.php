<?php

declare(strict_types=1);

use App\Services\PdfService;
use Hwkdo\IntranetAppBase\Contracts\AiConfigResolverInterface;
use Hwkdo\IntranetAppBase\Contracts\DocumentParseConfigResolverInterface;
use Hwkdo\IntranetAppBase\Contracts\IntranetAiGatewayInterface;
use Hwkdo\IntranetAppBase\Data\ResolvedAiConfig;
use Hwkdo\IntranetAppBase\Data\ResolvedDocumentParseConfig;
use Hwkdo\IntranetAppBase\Enums\AiCapability;
use Hwkdo\IntranetAppBase\Enums\AiConfigSource;
use Hwkdo\IntranetAppBase\Enums\AiProvider;
use Hwkdo\IntranetAppBase\Enums\DocumentParseEngine;
use Hwkdo\IntranetAppBestellungen\Models\Kassenbon;
use Hwkdo\IntranetAppBestellungen\Services\Kassenbon\KassenbonExtractionService;
use Illuminate\Support\Facades\Storage;

afterEach(function (): void {
    Storage::forgetDisk('local');
});

it('normalisiert den Kassenbon und setzt eine fehlende Belegnummer auf 0', function (): void {
    Storage::fake('local');
    Storage::disk('local')->put('bestellungen/kassenbons/probe.pdf', '%PDF-1.4');

    $pdf = Mockery::mock(PdfService::class);
    $pdf->shouldReceive('pdfToText')->once()->andReturn('');
    app()->instance(PdfService::class, $pdf);

    $gateway = Mockery::mock(IntranetAiGatewayInterface::class);
    $gateway->shouldReceive('parseAppDocument')->once()->andReturn('Nordmarkt Baumarkt Summe 17,76 EUR');
    $gateway->shouldReceive('agent')->once()->andReturn([
        'supplier_name' => 'Nordmarkt Baumarkt GmbH',
        'supplier_street' => null,
        'supplier_zip' => null,
        'supplier_city' => 'Dortmund',
        'vat_id' => null,
        'receipt_number' => null,
        'receipt_date' => '10.11.2026',
        'net_amount' => 14.92,
        'tax_amount' => '2,84',
        'amount' => 17.76,
        'payment_method' => 'bar',
        'vat' => [],
        'line_items' => [
            [
                'description' => 'Siphon',
                'quantity' => 1,
                'unit' => 'Stk',
                'unit_price' => 12.99,
                'line_total' => 12.99,
                'article_number' => '401128',
            ],
        ],
    ]);
    app()->instance(IntranetAiGatewayInterface::class, $gateway);

    $ai = Mockery::mock(AiConfigResolverInterface::class);
    $ai->shouldReceive('resolve')->once()->andReturn(new ResolvedAiConfig(
        AiProvider::OpenAi,
        'test-model',
        AiConfigSource::Base,
        AiCapability::Agent,
    ));
    app()->instance(AiConfigResolverInterface::class, $ai);

    $kassenbon = new Kassenbon([
        'datei_pfad' => 'bestellungen/kassenbons/probe.pdf',
        'mime' => 'application/pdf',
        'user_id' => 1,
    ]);

    $result = app(KassenbonExtractionService::class)->extract($kassenbon);

    expect($result['receipt_number'])->toBe('0')
        ->and($result['receipt_date'])->toBe('2026-11-10')
        ->and($result['tax_amount'])->toBe(2.84)
        ->and($result['supplier_name'])->toBe('Nordmarkt Baumarkt GmbH')
        ->and($result['line_items'][0]['description'])->toBe('Siphon')
        ->and($result['source'])->toBe('document_parse');
});

it('lehnt Kassenbon-Fotos ab, wenn nur ein PDF-Motor konfiguriert ist', function (): void {
    Storage::fake('local');
    Storage::disk('local')->put('bestellungen/kassenbons/probe.jpg', 'bild');

    $docs = Mockery::mock(DocumentParseConfigResolverInterface::class);
    $docs->shouldReceive('resolve')->once()->andReturn(new ResolvedDocumentParseConfig(
        DocumentParseEngine::PdfToText,
        'fast',
        AiConfigSource::Base,
    ));
    app()->instance(DocumentParseConfigResolverInterface::class, $docs);

    $gateway = Mockery::mock(IntranetAiGatewayInterface::class);
    $gateway->shouldNotReceive('parseAppDocument');
    app()->instance(IntranetAiGatewayInterface::class, $gateway);

    $kassenbon = new Kassenbon([
        'datei_pfad' => 'bestellungen/kassenbons/probe.jpg',
        'mime' => 'image/jpeg',
        'user_id' => 1,
    ]);

    expect(fn () => app(KassenbonExtractionService::class)->extract($kassenbon))
        ->toThrow(RuntimeException::class, 'Vision oder LlamaParse');
});
