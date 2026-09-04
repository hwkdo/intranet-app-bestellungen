<?php

declare(strict_types=1);

namespace Hwkdo\IntranetAppBestellungen;

use Hwkdo\IntranetAppBase\Data\NotificationTypeDefinition;
use Hwkdo\IntranetAppBase\Data\SearchActionDefinition;
use Hwkdo\IntranetAppBase\Interfaces\DashboardWidgetProviderInterface;
use Hwkdo\IntranetAppBase\Interfaces\IntranetAppInterface;
use Hwkdo\IntranetAppBase\Interfaces\ProvidesDashboardWidgetsInterface;
use Hwkdo\IntranetAppBase\Interfaces\ProvidesNotificationsInterface;
use Hwkdo\IntranetAppBase\Interfaces\ProvidesSearchActionsInterface;
use Hwkdo\IntranetAppBase\Interfaces\ProvidesSearchInterface;
use Hwkdo\IntranetAppBase\Interfaces\ProvidesTasksInterface;
use Hwkdo\IntranetAppBase\Interfaces\SearchSourceInterface;
use Hwkdo\IntranetAppBase\Interfaces\TaskProviderInterface;
use Hwkdo\IntranetAppBestellungen\Dashboard\BestellungenDashboardWidgetProvider;
use Hwkdo\IntranetAppBestellungen\Data\AppSettings;
use Hwkdo\IntranetAppBestellungen\Search\BestellungenSearchSource;
use Hwkdo\IntranetAppBestellungen\Tasks\BestellungAusfuehrenTaskProvider;
use Hwkdo\IntranetAppBestellungen\Tasks\FreigabeAusstehendTaskProvider;
use Hwkdo\IntranetAppBestellungen\Tasks\InterneBestellungAusstehendTaskProvider;
use Illuminate\Support\Collection;

class IntranetAppBestellungen implements IntranetAppInterface, ProvidesDashboardWidgetsInterface, ProvidesNotificationsInterface, ProvidesSearchActionsInterface, ProvidesSearchInterface, ProvidesTasksInterface
{
    public static function app_name(): string
    {
        return 'Bestellungen';
    }

    public static function app_icon(): string
    {
        return 'shopping-cart';
    }

    public static function identifier(): string
    {
        return 'bestellungen';
    }

    public static function roles_admin(): Collection
    {
        return collect(config('intranet-app-bestellungen.roles.admin'));
    }

    public static function roles_user(): Collection
    {
        return collect(config('intranet-app-bestellungen.roles.user'));
    }

    public static function userSettingsClass(): ?string
    {
        return null;
    }

    public static function appSettingsClass(): ?string
    {
        return AppSettings::class;
    }

    public static function mcpServers(): array
    {
        return [];
    }

    /**
     * @return array<class-string<TaskProviderInterface>>
     */
    public static function taskProviders(): array
    {
        return [
            FreigabeAusstehendTaskProvider::class,
            BestellungAusfuehrenTaskProvider::class,
            InterneBestellungAusstehendTaskProvider::class,
        ];
    }

    /**
     * @return array<class-string<DashboardWidgetProviderInterface>>
     */
    public static function dashboardWidgetProviders(): array
    {
        return [
            BestellungenDashboardWidgetProvider::class,
        ];
    }

    public static function notificationTypes(): array
    {
        return [
            new NotificationTypeDefinition(
                key: 'bestellungen.order_approved',
                label: 'Bestellung freigegeben',
                appIdentifier: self::identifier(),
                appName: self::app_name(),
                description: 'Ihre Bestellung wurde freigegeben und kann bestellt werden.',
                mandatory: true,
            ),
        ];
    }

    public static function searchActions(): array
    {
        return [
            new SearchActionDefinition(
                key: 'bestellungen.create',
                title: 'Neue Bestellung',
                keywords: ['neue bestellung', 'bestellung erstellen', 'bestellung', 'bestellen'],
                routeName: 'apps.bestellungen.erstellen',
                appIdentifier: self::identifier(),
                appName: self::app_name(),
                icon: self::app_icon(),
                permission: 'see-app-bestellungen',
                subtitle: self::app_name(),
                sort: 100,
            ),
            new SearchActionDefinition(
                key: 'bestellungen.search',
                title: 'Bestellungen suchen',
                keywords: ['bestellung suchen', 'bestellungen suchen', 'ben suchen', 'suche bestellung'],
                routeName: 'apps.bestellungen.search',
                appIdentifier: self::identifier(),
                appName: self::app_name(),
                icon: 'magnifying-glass',
                permission: 'see-app-bestellungen',
                subtitle: self::app_name(),
                sort: 110,
            ),
        ];
    }

    /**
     * @return list<class-string<SearchSourceInterface>>
     */
    public static function searchSources(): array
    {
        return [
            BestellungenSearchSource::class,
        ];
    }
}
