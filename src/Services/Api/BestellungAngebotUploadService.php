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
            'betrag' => $payload['amount'] ?? null,
            'pdf_path' => $relativePath,
            'extraction_status' => 'pending',
        ]);

        if (
            trim((string) ($payload['supplier_name'] ?? '')) === ''
            || trim((string) ($payload['reference_number'] ?? '')) === ''
            || ($payload['amount'] ?? null) === null
            || trim((string) ($payload['amount'] ?? '')) === ''
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
