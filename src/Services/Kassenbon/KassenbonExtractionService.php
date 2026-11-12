<?php

declare(strict_types=1);

namespace Hwkdo\IntranetAppBestellungen\Services\Kassenbon;

use App\Services\PdfService;
use Hwkdo\IntranetAppBase\Contracts\AiConfigResolverInterface;
use Hwkdo\IntranetAppBase\Contracts\DocumentParseConfigResolverInterface;
use Hwkdo\IntranetAppBase\Contracts\IntranetAiGatewayInterface;
use Hwkdo\IntranetAppBase\Data\AiRequestContext;
use Hwkdo\IntranetAppBase\Enums\AiCapability;
use Hwkdo\IntranetAppBase\Enums\DocumentParseEngine;
use Hwkdo\IntranetAppBestellungen\Ai\Agents\KassenbonAgent;
use Hwkdo\IntranetAppBestellungen\Models\Kassenbon;
use Hwkdo\IntranetAppBestellungen\Support\StructuredValueNormalizer;
use RuntimeException;

class KassenbonExtractionService
{
    public function __construct(
        private PdfService $pdfService,
        private IntranetAiGatewayInterface $gateway,
        private AiConfigResolverInterface $aiConfig,
        private DocumentParseConfigResolverInterface $documentParseConfig,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function extract(Kassenbon $kassenbon): array
    {
        $absolutePath = $kassenbon->dateiAbsolut();
        if (! is_file($absolutePath)) {
            throw new RuntimeException('Kassenbon-Datei nicht gefunden: '.$absolutePath);
        }

        $this->ensureEngineKannDateiLesen($absolutePath);
        $kassenbon->merkeSchritt('lesen');

        $embeddedText = $kassenbon->istPdf() ? $this->embeddedText($absolutePath) : '';
        $usesEmbeddedText = $this->hasUsableText($embeddedText);
        $text = $usesEmbeddedText
            ? $embeddedText
            : trim($this->gateway->parseAppDocument($absolutePath, 'bestellungen', $kassenbon->user_id));

        if (! $this->hasUsableText($text)) {
            throw new RuntimeException('Aus dem Kassenbon konnte kein Text gelesen werden.');
        }

        $kassenbon->merkeSchritt('erkennen');

        $structured = $this->gateway->agent(
            new KassenbonAgent,
            "Kassenbon:\n\n".$text,
            new AiRequestContext(
                appIdentifier: 'bestellungen',
                capability: AiCapability::Agent,
                userId: $kassenbon->user_id,
            ),
        );

        if (! is_array($structured)) {
            throw new RuntimeException('KI-Antwort enthält keine strukturierten Kassenbondaten.');
        }

        $config = $this->aiConfig->resolve('bestellungen', AiCapability::Agent);
        $normalized = $this->normalize($structured);

        return [
            ...$normalized,
            'source' => $usesEmbeddedText ? 'text' : 'document_parse',
            'method' => 'structured',
            'provider' => $config->provider->label(),
            'payload' => [
                'text_excerpt' => mb_substr($text, 0, 1500),
                'model' => $config->model,
            ],
        ];
    }

    /**
     * @param  array<string, mixed>  $structured
     * @return array<string, mixed>
     */
    private function normalize(array $structured): array
    {
        $lineItems = [];
        foreach (is_array($structured['line_items'] ?? null) ? $structured['line_items'] : [] as $line) {
            if (! is_array($line)) {
                continue;
            }

            $description = StructuredValueNormalizer::nullableString($line['description'] ?? null);
            if ($description === null) {
                continue;
            }

            $lineItems[] = [
                'description' => $description,
                'quantity' => StructuredValueNormalizer::nullableFloat($line['quantity'] ?? null) ?? 1.0,
                'unit' => StructuredValueNormalizer::nullableString($line['unit'] ?? null) ?? 'Stk',
                'unit_price' => StructuredValueNormalizer::nullableFloat($line['unit_price'] ?? null),
                'line_total' => StructuredValueNormalizer::nullableFloat($line['line_total'] ?? null),
                'article_number' => StructuredValueNormalizer::nullableString($line['article_number'] ?? null),
            ];
        }

        $vat = [];
        foreach (is_array($structured['vat'] ?? null) ? $structured['vat'] : [] as $row) {
            if (! is_array($row)) {
                continue;
            }

            $vat[] = [
                'rate' => StructuredValueNormalizer::nullableFloat($row['rate'] ?? null),
                'net' => StructuredValueNormalizer::nullableFloat($row['net'] ?? null),
                'tax' => StructuredValueNormalizer::nullableFloat($row['tax'] ?? null),
                'gross' => StructuredValueNormalizer::nullableFloat($row['gross'] ?? null),
            ];
        }

        return [
            'supplier_name' => StructuredValueNormalizer::nullableString($structured['supplier_name'] ?? null),
            'supplier_street' => StructuredValueNormalizer::nullableString($structured['supplier_street'] ?? null),
            'supplier_zip' => StructuredValueNormalizer::nullableString($structured['supplier_zip'] ?? null),
            'supplier_city' => StructuredValueNormalizer::nullableString($structured['supplier_city'] ?? null),
            'vat_id' => StructuredValueNormalizer::nullableString($structured['vat_id'] ?? null),
            'receipt_number' => StructuredValueNormalizer::nullableString($structured['receipt_number'] ?? null) ?? '0',
            'receipt_date' => StructuredValueNormalizer::nullableDate($structured['receipt_date'] ?? null),
            'net_amount' => StructuredValueNormalizer::nullableFloat($structured['net_amount'] ?? null),
            'tax_amount' => StructuredValueNormalizer::nullableFloat($structured['tax_amount'] ?? null),
            'amount' => StructuredValueNormalizer::nullableFloat($structured['amount'] ?? null),
            'payment_method' => StructuredValueNormalizer::nullableString($structured['payment_method'] ?? null),
            'vat' => $vat,
            'line_items' => $lineItems,
        ];
    }

    private function ensureEngineKannDateiLesen(string $absolutePath): void
    {
        if (! $this->istBildPfad($absolutePath)) {
            return;
        }

        $engine = $this->documentParseConfig->resolve('bestellungen')->engine;
        if (in_array($engine, [DocumentParseEngine::PdfToText, DocumentParseEngine::HwkAdmin], true)) {
            throw new RuntimeException('Der konfigurierte Document-Parse-Motor kann Fotos nicht lesen. Für Kassenbon-Fotos Vision oder LlamaParse im KI-Tab wählen.');
        }
    }

    private function istBildPfad(string $absolutePath): bool
    {
        return in_array(strtolower(pathinfo($absolutePath, PATHINFO_EXTENSION)), ['jpg', 'jpeg', 'png', 'webp', 'gif'], true);
    }

    private function embeddedText(string $absolutePdfPath): string
    {
        try {
            return trim((string) $this->pdfService->pdfToText($absolutePdfPath));
        } catch (\Throwable) {
            return '';
        }
    }

    private function hasUsableText(string $text): bool
    {
        $normalized = trim(str_replace("\f", '', $text));

        return mb_strlen($normalized) >= 8;
    }
}
