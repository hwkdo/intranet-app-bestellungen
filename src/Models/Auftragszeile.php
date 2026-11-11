<?php

declare(strict_types=1);

namespace Hwkdo\IntranetAppBestellungen\Models;

use Hwkdo\IntranetAppBestellungen\Database\Factories\AuftragszeileFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Auftragszeile extends Model
{
    use HasFactory;

    protected $table = 'intranet_app_bestellungen_auftragszeilen';

    protected $guarded = [];

    protected static function newFactory(): AuftragszeileFactory
    {
        return AuftragszeileFactory::new();
    }

    protected function casts(): array
    {
        return [
            'nr' => 'integer',
            'angebotsdatum' => 'date',
            'betrag_netto' => 'decimal:2',
            'betrag_brutto' => 'decimal:2',
        ];
    }

    public function bestellung(): BelongsTo
    {
        return $this->belongsTo(Bestellung::class);
    }

    public function angebot(): BelongsTo
    {
        return $this->belongsTo(Angebot::class);
    }

    public function bezugszeile(): string
    {
        $datum = $this->angebotsdatum?->format('d.m.Y') ?? '–';
        $nummer = filled($this->angebotsnummer) ? (string) $this->angebotsnummer : '–';

        return 'Ihr Angebot vom '.$datum.' Nr. '.$nummer;
    }
}
