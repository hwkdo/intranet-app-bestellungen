<?php

declare(strict_types=1);

namespace Hwkdo\IntranetAppBestellungen\Support;

use Hwkdo\IntranetAppBestellungen\Services\InterneBestellerService;
use Illuminate\Support\Collection;

final class BestellungenNavigation
{
    /**
     * @return list<array<string, mixed>>
     */
    public static function items(): array
    {
        $items = [
            ['label' => 'Übersicht', 'href' => route('apps.bestellungen.index'), 'icon' => 'home', 'description' => 'Zurück zur Übersicht', 'buttonText' => 'Übersicht anzeigen'],
            ['label' => 'Neue Bestellung', 'href' => route('apps.bestellungen.erstellen'), 'icon' => 'plus-circle', 'description' => 'Neue Bestellung anlegen', 'buttonText' => 'Bestellung erstellen'],
            ['label' => 'Meine Bestellungen', 'href' => route('apps.bestellungen.meine'), 'icon' => 'document-text', 'description' => 'Meine Bestellungen anzeigen', 'buttonText' => 'Meine Bestellungen'],
            ['label' => 'Suche', 'href' => route('apps.bestellungen.search'), 'icon' => 'magnifying-glass', 'description' => 'Bestellungen nach BEN, Betreff, Projekt oder Position suchen', 'buttonText' => 'Suche öffnen'],
            ['label' => 'Projekte', 'href' => route('apps.bestellungen.projekte.index'), 'icon' => 'folder', 'description' => 'Bestellungen in Projekten bündeln', 'buttonText' => 'Projekte öffnen'],
            ['label' => 'Freigaben', 'href' => route('apps.bestellungen.freigaben'), 'icon' => 'check-badge', 'description' => 'Bestellungen zur Freigabe', 'buttonText' => 'Freigaben öffnen'],
        ];

        if (auth()->check() && app(InterneBestellerService::class)->istMitglied(auth()->user())) {
            $items[] = [
                'label' => 'Interne Bestellungen',
                'href' => route('apps.bestellungen.interne'),
                'icon' => 'building-office-2',
                'description' => 'Ihnen zugewiesene interne Bedarfsanforderungen bearbeiten',
                'buttonText' => 'Interne Bestellungen öffnen',
            ];
        }

        return array_merge($items, [
            ['label' => 'Benachrichtigungen', 'href' => route('apps.bestellungen.settings.notifications'), 'icon' => 'bell', 'description' => 'Benachrichtigungen dieser App konfigurieren', 'buttonText' => 'Benachrichtigungen öffnen'],
            ['label' => 'App-Info', 'href' => route('apps.bestellungen.info'), 'icon' => 'information-circle', 'description' => 'Installierte Version und Release-Historie', 'buttonText' => 'App-Info anzeigen'],
            ['label' => 'Admin', 'href' => route('apps.bestellungen.admin.index'), 'icon' => 'shield-check', 'description' => 'Administrationsbereich verwalten', 'buttonText' => 'Admin öffnen', 'permission' => 'manage-app-bestellungen'],
        ]);
    }

    /**
     * @return array{main: list<array<string, mixed>>, settings: list<array<string, mixed>>}
     */
    public static function grouped(): array
    {
        /** @var Collection<int, array<string, mixed>> $linkItems */
        $linkItems = collect(self::items())
            ->filter(fn (array $item): bool => ($item['type'] ?? '') !== 'separator' && isset($item['href']))
            ->values();

        $usesWelcomeSection = $linkItems->contains(
            fn (array $item): bool => array_key_exists('welcomeSection', $item),
        );

        if ($usesWelcomeSection) {
            $main = $linkItems->filter(fn (array $item): bool => ($item['welcomeSection'] ?? 'main') === 'main');
            $settings = $linkItems->filter(fn (array $item): bool => ($item['welcomeSection'] ?? '') === 'settings');
        } else {
            $main = $linkItems->filter(function (array $item): bool {
                return ! in_array($item['label'], ['Meine Einstellungen', 'Admin', 'Admin-Settings'], true)
                    && ! str_contains((string) $item['href'], '/settings/')
                    && ! str_contains((string) $item['href'], '/admin');
            });
            $settings = $linkItems->filter(function (array $item): bool {
                return in_array($item['label'], ['Meine Einstellungen', 'Admin', 'Admin-Settings'], true)
                    || str_contains((string) $item['href'], '/settings/')
                    || str_contains((string) $item['href'], '/admin');
            });
        }

        return [
            'main' => $main->values()->all(),
            'settings' => $settings->values()->all(),
        ];
    }
}
