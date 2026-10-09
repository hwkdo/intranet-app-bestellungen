<?php

declare(strict_types=1);

namespace Hwkdo\IntranetAppBestellungen\Services\Api;

use App\Services\PdfService;
use Hwkdo\IntranetAppBase\Contracts\AiConfigResolverInterface;
use Hwkdo\IntranetAppBase\Contracts\IntranetAiGatewayInterface;
use Hwkdo\IntranetAppBase\Data\AiRequestContext;
use Hwkdo\IntranetAppBase\Enums\AiCapability;
use Hwkdo\IntranetAppBestellungen\Ai\Agents\AngebotMetadataAgent;
use Hwkdo\IntranetAppBestellungen\Models\Angebot;
use Illuminate\Support\Facades\Storage;
use RuntimeException;

class AngebotPdfMetadataExtractionService
{
    public function __construct(
        private PdfService $pdfService,
        private IntranetAiGatewayInterface $gateway,
        private AiConfigResolverInterface $aiConfig,
    ) {}

    /**
     * @return array{
     *   supplier_name: ?string,
     *   reference_number: ?string,
     *   amount: ?float,
     *   source: string,
     *   method: string,
     *   provider: ?string,
     *   payload: array<string, mixed>,
     *   confidence: ?float
     * }
     */
    public function extract(Angebot $angebot): array
    {
        $absolutePdfPath = Storage::disk('local')->path((string) $angebot->pdf_path);
        if (! is_file($absolutePdfPath)) {
            throw new RuntimeException('Angebots-PDF nicht gefunden: '.$absolutePdfPath);
        }

        $embeddedText = $this->embeddedText($absolutePdfPath);
        $usesEmbeddedText = $this->hasUsableText($embeddedText);
        $text = $usesEmbeddedText
            ? $embeddedText
            : trim($this->gateway->parseAppDocument($absolutePdfPath, 'bestellungen', $angebot->user_id));

        if (! $this->hasUsableText($text)) {
            throw new RuntimeException('Aus dem Angebots-PDF konnte kein Text gelesen werden.');
        }

        $structured = $this->gateway->agent(
            new AngebotMetadataAgent,
            "Angebotstext:\n\n".$text,
            new AiRequestContext(
                appIdentifier: 'bestellungen',
                capability: AiCapability::Agent,
                userId: $angebot->user_id,
            ),
        );

        if (! is_array($structured)) {
            throw new RuntimeException('KI-Antwort enthält keine strukturierten Angebotsdaten.');
        }

        $supplierName = $this->nullableString($structured['supplier_name'] ?? null);
        $referenceNumber = $this->nullableString($structured['reference_number'] ?? null);
        $amount = $this->nullableFloat($structured['amount'] ?? null);
        $config = $this->aiConfig->resolve('bestellungen', AiCapability::Agent);

        return [
            'supplier_name' => $supplierName,
            'reference_number' => $referenceNumber,
            'amount' => $amount,
            'source' => $usesEmbeddedText ? 'text' : 'document_parse',
            'method' => 'structured',
            'provider' => $config->provider->label(),
            'payload' => [
                'text_excerpt' => mb_substr($text, 0, 1500),
                'model' => $config->model,
            ],
            'confidence' => $this->calculateConfidence($supplierName, $referenceNumber, $amount),
        ];
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

    private function nullableString(mixed $value): ?string
    {
        if (! is_string($value)) {
            return null;
        }

        $trimmed = trim($value);

        return $trimmed === '' ? null : $trimmed;
    }

    private function nullableFloat(mixed $value): ?float
    {
        if (is_int($value) || is_float($value)) {
            return (float) $value;
        }

        if (is_string($value) && is_numeric(trim($value))) {
            return (float) trim($value);
        }

        return null;
    }

    private function calculateConfidence(?string $supplierName, ?string $referenceNumber, ?float $amount): float
    {
        $score = 0.0;
        if ($supplierName !== null) {
            $score += 0.45;
        }
        if ($referenceNumber !== null) {
            $score += 0.2;
        }
        if ($amount !== null) {
            $score += 0.35;
        }

        return min(1.0, $score);
    }
}
