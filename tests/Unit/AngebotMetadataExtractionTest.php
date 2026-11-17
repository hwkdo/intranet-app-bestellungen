<?php

declare(strict_types=1);

use App\Services\PdfService;
use Hwkdo\IntranetAppBase\Contracts\AiConfigResolverInterface;
use Hwkdo\IntranetAppBase\Contracts\IntranetAiGatewayInterface;
use Hwkdo\IntranetAppBase\Data\ResolvedAiConfig;
use Hwkdo\IntranetAppBase\Enums\AiCapability;
use Hwkdo\IntranetAppBase\Enums\AiConfigSource;
use Hwkdo\IntranetAppBase\Enums\AiProvider;
use Hwkdo\IntranetAppBestellungen\Models\Angebot;
use Hwkdo\IntranetAppBestellungen\Services\Api\AngebotPdfMetadataExtractionService;
use Illuminate\Support\Facades\Storage;

it('mappt Angebotsdatum und Nettobetrag aus der KI-Antwort', function (): void {
    Storage::fake('local');
    Storage::disk('local')->put('bestellungen/angebote/1/angebot.pdf', '%PDF-1.4');

    $pdf = Mockery::mock(PdfService::class);
    $pdf->shouldReceive('pdfToText')->once()->andReturn('Angebot der Muster GmbH vom 12.10.2026 Nummer A-100');

    $gateway = Mockery::mock(IntranetAiGatewayInterface::class);
    $gateway->shouldReceive('agent')->once()->andReturn([
        'supplier_name' => 'Muster GmbH',
        'reference_number' => 'A-100',
        'offer_date' => '12.10.2026',
        'net_amount' => 1000.5,
        'amount' => 1190.6,
    ]);

    $config = Mockery::mock(AiConfigResolverInterface::class);
    $config->shouldReceive('resolve')->once()->andReturn(new ResolvedAiConfig(
        provider: AiProvider::OpenAi,
        model: 'gpt-test',
        source: AiConfigSource::ConfigDefault,
        capability: AiCapability::Agent,
    ));

    $result = (new AngebotPdfMetadataExtractionService($pdf, $gateway, $config))->extract(new Angebot([
        'pdf_path' => 'bestellungen/angebote/1/angebot.pdf',
        'user_id' => 5,
    ]));

    expect($result['offer_date'])->toBe('2026-10-12')
        ->and($result['net_amount'])->toBe(1000.5)
        ->and($result['amount'])->toBe(1190.6)
        ->and($result['supplier_name'])->toBe('Muster GmbH')
        ->and($result['reference_number'])->toBe('A-100')
        ->and($result['source'])->toBe('text')
        ->and($result['payload']['contact'])->toBe([
            'person' => null,
            'salutation' => null,
            'email' => null,
        ]);
});

it('legt Ansprechpartner, Anrede und E-Mail im Extraktions-Payload ab', function (): void {
    Storage::fake('local');
    Storage::disk('local')->put('bestellungen/angebote/2/angebot.pdf', '%PDF-1.4');

    $pdf = Mockery::mock(PdfService::class);
    $pdf->shouldReceive('pdfToText')->once()->andReturn('Angebot der Muster GmbH, Frau Lina Vogt, mailto:lina.vogt@example.test');

    $gateway = Mockery::mock(IntranetAiGatewayInterface::class);
    $gateway->shouldReceive('agent')->once()->andReturn([
        'supplier_name' => 'Muster GmbH',
        'reference_number' => 'A-200',
        'offer_date' => '2026-11-02',
        'net_amount' => 500,
        'amount' => 595,
        'contact_person' => 'Lina Vogt',
        'contact_salutation' => 'Frau',
        'contact_email' => 'mailto:lina.vogt@example.test',
    ]);

    $config = Mockery::mock(AiConfigResolverInterface::class);
    $config->shouldReceive('resolve')->once()->andReturn(new ResolvedAiConfig(
        provider: AiProvider::OpenAi,
        model: 'gpt-test',
        source: AiConfigSource::ConfigDefault,
        capability: AiCapability::Agent,
    ));

    $result = (new AngebotPdfMetadataExtractionService($pdf, $gateway, $config))->extract(new Angebot([
        'pdf_path' => 'bestellungen/angebote/2/angebot.pdf',
        'user_id' => 5,
    ]));

    expect($result['payload']['contact'])->toBe([
        'person' => 'Lina Vogt',
        'salutation' => 'frau',
        'email' => 'lina.vogt@example.test',
    ]);
});
