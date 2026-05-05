<?php

declare(strict_types=1);

namespace Hwkdo\IntranetAppBestellungen\Models;

use Hwkdo\IntranetAppBestellungen\Database\Factories\PositionFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;

class Position extends Model implements HasMedia
{
    use HasFactory;
    use InteractsWithMedia;

    protected $table = 'intranet_app_bestellungen_positionen';

    protected $guarded = [];

    protected static function newFactory(): PositionFactory
    {
        return PositionFactory::new();
    }

    protected function casts(): array
    {
        return [
            'menge' => 'decimal:2',
            'preis' => 'decimal:2',
            'anlagen' => 'array',
        ];
    }

    public function bestellung(): BelongsTo
    {
        return $this->belongsTo(Bestellung::class);
    }

    public function art(): BelongsTo
    {
        return $this->belongsTo(Art::class);
    }

    public function registerMediaCollections(): void
    {
        $this->addMediaCollection('position_pdf')
            ->singleFile()
            ->acceptsMimeTypes(['application/pdf']);
    }

    public function gesamt(): float
    {
        return (float) $this->menge * (float) $this->preis;
    }

    public function hasPositionPdf(): bool
    {
        return $this->hasMedia('position_pdf');
    }
}
