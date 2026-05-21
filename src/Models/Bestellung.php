<?php

declare(strict_types=1);

namespace Hwkdo\IntranetAppBestellungen\Models;

use App\Models\User;
use Hwkdo\IntranetAppBestellungen\Database\Factories\BestellungFactory;
use Hwkdo\IntranetAppBestellungen\Enums\BestellungStatus;
use Hwkdo\IntranetAppBestellungen\Enums\BestellungTyp;
use Hwkdo\IntranetAppBestellungen\Support\PlatzhalterLieferant;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Bestellung extends Model
{
    use HasFactory;
    use SoftDeletes;

    protected $table = 'intranet_app_bestellungen';

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'status' => BestellungStatus::class,
            'typ' => BestellungTyp::class,
            'kontierung' => 'array',
            'gruppen' => 'array',
            'lieferanschrift' => 'array',
            'gesamtbetrag' => 'decimal:2',
            'haushaltsjahr' => 'integer',
            'freigabe_stufe_aktuell' => 'integer',
            'd3_pushed_at' => 'datetime',
            'bestellt_at' => 'datetime',
        ];
    }

    protected static function newFactory(): BestellungFactory
    {
        return BestellungFactory::new();
    }

    public function positionen(): HasMany
    {
        return $this->hasMany(Position::class);
    }

    public function angebote(): HasMany
    {
        return $this->hasMany(Angebot::class);
    }

    public function notizen(): HasMany
    {
        return $this->hasMany(Notiz::class);
    }

    public function aktionen(): HasMany
    {
        return $this->hasMany(Aktion::class)->latest();
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function internerEmpfaenger(): BelongsTo
    {
        return $this->belongsTo(User::class, 'interner_empfaenger_user_id');
    }

    public function freigeber(): BelongsTo
    {
        return $this->belongsTo(User::class, 'freigeber_id');
    }

    public function besteller(): BelongsTo
    {
        return $this->belongsTo(User::class, 'besteller_id');
    }

    public function lieferanschriftUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'lieferanschrift_user_id');
    }

    public function vorlage(): BelongsTo
    {
        return $this->belongsTo(self::class, 'wiederholt_von_id');
    }

    public function projekt(): BelongsTo
    {
        return $this->belongsTo(Projekt::class, 'projekt_id');
    }

    public function scopeFreigabePending(Builder $query): Builder
    {
        return $query->whereIn('status', [
            BestellungStatus::ZurFreigabe->value,
            BestellungStatus::ZurZweitenFreigabe->value,
        ]);
    }

    public function scopeForUser(Builder $query, int $userId): Builder
    {
        return $query->where('user_id', $userId);
    }

    public function scopeFuerInternenEmpfaenger(Builder $query, int $userId): Builder
    {
        return $query
            ->where('typ', BestellungTyp::Intern)
            ->where('interner_empfaenger_user_id', $userId);
    }

    public function scopeInternBearbeitungOffen(Builder $query): Builder
    {
        return $query->where('status', BestellungStatus::Freigegeben);
    }

    public function istInternBearbeitungOffen(): bool
    {
        return $this->istIntern() && $this->status === BestellungStatus::Freigegeben;
    }

    public function refreshGesamtbetrag(): void
    {
        $this->gesamtbetrag = $this->positionen()
            ->get()
            ->sum(fn (Position $position): float => (float) $position->menge * (float) $position->preis);
        $this->save();
    }

    public function darfBearbeitenWerden(): bool
    {
        return $this->status === BestellungStatus::Entwurf;
    }

    public function istInD3(): bool
    {
        return ! is_null($this->d3id);
    }

    public function istIntern(): bool
    {
        return $this->typ === BestellungTyp::Intern;
    }

    public function benoetigtFinalenLieferantenVorD3(): bool
    {
        return $this->istIntern() && PlatzhalterLieferant::istPlatzhalter($this->lieferantennummer);
    }
}
