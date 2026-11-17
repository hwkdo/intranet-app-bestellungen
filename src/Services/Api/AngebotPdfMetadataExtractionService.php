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
use Hwkdo\IntranetAppBestellungen\Support\StructuredValueNormalizer;
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
     *   offer_date: ?string,
     *   net_amount: ?float,
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

        $supplierName = StructuredValueNormalizer::nullableString($structured['supplier_name'] ?? null);
        $referenceNumber = StructuredValueNormalizer::nullableString($structured['reference_number'] ?? null);
        $offerDate = StructuredValueNormalizer::nullableDate($structured['offer_date'] ?? null);
        $netAmount = StructuredValueNormalizer::nullableFloat($structured['net_amount'] ?? null);
        $amount = StructuredValueNormalizer::nullableFloat($structured['amount'] ?? null);
        $contactPerson = StructuredValueNormalizer::nullableString($structured['contact_person'] ?? null);
        $contactSalutation = $this->nullableAnrede($structured['contact_salutation'] ?? null);
        $contactEmail = $this->nullableEmail($structured['contact_email'] ?? null);
        $config = $this->aiConfig->resolve('bestellungen', AiCapability::Agent);

        return [
            'supplier_name' => $supplierName,
            'reference_number' => $referenceNumber,
            'offer_date' => $offerDate,
            'net_amount' => $netAmount,
            'amount' => $amount,
            'source' => $usesEmbeddedText ? 'text' : 'document_parse',
            'method' => 'structured',
            'provider' => $config->provider->label(),
            'payload' => [
                'text_excerpt' => mb_substr($text, 0, 1500),
                'model' => $config->model,
                'contact' => [
                    'person' => $contactPerson,
                    'salutation' => $contactSalutation,
                    'email' => $contactEmail,
                ],
            ],
            'confidence' => $this->calculateConfidence($supplierName, $referenceNumber, $offerDate, $netAmount, $amount),
        ];
    }

    private function nullableAnrede(mixed $value): ?string
    {
        $raw = StructuredValueNormalizer::nullableString($value);
        if ($raw === null) {
            return null;
        }

        $normalized = mb_strtolower($raw);

        if (str_contains($normalized, 'frau')) {
            return 'frau';
        }

        if (str_contains($normalized, 'herr') || in_array($normalized, ['hr', 'mr'], true)) {
            return 'herr';
        }

        return null;
    }

    private function nullableEmail(mixed $value): ?string
    {
        $raw = StructuredValueNormalizer::nullableString($value);
        if ($raw === null) {
            return null;
        }

        $raw = preg_replace('/^mailto:/i', '', $raw) ?? $raw;

        return filter_var($raw, FILTER_VALIDATE_EMAIL) !== false ? $raw : null;
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

    private function calculateConfidence(
        ?string $supplierName,
        ?string $referenceNumber,
        ?string $offerDate,
        ?float $netAmount,
        ?float $amount,
    ): float {
        $score = 0.0;
        if ($supplierName !== null) {
            $score += 0.35;
        }
        if ($referenceNumber !== null) {
            $score += 0.15;
        }
        if ($offerDate !== null) {
            $score += 0.15;
        }
        if ($netAmount !== null) {
            $score += 0.15;
        }
        if ($amount !== null) {
            $score += 0.2;
        }

        return min(1.0, $score);
    }
}
