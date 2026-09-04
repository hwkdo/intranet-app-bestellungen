<?php

declare(strict_types=1);

namespace Hwkdo\IntranetAppBestellungen\Search;

use Hwkdo\IntranetAppBase\Data\SearchResult;
use Hwkdo\IntranetAppBase\Interfaces\SearchSourceInterface;
use Hwkdo\IntranetAppBestellungen\IntranetAppBestellungen;
use Hwkdo\IntranetAppBestellungen\Models\Bestellung;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Support\Collection;

class BestellungenSearchSource implements SearchSourceInterface
{
    public function key(): string
    {
        return 'bestellungen.bestellungen';
    }

    public function label(): string
    {
        return 'Bestellungen';
    }

    public function appIdentifier(): string
    {
        return IntranetAppBestellungen::identifier();
    }

    public function appName(): string
    {
        return IntranetAppBestellungen::app_name();
    }

    public function icon(): string
    {
        return IntranetAppBestellungen::app_icon();
    }

    public function isAvailableFor(Authenticatable $user): bool
    {
        if (! method_exists($user, 'can')) {
            return true;
        }

        return $user->can('see-app-'.$this->appIdentifier());
    }

    public function search(string $query, Authenticatable $user, int $limit): Collection
    {
        return BestellungSearch::query($query, $user, $limit)
            ->map(fn (Bestellung $bestellung): SearchResult => new SearchResult(
                title: $bestellung->nummer.' – '.($bestellung->betreff ?: 'Ohne Betreff'),
                url: route('apps.bestellungen.detail', $bestellung),
                appIdentifier: $this->appIdentifier(),
                appName: $this->appName(),
                icon: $this->icon(),
                favoriteKey: $this->key().':'.$bestellung->id,
                subtitle: $this->subtitle($bestellung),
                sourceKey: $this->key(),
            ))
            ->values();
    }

    public function resolveFavorite(string $entityId, Authenticatable $user): ?SearchResult
    {
        if (! $this->isAvailableFor($user)) {
            return null;
        }

        $bestellung = Bestellung::query()
            ->with(['projekt'])
            ->visibleTo($user)
            ->whereKey($entityId)
            ->first();

        if ($bestellung === null) {
            return null;
        }

        return new SearchResult(
            title: $bestellung->nummer.' – '.($bestellung->betreff ?: 'Ohne Betreff'),
            url: route('apps.bestellungen.detail', $bestellung),
            appIdentifier: $this->appIdentifier(),
            appName: $this->appName(),
            icon: $this->icon(),
            favoriteKey: $this->key().':'.$bestellung->id,
            subtitle: $this->subtitle($bestellung),
            sourceKey: $this->key(),
        );
    }

    private function subtitle(Bestellung $bestellung): ?string
    {
        $parts = array_values(array_filter([
            $bestellung->status?->label(),
            trim((string) ($bestellung->projekt?->name ?? '')),
        ], fn (?string $part): bool => $part !== null && $part !== ''));

        if ($parts === []) {
            return null;
        }

        return implode(' · ', $parts);
    }
}
