<?php

declare(strict_types=1);

namespace Hwkdo\IntranetAppBestellungen;

use Hwkdo\IntranetAppBestellungen\Commands\SyncLieferantenNutzungCommand;
use Hwkdo\IntranetAppBestellungen\Commands\SyncStammdatenCommand;
use Livewire\Livewire;
use Spatie\LaravelPackageTools\Package;
use Spatie\LaravelPackageTools\PackageServiceProvider;

class IntranetAppBestellungenServiceProvider extends PackageServiceProvider
{
    public function configurePackage(Package $package): void
    {
        $package
            ->name('intranet-app-bestellungen')
            ->hasConfigFile()
            ->hasViews()
            ->discoversMigrations()
            ->hasCommand(SyncStammdatenCommand::class)
            ->hasCommand(SyncLieferantenNutzungCommand::class);
    }

    public function bootingPackage(): void
    {
        Livewire::addNamespace(
            namespace: 'intranet-app-bestellungen',
            classNamespace: 'Hwkdo\\IntranetAppBestellungen\\Livewire',
            classPath: __DIR__.'/Livewire',
            classViewPath: __DIR__.'/../resources/views/livewire',
            viewPath: __DIR__.'/../resources/views/livewire'
        );
    }

    public function boot(): void
    {
        parent::boot();
        $this->loadRoutesFrom(__DIR__.'/../routes/web.php');
    }
}
