<?php

declare(strict_types=1);

namespace Hwkdo\IntranetAppBestellungen\Models;

use App\Models\User;
use Hwkdo\IntranetAppBestellungen\Database\Factories\BestellungFactory;
use Hwkdo\IntranetAppBestellungen\Enums\AktionTyp;
use Hwkdo\IntranetAppBestellungen\Enums\BestellungStatus;
use Hwkdo\IntranetAppBestellungen\Enums\BestellungTyp;
use Hwkdo\IntranetAppBestellungen\Support\PlatzhalterLieferant;
use Hwkdo\IntranetAppBestellungen\Services\WertgrenzenService;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Laravel\Scout\Searchable;

class Bestellung extends Model
{
    use HasFactory;
    use Searchable;
    use SoftDeletes;

    protected $table = 'intranet_app_bestellungen';

    protected $guarded = [];

    /**
     * Freigabe-relevante Aktionen, deren Akteur als „involviert“ gilt.
     *
     * @return list<AktionTyp>
     */
    public static function freigabeBeteiligungsAktionTypen(): array
    {
        return [
            AktionTyp::Weitergeleitet,
            AktionTyp::ErstFreigegeben,
            AktionTyp::Freigegeben,
            AktionTyp::Abgelehnt,
        ];
    }

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

    public function searchableAs(): string
    {
        return 'intranet_app_bestellungen';
    }

    /**
     * @return array<string, mixed>
     */
    public function toSearchableArray(): array
    {
        $this->loadMissing(['projekt', 'positionen', 'aktionen']);

        return [
            'id' => (string) $this->id,
            'nummer' => $this->normalizedSearchString($this->nummer),
            'betreff' => $this->normalizedSearchString($this->betreff),
            'projekt_name' => $this->normalizedSearchString($this->projekt?->name),
            'positionen_text' => $this->positionenSearchText(),
            'status' => $this->normalizedSearchString($this->status?->value ?? $this->status),
            'visible_user_ids' => $this->visibleUserIds(),
            'created_at' => $this->created_at?->timestamp ?? now()->timestamp,
        ];
    }

    public function typesenseSearchParameters(): array
    {
        return [
            'infix' => 'always',
        ];
    }

    /**
     * @param  Builder<Bestellung>  $query
     * @return Builder<Bestellung>
     */
    protected function makeAllSearchableUsing(Builder $query): Builder
    {
        return $query->with(['projekt', 'positionen', 'aktionen']);
    }

    /**
     * Nutzer-IDs, die diese Bestellung in der Suche finden dürfen (ohne Admin-Recht).
     *
     * @return list<int>
     */
    public function visibleUserIds(): array
    {
        $ids = collect([
            $this->user_id,
            $this->freigeber_id,
            $this->besteller_id,
            $this->interner_empfaenger_user_id,
        ]);

        $aktionTypen = array_map(
            static fn (AktionTyp $typ): string => $typ->value,
            self::freigabeBeteiligungsAktionTypen(),
        );

        $aktionUserIds = $this->relationLoaded('aktionen')
            ? $this->aktionen
                ->filter(fn (Aktion $aktion): bool => in_array($aktion->typ?->value ?? $aktion->typ, $aktionTypen, true))
                ->pluck('user_id')
            : $this->aktionen()
                ->whereIn('typ', $aktionTypen)
                ->whereNotNull('user_id')
                ->pluck('user_id');

        return $ids
            ->merge($aktionUserIds)
            ->filter(fn (mixed $id): bool => $id !== null && $id !== '')
            ->map(fn (mixed $id): int => (int) $id)
            ->unique()
            ->values()
            ->all();
    }

    public function istSichtbarFuer(Authenticatable $user): bool
    {
        if (method_exists($user, 'can') && $user->can('manage-app-bestellungen')) {
            return true;
        }

        if (in_array((int) $user->getAuthIdentifier(), $this->visibleUserIds(), true)) {
            return true;
        }

        if ($this->status?->isFreigabePending() && $user instanceof User) {
            return app(WertgrenzenService::class)->darfFreigeben($user, $this);
        }

        return false;
    }

    /**
     * @param  Builder<Bestellung>  $query
     * @return Builder<Bestellung>
     */
    public function scopeVisibleTo(Builder $query, Authenticatable $user): Builder
    {
        if (method_exists($user, 'can') && $user->can('manage-app-bestellungen')) {
            return $query;
        }

        $userId = (int) $user->getAuthIdentifier();
        $aktionTypen = array_map(
            static fn (AktionTyp $typ): string => $typ->value,
            self::freigabeBeteiligungsAktionTypen(),
        );

        return $query->where(function (Builder $q) use ($userId, $aktionTypen): void {
            $q->where('user_id', $userId)
                ->orWhere('freigeber_id', $userId)
                ->orWhere('besteller_id', $userId)
                ->orWhere('interner_empfaenger_user_id', $userId)
                ->orWhereHas(
                    'aktionen',
                    fn (Builder $aktionen): Builder => $aktionen
                        ->where('user_id', $userId)
                        ->whereIn('typ', $aktionTypen),
                );
        });
    }

    private function positionenSearchText(): string
    {
        $teile = $this->positionen
            ->map(function (Position $position): string {
                return trim(implode(' ', array_filter([
                    $this->normalizedSearchString($position->oberbegriff),
                    $this->normalizedSearchString($position->bezeichnung),
                ])));
            })
            ->filter()
            ->values()
            ->all();

        return implode(' ', $teile);
    }

    private function normalizedSearchString(mixed $value): string
    {
        if ($value === null) {
            return '';
        }

        return trim((string) $value);
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

    public function scopeFreigegeben(Builder $query): Builder
    {
        return $query->where('status', BestellungStatus::Freigegeben);
    }

    public function scopeExtern(Builder $query): Builder
    {
        return $query->where('typ', '!=', BestellungTyp::Intern);
    }

    public function darfVonUserBestelltAbschliessen(User $user): bool
    {
        if ($this->status !== BestellungStatus::Freigegeben) {
            return false;
        }

        if ($this->istIntern()) {
            return (int) $this->interner_empfaenger_user_id === (int) $user->getKey();
        }

        return (int) $this->user_id === (int) $user->getKey()
            || $user->can('manage-app-bestellungen');
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

    /**
     * Offene Bestellungen des Anforderers, bei denen Freigabe oder Bestellung bei jemand anderem liegt.
     *
     * @param  Builder<Bestellung>  $query
     * @return Builder<Bestellung>
     */
    public function scopeWartendFuerAnforderer(Builder $query, int $userId): Builder
    {
        return $query
            ->where('user_id', $userId)
            ->where(function (Builder $q) use ($userId): void {
                $q->where(function (Builder $q) use ($userId): void {
                    $q->freigabePending()
                        ->where(function (Builder $q) use ($userId): void {
                            $q->whereNull('freigeber_id')
                                ->orWhere('freigeber_id', '!=', $userId);
                        });
                })->orWhere(function (Builder $q) use ($userId): void {
                    $q->where('status', BestellungStatus::Freigegeben)
                        ->where('typ', BestellungTyp::Intern)
                        ->whereNotNull('interner_empfaenger_user_id')
                        ->where('interner_empfaenger_user_id', '!=', $userId);
                });
            });
    }

    public function istInternBearbeitungOffen(): bool
    {
        return $this->istIntern() && $this->status === BestellungStatus::Freigegeben;
    }

    public function wartehinweisFuerAnforderer(): string
    {
        if ($this->status?->isFreigabePending()) {
            $name = trim((string) ($this->freigeber?->name ?? ''));

            return $name !== ''
                ? 'Wartet auf Freigabe durch '.$name
                : 'Wartet auf Freigabe';
        }

        if ($this->status === BestellungStatus::Freigegeben) {
            $name = trim((string) ($this->internerEmpfaenger?->name ?? ''));

            return $name !== ''
                ? 'Wartet auf Bestellung durch '.$name
                : 'Wartet auf Bestellung';
        }

        return $this->status?->label() ?? '';
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
