<?php

declare(strict_types=1);

namespace Hwkdo\IntranetAppBestellungen;

use Hwkdo\IntranetAppBestellungen\Commands\SyncLieferantenNutzungCommand;
use Hwkdo\IntranetAppBestellungen\Commands\SyncStammdatenCommand;
use Hwkdo\IntranetAppBestellungen\Models\Aktion;
use Hwkdo\IntranetAppBestellungen\Models\Bestellung;
use Hwkdo\IntranetAppBestellungen\Models\Position;
use Hwkdo\IntranetAppBestellungen\Models\Projekt;
use Hwkdo\IntranetAppBestellungen\Observers\AktionSearchObserver;
use Hwkdo\IntranetAppBestellungen\Observers\PositionSearchObserver;
use Hwkdo\IntranetAppBestellungen\Observers\ProjektSearchObserver;
use Illuminate\Support\Facades\Config;
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
        $this->loadRoutesFrom(__DIR__.'/../routes/api.php');

        Position::observe(PositionSearchObserver::class);
        Aktion::observe(AktionSearchObserver::class);
        Projekt::observe(ProjektSearchObserver::class);

        $this->configureTypesenseIndexSettings();
    }

    protected function configureTypesenseIndexSettings(): void
    {
        $modelSettings = Config::get('scout.typesense.model-settings', []);

        $modelSettings[Bestellung::class] = [
            'collection-schema' => [
                'fields' => [
                    ['name' => 'id', 'type' => 'string'],
                    ['name' => 'nummer', 'type' => 'string', 'optional' => true, 'infix' => true],
                    ['name' => 'betreff', 'type' => 'string', 'optional' => true, 'infix' => true],
                    ['name' => 'projekt_name', 'type' => 'string', 'optional' => true, 'infix' => true],
                    ['name' => 'positionen_text', 'type' => 'string', 'optional' => true, 'infix' => true],
                    ['name' => 'status', 'type' => 'string', 'optional' => true],
                    ['name' => 'visible_user_ids', 'type' => 'int64[]', 'optional' => true],
                    ['name' => 'created_at', 'type' => 'int64'],
                ],
                'default_sorting_field' => 'created_at',
            ],
            'search-parameters' => [
                'query_by' => 'nummer,betreff,projekt_name,positionen_text',
                'prefix' => true,
            ],
        ];

        Config::set('scout.typesense.model-settings', $modelSettings);
    }
}
