<?php

declare(strict_types=1);

namespace Hwkdo\IntranetAppBestellungen\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Facades\Storage;

class Angebot extends Model
{
    protected $table = 'intranet_app_bestellungen_angebote';

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'angebotsdatum' => 'date',
            'betrag' => 'decimal:2',
            'betrag_netto' => 'decimal:2',
            'd3_pushed_at' => 'datetime',
            'extraction_payload' => 'array',
            'extracted_at' => 'datetime',
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

    public function auftragszeile(): HasOne
    {
        return $this->hasOne(Auftragszeile::class);
    }

    /**
     * Absoluter Pfad der hinterlegten PDF, inklusive der von Gotenberg
     * gelegentlich doppelt angehängten Endung.
     */
    public function absolutePdfPath(): ?string
    {
        if (! filled($this->pdf_path)) {
            return null;
        }

        $disk = Storage::disk('local');

        if ($disk->exists($this->pdf_path)) {
            return $disk->path($this->pdf_path);
        }

        $doubleExtensionPath = $this->pdf_path.'.pdf';

        if ($disk->exists($doubleExtensionPath)) {
            return $disk->path($doubleExtensionPath);
        }

        return null;
    }

    public function angabenVollstaendig(): bool
    {
        return $this->fehlendeAngaben() === [];
    }

    /**
     * @return list<string>
     */
    public function fehlendeAngaben(): array
    {
        $fehlend = [];

        if (! filled($this->lieferantenname)) {
            $fehlend[] = 'Lieferant';
        }

        if (! filled($this->nummer)) {
            $fehlend[] = 'Angebotsnummer';
        }

        if ($this->angebotsdatum === null) {
            $fehlend[] = 'Angebotsdatum';
        }

        if ($this->betrag_netto === null) {
            $fehlend[] = 'Netto';
        }

        return $fehlend;
    }

    public function analyseStatus(): string
    {
        return match ($this->extraction_status) {
            'pending', 'processing' => 'laeuft',
            'failed' => 'fehlgeschlagen',
            'done' => 'abgeschlossen',
            default => 'offen',
        };
    }

    public function analyseLabel(): string
    {
        return match ($this->analyseStatus()) {
            'laeuft' => 'Analyse läuft',
            'fehlgeschlagen' => 'Analyse fehlgeschlagen',
            'abgeschlossen' => 'Analyse abgeschlossen',
            default => 'Keine Analyse',
        };
    }
}
