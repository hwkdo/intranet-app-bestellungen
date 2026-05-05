<?php

declare(strict_types=1);

namespace Hwkdo\IntranetAppBestellungen\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Notiz extends Model
{
    protected $table = 'intranet_app_bestellungen_notizen';

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'an_d3_gesendet' => 'boolean',
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
}
