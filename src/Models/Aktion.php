<?php

declare(strict_types=1);

namespace Hwkdo\IntranetAppBestellungen\Models;

use App\Models\User;
use Hwkdo\IntranetAppBestellungen\Enums\AktionTyp;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Aktion extends Model
{
    protected $table = 'intranet_app_bestellungen_aktionen';

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'typ' => AktionTyp::class,
            'payload' => 'array',
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
