<?php

declare(strict_types=1);

namespace Hwkdo\IntranetAppBestellungen\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Anlage extends Model
{
    protected $table = 'intranet_app_bestellungen_anlagen';

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'aktiv' => 'boolean',
        ];
    }

    public function art(): BelongsTo
    {
        return $this->belongsTo(Art::class);
    }

    public function scopeAktiv(Builder $query): Builder
    {
        return $query->where('aktiv', true);
    }
}
