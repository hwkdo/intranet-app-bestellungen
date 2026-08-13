<?php

declare(strict_types=1);

namespace Hwkdo\IntranetAppBestellungen\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class KostenstelleCache extends Model
{
    protected $table = 'intranet_app_bestellungen_kostenstellen_cache';

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'aktiv' => 'boolean',
            'synced_at' => 'datetime',
        ];
    }

    public function scopeAktiv(Builder $query): Builder
    {
        return $query->where('aktiv', true);
    }

    public function scopeInaktiv(Builder $query): Builder
    {
        return $query->where('aktiv', false);
    }
}
