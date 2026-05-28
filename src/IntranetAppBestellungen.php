<?php

declare(strict_types=1);

namespace Hwkdo\IntranetAppBestellungen;

use Hwkdo\IntranetAppBase\Interfaces\IntranetAppInterface;
use Hwkdo\IntranetAppBase\Interfaces\ProvidesTasksInterface;
use Hwkdo\IntranetAppBestellungen\Tasks\BestellungAusfuehrenTaskProvider;
use Hwkdo\IntranetAppBestellungen\Tasks\FreigabeAusstehendTaskProvider;
use Hwkdo\IntranetAppBestellungen\Tasks\InterneBestellungAusstehendTaskProvider;
use Illuminate\Support\Collection;

class IntranetAppBestellungen implements IntranetAppInterface, ProvidesTasksInterface
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
        return \Hwkdo\IntranetAppBestellungen\Data\UserSettings::class;
    }

    public static function appSettingsClass(): ?string
    {
        return \Hwkdo\IntranetAppBestellungen\Data\AppSettings::class;
    }

    public static function mcpServers(): array
    {
        return [];
    }

    /**
     * @return array<class-string<\Hwkdo\IntranetAppBase\Interfaces\TaskProviderInterface>>
     */
    public static function taskProviders(): array
    {
        return [
            FreigabeAusstehendTaskProvider::class,
            BestellungAusfuehrenTaskProvider::class,
            InterneBestellungAusstehendTaskProvider::class,
        ];
    }
}
