<?php

declare(strict_types=1);

namespace Hwkdo\IntranetAppBestellungen\Services\Api;

use App\Services\LangdockCompletionService;
use App\Services\PdfService;
use Hwkdo\IntranetAppBestellungen\Models\Angebot;
use Hwkdo\OpenwebuiApiLaravel\Services\OpenWebUiRagService;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Spatie\PdfToImage\Enums\OutputFormat;
use Spatie\PdfToImage\Pdf as PdfToImagePdf;

class AngebotPdfMetadataExtractionService
{
    public function __construct(
        private PdfService $pdfService,
        private LangdockCompletionService $langdock,
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
            throw new \RuntimeException('Angebots-PDF nicht gefunden: '.$absolutePdfPath);
        }

        $textResult = [
            'supplier_name' => null,
            'reference_number' => null,
            'amount' => null,
            'payload' => [],
            'confidence' => null,
        ];

        try {
            $textResult = $this->extractFromText($absolutePdfPath);
        } catch (\Throwable $e) {
            Log::warning('bestellungen.angebot_ocr.text_extract_failed', [
                'angebot_id' => $angebot->getKey(),
                'message' => $e->getMessage(),
            ]);
            $textResult['payload'] = [
                'text_extract_error' => $e->getMessage(),
            ];
        }

        if ($this->isExtractionComplete($textResult)) {
            return $textResult + [
                'source' => 'text',
                'method' => 'pdf-to-text',
                'provider' => null,
            ];
        }

        $visionResult = $this->extractFromVision($absolutePdfPath);
        if ($visionResult !== null && $this->isExtractionComplete($visionResult)) {
            return $visionResult + [
                'source' => 'vision',
                'method' => 'vision',
            ];
        }

        if ($visionResult !== null) {
            return $visionResult + [
                'source' => 'vision_partial',
                'method' => 'vision',
            ];
        }

        return $textResult + [
            'source' => 'text_partial',
            'method' => 'pdf-to-text',
            'provider' => null,
        ];
    }

    /**
     * @return array{
     *   supplier_name: ?string,
     *   reference_number: ?string,
     *   amount: ?float,
     *   payload: array<string, mixed>,
     *   confidence: ?float
     * }
     */
    private function extractFromText(string $absolutePdfPath): array
    {
        $text = (string) $this->pdfService->pdfToText($absolutePdfPath);
        $supplierName = $this->extractSupplierNameFromText($text);
        $referenceNumber = $this->extractReferenceNumberFromText($text);
        $amount = $this->extractAmountFromText($text);

        return [
            'supplier_name' => $supplierName,
            'reference_number' => $referenceNumber,
            'amount' => $amount,
            'payload' => [
                'text_excerpt' => mb_substr(trim($text), 0, 1500),
            ],
            'confidence' => $this->calculateConfidence($supplierName, $referenceNumber, $amount),
        ];
    }

    /**
     * @return array{
     *   supplier_name: ?string,
     *   reference_number: ?string,
     *   amount: ?float,
     *   provider: ?string,
     *   payload: array<string, mixed>,
     *   confidence: ?float
     * }|null
     */
    private function extractFromVision(string $absolutePdfPath): ?array
    {
        [$images, $tmpDir] = $this->renderPdfPagesToImages($absolutePdfPath);
        if ($images === []) {
            return null;
        }

        try {
            $aggregated = [
                'supplier_name' => null,
                'reference_number' => null,
                'amount' => null,
                'provider' => null,
                'payload' => [
                    'pages' => [],
                ],
                'confidence' => null,
            ];
            $fallbackAmount = null;

            foreach ($images as $imagePath) {
                $pageResult = $this->callVisionProviders($imagePath);
                if ($pageResult === null) {
                    continue;
                }

                $aggregated['supplier_name'] ??= $this->nullableString($pageResult['supplier_name'] ?? null);
                $aggregated['reference_number'] ??= $this->nullableString($pageResult['reference_number'] ?? null);
                $pageAmount = $this->nullableFloat($pageResult['amount'] ?? null);
                $isDocumentTotal = (bool) ($pageResult['amount_is_document_total'] ?? false);
                $amountLabel = mb_strtolower((string) ($pageResult['amount_label'] ?? ''));
                $looksLikeTotalLabel = str_contains($amountLabel, 'gesamt')
                    || str_contains($amountLabel, 'summe')
                    || str_contains($amountLabel, 'endsumme')
                    || str_contains($amountLabel, 'zahlbetrag')
                    || str_contains($amountLabel, 'rechnungsbetrag')
                    || str_contains($amountLabel, 'brutto');

                if ($pageAmount !== null && ($isDocumentTotal || $looksLikeTotalLabel)) {
                    // Dokumentensumme gewinnt immer und darf spätere Seiten überschreiben.
                    $aggregated['amount'] = $pageAmount;
                } elseif ($pageAmount !== null && $fallbackAmount === null) {
                    $fallbackAmount = $pageAmount;
                }
                $aggregated['provider'] ??= $this->nullableString($pageResult['provider'] ?? null);
                $aggregated['confidence'] = $this->nullableFloat($pageResult['confidence'] ?? $aggregated['confidence']);
                $aggregated['payload']['pages'][] = $pageResult;
            }

            if ($aggregated['amount'] === null && $fallbackAmount !== null) {
                $aggregated['amount'] = $fallbackAmount;
            }

            return $aggregated;
        } finally {
            $this->deleteDirectoryRecursive($tmpDir);
        }
    }

    /**
     * @return array{0: list<string>, 1: string}
     */
    private function renderPdfPagesToImages(string $absolutePdfPath): array
    {
        if (! class_exists(PdfToImagePdf::class)) {
            return [[], ''];
        }

        $maxPages = max(1, min(5, (int) config('intranet-app-bestellungen.api.vision_max_pages', 2)));
        $dpi = max(120, min(300, (int) config('intranet-app-bestellungen.api.vision_dpi', 180)));

        $dir = storage_path('app/private/bestellungen-ocr/'.bin2hex(random_bytes(8)));
        if (! @mkdir($dir, 0755, true) && ! is_dir($dir)) {
            return [[], $dir];
        }

        $pdf = new PdfToImagePdf($absolutePdfPath);
        $pdf->format(OutputFormat::Png)->resolution($dpi);
        $totalPages = $pdf->pageCount();
        if ($totalPages < 1) {
            return [[], $dir];
        }

        $pdf->selectPages(...range(1, min($totalPages, $maxPages)));

        return [$pdf->save($dir, 'page-'), $dir];
    }

    /**
     * @return array<string, mixed>|null
     */
    private function callVisionProviders(string $imagePath): ?array
    {
        $prompt = <<<PROMPT
Lies das Angebotsdokument im Bild.
Extrahiere diese Felder und antworte nur als valides JSON:
{
  "supplier_name": null,
  "reference_number": null,
  "amount": null,
  "amount_label": null,
  "amount_is_document_total": false,
  "currency": null,
  "confidence": null
}

Regeln:
- amount darf NUR gesetzt werden, wenn auf dieser Seite explizit die Endsumme / der zu zahlende Gesamtbetrag steht.
- Typische Labels sind: "Gesamtbetrag", "Summe", "Endsumme", "Rechnungsbetrag", "Bruttobetrag", "Zu zahlen".
- Wenn nur Positionspreise oder Zwischensummen sichtbar sind, setze amount auf null.
- amount_label enthält das gefundene Label zur Summe (z. B. "Gesamtbetrag EUR"), sonst null.
- amount_is_document_total ist true nur bei echter Dokument-Endsumme, sonst false.
- amount ist ein JSON number (Punkt als Dezimaltrennzeichen), sonst null.
- Fehlende Werte sind null.
PROMPT;

        $timeout = max(1, (int) config('intranet-app-bestellungen.api.vision_timeout_seconds', 120));
        $connectTimeout = max(1, (int) config('intranet-app-bestellungen.api.vision_connect_timeout_seconds', 15));

        if (class_exists(OpenWebUiRagService::class)) {
            try {
                /** @var OpenWebUiRagService $openWebUi */
                $openWebUi = app(OpenWebUiRagService::class);
                $model = (string) config('intranet-app-assets.d3_invoice_vision_model', 'qwen2.5vl:7b');
                $response = $openWebUi->chatWithImageFilePath(
                    $model,
                    $prompt,
                    $imagePath,
                    null,
                    [],
                    $timeout,
                    $connectTimeout
                );

                return $this->decodeVisionJsonResponse($response) + ['provider' => 'openwebui'];
            } catch (\Throwable $e) {
                Log::warning('bestellungen.angebot_ocr.openwebui_failed', ['message' => $e->getMessage()]);
            }
        }

        try {
            $model = (string) config('intranet-app-assets.d3_invoice_vision_model_langdock', 'gpt-5-mini');
            $response = $this->langdock->createChatCompletionWithImageFromPath(
                $model,
                $prompt,
                $imagePath,
                $timeout,
                $connectTimeout
            );

            return $this->decodeVisionJsonResponse($response) + ['provider' => 'langdock'];
        } catch (\Throwable $e) {
            Log::warning('bestellungen.angebot_ocr.langdock_failed', ['message' => $e->getMessage()]);
        }

        return null;
    }

    /**
     * @param  array<string, mixed>  $response
     * @return array<string, mixed>
     */
    private function decodeVisionJsonResponse(array $response): array
    {
        $rawContent = data_get($response, 'choices.0.message.content');
        $content = $this->normalizeAssistantContent($rawContent);
        $json = $this->extractJsonFromContent($content);
        $decoded = json_decode($json, true);

        if (! is_array($decoded)) {
            throw new \RuntimeException('Vision-Antwort ist kein valides JSON.');
        }

        return $decoded;
    }

    private function extractSupplierNameFromText(string $text): ?string
    {
        $lines = preg_split('/\r\n|\r|\n/', $text) ?: [];
        foreach ($lines as $line) {
            $normalized = trim($line);
            if ($normalized === '') {
                continue;
            }

            if (mb_strlen($normalized) < 4 || mb_strlen($normalized) > 120) {
                continue;
            }

            if (preg_match('/\b(angebot|rechnung|datum|betrag|summe|eur)\b/i', $normalized)) {
                continue;
            }

            return $normalized;
        }

        return null;
    }

    private function extractReferenceNumberFromText(string $text): ?string
    {
        $patterns = [
            '/(?:Angebotsnummer|Angebot|Referenz|Ref(?:erenznummer)?|Vorgangsnummer)\s*[:#]?\s*([A-Za-z0-9\-\/_]+)/iu',
            '/\b([A-Z]{2,}\-?\d{3,})\b/u',
        ];

        foreach ($patterns as $pattern) {
            if (preg_match($pattern, $text, $matches) === 1) {
                return $this->nullableString($matches[1] ?? null);
            }
        }

        return null;
    }

    private function extractAmountFromText(string $text): ?float
    {
        $lines = preg_split('/\r\n|\r|\n/', $text) ?: [];
        $preferredKeywords = [
            'summe',
            'gesamt',
            'gesamtbetrag',
            'endsumme',
            'rechnungsbetrag',
            'zahlbetrag',
            'brutto',
            'zu zahlen',
        ];

        $preferredAmounts = [];
        foreach ($lines as $line) {
            $lineNormalized = mb_strtolower(trim($line));
            if ($lineNormalized === '') {
                continue;
            }

            $containsKeyword = false;
            foreach ($preferredKeywords as $keyword) {
                if (str_contains($lineNormalized, $keyword)) {
                    $containsKeyword = true;
                    break;
                }
            }

            if (! $containsKeyword) {
                continue;
            }

            if (preg_match_all('/(\d{1,3}(?:[.\s]\d{3})*(?:,\d{2})|\d+(?:[.,]\d{2}))/u', $line, $matches) > 0) {
                foreach ($matches[1] as $raw) {
                    $parsed = $this->nullableFloat($raw);
                    if ($parsed !== null) {
                        $preferredAmounts[] = $parsed;
                    }
                }
            }
        }

        if ($preferredAmounts !== []) {
            return $preferredAmounts[array_key_last($preferredAmounts)];
        }

        $amounts = [];
        if (preg_match_all('/(\d{1,3}(?:[.\s]\d{3})*(?:,\d{2})|\d+(?:[.,]\d{2}))\s*(?:EUR|€)/iu', $text, $matches) > 0) {
            foreach ($matches[1] as $raw) {
                $parsed = $this->nullableFloat($raw);
                if ($parsed !== null) {
                    $amounts[] = $parsed;
                }
            }
        }

        if ($amounts !== []) {
            rsort($amounts, SORT_NUMERIC);

            return $amounts[0];
        }

        $allAmounts = [];
        if (preg_match_all('/(\d{1,3}(?:[.\s]\d{3})*(?:,\d{2})|\d+(?:[.,]\d{2}))/u', $text, $matches) > 0) {
            foreach ($matches[1] as $raw) {
                $parsed = $this->nullableFloat($raw);
                if ($parsed !== null) {
                    $allAmounts[] = $parsed;
                }
            }
        }

        return $allAmounts === [] ? null : $allAmounts[array_key_last($allAmounts)];
    }

    /**
     * @param  array{
     *   supplier_name: ?string,
     *   reference_number: ?string,
     *   amount: ?float
     * }  $result
     */
    private function isExtractionComplete(array $result): bool
    {
        return $result['supplier_name'] !== null && $result['amount'] !== null;
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

    private function normalizeAssistantContent(mixed $content): string
    {
        if (is_string($content)) {
            return trim($content);
        }

        if (! is_array($content)) {
            return '';
        }

        $parts = [];
        foreach ($content as $part) {
            if (is_string($part)) {
                $parts[] = $part;
                continue;
            }

            if (is_array($part) && ($part['type'] ?? '') === 'text') {
                $parts[] = (string) ($part['text'] ?? '');
            }
        }

        return trim(implode("\n", $parts));
    }

    private function extractJsonFromContent(string $content): string
    {
        $trimmed = trim($content);
        if (str_starts_with($trimmed, '```')) {
            $trimmed = preg_replace('/^```(?:json)?\s*/', '', $trimmed) ?? $trimmed;
            $trimmed = preg_replace('/\s*```$/', '', $trimmed) ?? $trimmed;
            $trimmed = trim($trimmed);
        }

        $firstBrace = strpos($trimmed, '{');
        $lastBrace = strrpos($trimmed, '}');
        if ($firstBrace === false || $lastBrace === false || $lastBrace < $firstBrace) {
            throw new \RuntimeException('Kein JSON-Objekt in der Vision-Antwort gefunden.');
        }

        return substr($trimmed, $firstBrace, $lastBrace - $firstBrace + 1);
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
        if ($value === null || $value === '') {
            return null;
        }

        if (is_numeric($value)) {
            return (float) $value;
        }

        if (! is_string($value)) {
            return null;
        }

        $normalized = str_replace([' ', "\u{00A0}"], '', trim($value));
        $normalized = str_replace('.', '', $normalized);
        $normalized = str_replace(',', '.', $normalized);

        return is_numeric($normalized) ? (float) $normalized : null;
    }

    private function deleteDirectoryRecursive(string $dir): void
    {
        if ($dir === '' || ! is_dir($dir)) {
            return;
        }

        $items = @scandir($dir);
        if ($items === false) {
            return;
        }

        foreach ($items as $item) {
            if ($item === '.' || $item === '..') {
                continue;
            }

            $path = $dir.DIRECTORY_SEPARATOR.$item;
            if (is_dir($path)) {
                $this->deleteDirectoryRecursive($path);
            } else {
                @unlink($path);
            }
        }

        @rmdir($dir);
    }
}
