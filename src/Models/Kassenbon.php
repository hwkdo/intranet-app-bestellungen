<?php

declare(strict_types=1);

namespace Hwkdo\IntranetAppBestellungen\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;

class Kassenbon extends Model
{
    protected $table = 'intranet_app_bestellungen_kassenbons';

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'extraction_payload' => 'array',
            'extracted_at' => 'datetime',
            'belegdatum' => 'date',
            'nettobetrag' => 'decimal:2',
            'steuerbetrag' => 'decimal:2',
            'bruttobetrag' => 'decimal:2',
            'd3_rechnung_pushed_at' => 'datetime',
        ];
    }

    public function bestellung(): BelongsTo
    {
        return $this->belongsTo(Bestellung::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function dateiAbsolut(): string
    {
        return Storage::disk('local')->path((string) $this->datei_pfad);
    }

    public function istBild(): bool
    {
        $mime = strtolower((string) $this->mime);
        if (str_starts_with($mime, 'image/')) {
            return true;
        }

        return in_array(strtolower(pathinfo((string) $this->datei_pfad, PATHINFO_EXTENSION)), ['jpg', 'jpeg', 'png', 'webp', 'gif'], true);
    }

    public function istPdf(): bool
    {
        return ! $this->istBild();
    }

    public function analyseLaeuft(): bool
    {
        return in_array($this->extraction_status, ['pending', 'processing'], true);
    }

    public function merkeSchritt(string $schritt): void
    {
        if (! $this->exists) {
            return;
        }

        $payload = $this->extraction_payload ?? [];
        $payload['schritt'] = $schritt;

        $this->forceFill([
            'extraction_status' => 'processing',
            'extraction_payload' => $payload,
        ])->save();
    }
}
