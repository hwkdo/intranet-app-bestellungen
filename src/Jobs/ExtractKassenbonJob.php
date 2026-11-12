<?php

declare(strict_types=1);

namespace Hwkdo\IntranetAppBestellungen\Jobs;

use Hwkdo\IntranetAppBestellungen\Models\Kassenbon;
use Hwkdo\IntranetAppBestellungen\Services\Kassenbon\KassenbonExtractionService;
use Hwkdo\IntranetAppBestellungen\Services\Lieferant\LieferantErkennungService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class ExtractKassenbonJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public int $tries = 3;

    /** @var array<int, int> */
    public array $backoff = [10, 30, 120];

    public function __construct(public readonly int $kassenbonId)
    {
        $this->onQueue(config('intranet-app-bestellungen.api.extract_queue'));
    }

    public function handle(
        KassenbonExtractionService $extractionService,
        LieferantErkennungService $lieferantErkennung,
    ): void {
        $kassenbon = Kassenbon::query()->find($this->kassenbonId);
        if (! $kassenbon || $kassenbon->bestellung_id !== null) {
            return;
        }

        $kassenbon->forceFill([
            'extraction_status' => 'processing',
            'extraction_error' => null,
        ])->save();

        $result = $extractionService->extract($kassenbon);
        $kassenbon->merkeSchritt('lieferant');
        $erkannterName = $result['supplier_name'] ?? null;
        $zugeordnet = filled($erkannterName)
            ? $lieferantErkennung->zuordnen((string) $erkannterName)
            : null;

        $kassenbon->forceFill([
            'extraction_status' => 'done',
            'extraction_error' => null,
            'extracted_at' => now(),
            'extraction_payload' => [
                'structured' => $result,
                'lieferant' => [
                    'erkannter_name' => $erkannterName,
                    'lieferantennummer' => $zugeordnet?->lieferantennummer,
                    'lieferantenname' => $zugeordnet?->lieferantenname,
                    'status' => $zugeordnet !== null
                        ? 'eindeutig'
                        : (filled($erkannterName) ? 'offen' : 'kein_name'),
                ],
                'source' => $result['source'] ?? null,
                'provider' => $result['provider'] ?? null,
                'model' => $result['payload']['model'] ?? null,
                'text_excerpt' => $result['payload']['text_excerpt'] ?? null,
            ],
        ])->save();
    }

    public function failed(\Throwable $exception): void
    {
        $kassenbon = Kassenbon::query()->find($this->kassenbonId);
        if (! $kassenbon) {
            return;
        }

        $kassenbon->update([
            'extraction_status' => 'failed',
            'extraction_error' => $exception->getMessage(),
        ]);

        Log::error('bestellungen.kassenbon_extraction_failed', [
            'kassenbon_id' => $this->kassenbonId,
            'error' => $exception->getMessage(),
        ]);
    }
}
