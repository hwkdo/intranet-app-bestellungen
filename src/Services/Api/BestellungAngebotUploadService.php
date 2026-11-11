<?php

declare(strict_types=1);

namespace Hwkdo\IntranetAppBestellungen\Services\Api;

use Hwkdo\IntranetAppBestellungen\Jobs\ExtractAngebotMetadataJob;
use Hwkdo\IntranetAppBestellungen\Models\Angebot;
use Hwkdo\IntranetAppBestellungen\Models\Bestellung;
use Illuminate\Http\UploadedFile;

class BestellungAngebotUploadService
{
    /**
     * @param  array{
     *     file: UploadedFile,
     *     supplier_name?: string|null,
     *     reference_number?: string|null,
     *     offer_date?: string|null,
     *     net_amount?: int|float|string|null,
     *     amount?: int|float|string|null
     * }  $payload
     */
    public function store(Bestellung $bestellung, int $userId, array $payload): Angebot
    {
        $file = $payload['file'];
        $relativePath = $file->store('bestellungen/angebote/'.$bestellung->getKey(), 'local');

        $angebot = Angebot::create([
            'bestellung_id' => $bestellung->getKey(),
            'user_id' => $userId,
            'typ' => 'angebot',
            'lieferantenname' => $payload['supplier_name'] ?? null,
            'nummer' => $payload['reference_number'] ?? null,
            'angebotsdatum' => $payload['offer_date'] ?? null,
            'betrag_netto' => $payload['net_amount'] ?? null,
            'betrag' => $payload['amount'] ?? null,
            'pdf_path' => $relativePath,
            'extraction_status' => 'pending',
        ]);

        $offerDateMissing = trim((string) ($payload['offer_date'] ?? '')) === '';
        $netAmountMissing = ($payload['net_amount'] ?? null) === null || trim((string) ($payload['net_amount'] ?? '')) === '';

        if (
            trim((string) ($payload['supplier_name'] ?? '')) === ''
            || trim((string) ($payload['reference_number'] ?? '')) === ''
            || ($payload['amount'] ?? null) === null
            || trim((string) ($payload['amount'] ?? '')) === ''
            || $offerDateMissing
            || $netAmountMissing
        ) {
            ExtractAngebotMetadataJob::dispatch($angebot->getKey());
        } else {
            $angebot->forceFill([
                'extraction_status' => 'done',
                'extraction_source' => 'manual',
                'extracted_at' => now(),
            ])->save();
        }

        return $angebot;
    }
}
