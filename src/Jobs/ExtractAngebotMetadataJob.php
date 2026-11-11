<?php

declare(strict_types=1);

namespace Hwkdo\IntranetAppBestellungen\Jobs;

use Hwkdo\IntranetAppBestellungen\Enums\AktionTyp;
use Hwkdo\IntranetAppBestellungen\Models\Angebot;
use Hwkdo\IntranetAppBestellungen\Services\Api\AngebotPdfMetadataExtractionService;
use Hwkdo\IntranetAppBestellungen\Services\BestellungWorkflow;
use Hwkdo\IntranetAppBestellungen\Services\Lieferant\LieferantErkennungService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class ExtractAngebotMetadataJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public int $tries = 3;

    /** @var array<int, int> */
    public array $backoff = [10, 30, 120];

    public function __construct(public readonly int $angebotId)
    {
        $this->onQueue(config('intranet-app-bestellungen.api.extract_queue'));
    }

    public function handle(
        AngebotPdfMetadataExtractionService $extractionService,
        BestellungWorkflow $workflow,
        LieferantErkennungService $lieferantErkennung,
    ): void {
        $angebot = Angebot::query()->find($this->angebotId);
        if (! $angebot) {
            return;
        }

        $angebot->forceFill([
            'extraction_status' => 'processing',
            'extraction_error' => null,
        ])->save();

        $result = $extractionService->extract($angebot);

        $erkannterName = $angebot->lieferantenname ?: $result['supplier_name'];
        $zugeordnet = filled($angebot->lieferantennummer) || ! filled($erkannterName)
            ? null
            : $lieferantErkennung->zuordnen((string) $erkannterName);

        $angebot->forceFill([
            'lieferantenname' => $zugeordnet?->lieferantenname ?? $erkannterName,
            'lieferantennummer' => $angebot->lieferantennummer ?: $zugeordnet?->lieferantennummer,
            'nummer' => $angebot->nummer ?: $result['reference_number'],
            'angebotsdatum' => $angebot->angebotsdatum?->toDateString() ?: $result['offer_date'],
            'betrag_netto' => $angebot->betrag_netto ?: $result['net_amount'],
            'betrag' => $angebot->betrag ?: $result['amount'],
            'extraction_status' => 'done',
            'extraction_source' => $result['source'],
            'extraction_payload' => array_merge($result['payload'], [
                'method' => $result['method'] ?? null,
                'provider' => $result['provider'] ?? null,
                'erkannter_lieferant' => $result['supplier_name'] ?? null,
                'lieferant_erkennung' => filled($angebot->lieferantennummer) || $zugeordnet !== null
                    ? 'eindeutig'
                    : (filled($erkannterName) ? 'offen' : 'kein_name'),
            ]),
            'extracted_at' => now(),
            'extraction_error' => null,
        ])->save();

        $provider = (string) ($result['provider'] ?? '');
        $textHerkunft = ($result['source'] ?? '') === 'document_parse' ? 'Document-Parsing' : 'PDF-Text';
        $humanMethod = ($provider !== '' ? 'KI ('.$provider.')' : 'KI').', Text aus '.$textHerkunft;

        $workflow->logAktion(
            $angebot->bestellung,
            $angebot->user,
            AktionTyp::AngebotExtraktionAbgeschlossen,
            'Metadaten für Angebot wurden automatisch extrahiert via '.$humanMethod.'.',
            payload: [
                'angebot_id' => $angebot->getKey(),
                'method' => $result['method'] ?? null,
                'provider' => $provider !== '' ? $provider : null,
                'source' => $result['source'] ?? null,
            ],
        );
    }

    public function failed(\Throwable $exception): void
    {
        $angebot = Angebot::query()->find($this->angebotId);
        if (! $angebot) {
            return;
        }

        $angebot->update([
            'extraction_status' => 'failed',
            'extraction_error' => $exception->getMessage(),
        ]);

        app(BestellungWorkflow::class)->logAktion(
            $angebot->bestellung,
            $angebot->user,
            AktionTyp::AngebotExtraktionFehlgeschlagen,
            'Die automatische Metadaten-Extraktion ist fehlgeschlagen.',
            payload: [
                'angebot_id' => $angebot->getKey(),
                'error' => $exception->getMessage(),
            ],
        );

        Log::error('bestellungen.angebot_metadata_extraction_failed', [
            'angebot_id' => $this->angebotId,
            'error' => $exception->getMessage(),
        ]);
    }
}
