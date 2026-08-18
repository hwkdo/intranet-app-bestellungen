<?php

declare(strict_types=1);

namespace Hwkdo\IntranetAppBestellungen\Dashboard;

use Hwkdo\IntranetAppBase\Data\DashboardWidgetDefinition;
use Hwkdo\IntranetAppBase\Interfaces\DashboardWidgetProviderInterface;

class BestellungenDashboardWidgetProvider implements DashboardWidgetProviderInterface
{
    public const KEY_OFFENE_BESTELLUNGEN = 'offene-bestellungen';

    /**
     * @return array<DashboardWidgetDefinition>
     */
    public static function widgets(): array
    {
        return [
            new DashboardWidgetDefinition(
                key: self::KEY_OFFENE_BESTELLUNGEN,
                title: 'Bestellungen in Bearbeitung',
                description: 'Aktueller Stand Ihrer angeforderten Bestellungen, die auf Freigabe oder Bestellung warten',
                component: 'intranet-app-bestellungen::apps.bestellungen.widgets.offene-bestellungen',
                defaultW: 6,
                defaultH: 4,
                minW: 4,
                minH: 3,
                defaultEnabled: true,
            ),
        ];
    }
}
