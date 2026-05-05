<?php

declare(strict_types=1);

namespace Hwkdo\IntranetAppBestellungen\Tasks;

use Hwkdo\IntranetAppBase\Data\TaskItem;
use Hwkdo\IntranetAppBase\Interfaces\TaskProviderInterface;
use Hwkdo\IntranetAppBestellungen\IntranetAppBestellungen;
use Hwkdo\IntranetAppBestellungen\Models\Bestellung;
use Hwkdo\IntranetAppBestellungen\Services\WertgrenzenService;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

class FreigabeAusstehendTaskProvider implements TaskProviderInterface
{
    public function __construct(
        private readonly WertgrenzenService $wertgrenzen,
    ) {}

    public function getLabel(): string
    {
        return 'Bestellungen zur Freigabe';
    }

    public function getTasksForUser(Authenticatable $user): Collection
    {
        $userId = method_exists($user, 'getKey') ? $user->getKey() : null;
        if (! $userId) {
            return collect();
        }

        return Bestellung::query()
            ->with(['user'])
            ->freigabePending()
            ->where(function (Builder $q) use ($userId): void {
                $q->where('freigeber_id', $userId)->orWhereNull('freigeber_id');
            })
            ->latest()
            ->limit(50)
            ->get()
            ->filter(fn (Bestellung $b): bool => $this->wertgrenzen->darfFreigeben($user, $b))
            ->map(fn (Bestellung $b): TaskItem => new TaskItem(
                title: 'BEN '.$b->nummer.' – '.($b->lieferantenname ?? 'Bestellung'),
                url: route('apps.bestellungen.detail', ['bestellung' => $b, 'aktion' => 'freigeben']),
                appIdentifier: IntranetAppBestellungen::identifier(),
                appName: IntranetAppBestellungen::app_name(),
                appIcon: IntranetAppBestellungen::app_icon(),
                description: 'Betrag: '.number_format((float) $b->gesamtbetrag, 2, ',', '.').' € · von '.optional($b->user)->name,
                badge: $b->status?->label(),
                priority: 50,
            ))
            ->values();
    }
}
