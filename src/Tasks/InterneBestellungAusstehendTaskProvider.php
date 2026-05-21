<?php

declare(strict_types=1);

namespace Hwkdo\IntranetAppBestellungen\Tasks;

use Hwkdo\IntranetAppBase\Data\TaskItem;
use Hwkdo\IntranetAppBase\Interfaces\TaskProviderInterface;
use Hwkdo\IntranetAppBestellungen\IntranetAppBestellungen;
use Hwkdo\IntranetAppBestellungen\Models\Bestellung;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Support\Collection;

class InterneBestellungAusstehendTaskProvider implements TaskProviderInterface
{
    public function getLabel(): string
    {
        return 'Interne Bestellungen bearbeiten';
    }

    public function getTasksForUser(Authenticatable $user): Collection
    {
        $userId = method_exists($user, 'getKey') ? $user->getKey() : null;
        if (! $userId) {
            return collect();
        }

        return Bestellung::query()
            ->with(['user'])
            ->fuerInternenEmpfaenger((int) $userId)
            ->internBearbeitungOffen()
            ->latest()
            ->limit(50)
            ->get()
            ->map(fn (Bestellung $b): TaskItem => new TaskItem(
                title: $b->nummer.' – '.($b->betreff ?: ($b->lieferantenname ?? 'Interne Bestellung')),
                url: route('apps.bestellungen.detail', ['bestellung' => $b, 'aktion' => 'bestellen']),
                appIdentifier: IntranetAppBestellungen::identifier(),
                appName: IntranetAppBestellungen::app_name(),
                appIcon: IntranetAppBestellungen::app_icon(),
                description: 'Betrag: '.number_format((float) $b->gesamtbetrag, 2, ',', '.').' € · von '.optional($b->user)->name,
                badge: $b->status?->label(),
                priority: 45,
            ))
            ->values();
    }
}
