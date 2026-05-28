<?php

declare(strict_types=1);

namespace Hwkdo\IntranetAppBestellungen\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Angebot extends Model
{
    protected $table = 'intranet_app_bestellungen_angebote';

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'betrag' => 'decimal:2',
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
}
