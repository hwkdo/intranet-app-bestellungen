<?php

declare(strict_types=1);

namespace Hwkdo\IntranetAppBestellungen\Models;

use Hwkdo\IntranetAppBestellungen\Database\Factories\ArtFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Art extends Model
{
    use HasFactory;

    protected $table = 'intranet_app_bestellungen_arten';

    protected $guarded = [];

    protected static function newFactory(): ArtFactory
    {
        return ArtFactory::new();
    }

    protected function casts(): array
    {
        return [
            'aktiv' => 'boolean',
            'sortierung' => 'integer',
        ];
    }

    public function anlagen(): HasMany
    {
        return $this->hasMany(Anlage::class);
    }

    public function scopeAktiv(Builder $query): Builder
    {
        return $query->where('aktiv', true);
    }
}
