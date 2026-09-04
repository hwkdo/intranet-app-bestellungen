<?php

declare(strict_types=1);

namespace Hwkdo\IntranetAppBestellungen\Search;

use Hwkdo\IntranetAppBestellungen\Models\Bestellung;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Support\Collection;

class BestellungSearch
{
    /**
     * @return Collection<int, Bestellung>
     */
    public static function query(string $query, Authenticatable $user, int $limit = 50): Collection
    {
        $trimmed = trim($query);
        if ($trimmed === '' || mb_strlen($trimmed) < 2) {
            return collect();
        }

        $builder = Bestellung::search($trimmed);

        if (! self::canManageBestellungen($user)) {
            $builder->where('visible_user_ids', (int) $user->getAuthIdentifier());
        }

        return $builder
            ->query(function ($eloquent) use ($user): void {
                $eloquent
                    ->with(['projekt', 'user', 'freigeber'])
                    ->visibleTo($user);
            })
            ->take($limit)
            ->get();
    }

    private static function canManageBestellungen(Authenticatable $user): bool
    {
        if (! method_exists($user, 'can')) {
            return false;
        }

        return $user->can('manage-app-bestellungen');
    }
}
