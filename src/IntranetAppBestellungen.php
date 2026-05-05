<?php

declare(strict_types=1);

namespace Hwkdo\IntranetAppBestellungen;

use Hwkdo\IntranetAppBase\Interfaces\IntranetAppInterface;
use Hwkdo\IntranetAppBase\Interfaces\ProvidesTasksInterface;
use Hwkdo\IntranetAppBase\Interfaces\TaskProviderInterface;
use Hwkdo\IntranetAppBestellungen\Data\AppSettings;
use Hwkdo\IntranetAppBestellungen\Data\UserSettings;
use Hwkdo\IntranetAppBestellungen\Tasks\FreigabeAusstehendTaskProvider;
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
        return UserSettings::class;
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
        ];
    }
}
