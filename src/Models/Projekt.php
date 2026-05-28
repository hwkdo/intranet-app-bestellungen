<?php

declare(strict_types=1);

namespace Hwkdo\IntranetAppBestellungen\Models;

use App\Models\User;
use Hwkdo\IntranetAppBestellungen\Database\Factories\ProjektFactory;
use Hwkdo\IntranetAppBestellungen\Services\Projekt\ProjektIdGenerator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Projekt extends Model
{
    use HasFactory;

    protected $table = 'intranet_app_bestellungen_projekte';

    protected $guarded = [];

    protected static function newFactory(): ProjektFactory
    {
        return ProjektFactory::new();
    }

    protected static function booted(): void
    {
        static::creating(function (Projekt $projekt): void {
            if (filled($projekt->d3_projekt_id)) {
                return;
            }

            $projekt->d3_projekt_id = app(ProjektIdGenerator::class)->generate($projekt->name);
        });
    }

    public function ersteller(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function mitglieder(): BelongsToMany
    {
        return $this->belongsToMany(
            User::class,
            'intranet_app_bestellungen_projekt_mitglieder',
            'projekt_id',
            'user_id',
        )->withTimestamps();
    }

    public function bestellungen(): HasMany
    {
        return $this->hasMany(Bestellung::class, 'projekt_id');
    }

    public function istMitgliedOderErsteller(int $userId): bool
    {
        return $this->user_id === $userId
            || $this->mitglieder()->where('user_id', $userId)->exists();
    }

    public function scopeForUser(Builder $query, int $userId): Builder
    {
        return $query->where(function (Builder $q) use ($userId): void {
            $q->where('user_id', $userId)
                ->orWhereHas('mitglieder', fn (Builder $m): Builder => $m->where('users.id', $userId));
        });
    }
}
