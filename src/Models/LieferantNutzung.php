<?php

declare(strict_types=1);

namespace Hwkdo\IntranetAppBestellungen\Models;

use Illuminate\Database\Eloquent\Model;

class LieferantNutzung extends Model
{
    protected $table = 'intranet_app_bestellungen_lieferant_nutzung';

    protected $primaryKey = 'lieferantennummer';

    protected $keyType = 'string';

    public $incrementing = false;

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'legacy_synced_at' => 'datetime',
            'legacy_bestellungen_count' => 'integer',
            'v3_bestellungen_count' => 'integer',
        ];
    }
}
