<?php

declare(strict_types=1);

namespace Hwkdo\IntranetAppBestellungen\Models;

use Illuminate\Database\Eloquent\Model;

class LieferantCache extends Model
{
    protected $table = 'intranet_app_bestellungen_lieferanten_cache';

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'synced_at' => 'datetime',
        ];
    }
}
