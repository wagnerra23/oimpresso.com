<?php

namespace Modules\Fiscal\Providers;

use Illuminate\Support\Facades\Event;
use Illuminate\Support\ServiceProvider;
use Modules\Fiscal\Console\Commands\HabilitarBusinessCommand;
use Modules\Fiscal\Listeners\InvalidaCockpitCacheListener;
use Modules\NfeBrasil\Events\NFCeAutorizada;
use Modules\NfeBrasil\Events\NFeAutorizada;

class FiscalServiceProvider extends ServiceProvider
{
    protected string $moduleName = 'Fiscal';

    protected string $moduleNameLower = 'fiscal';

    public function boot(): void
    {
        $this->registerTranslations();
        $this->registerConfig();
        $this->registerViews();
        $this->loadMigrationsFrom(module_path($this->moduleName, 'Database/Migrations'));

        // GAP-FISCAL-002 — invalida cache KPIs Cockpit quando NFe/NFCe autorizada
        Event::listen(NFeAutorizada::class, InvalidaCockpitCacheListener::class);
        Event::listen(NFCeAutorizada::class, InvalidaCockpitCacheListener::class);

        if ($this->app->runningInConsole()) {
            $this->commands([
                HabilitarBusinessCommand::class,
            ]);
        }
    }

    public function register(): void
    {
        $this->app->register(RouteServiceProvider::class);
    }

    protected function registerConfig(): void
    {
        $configPath = module_path($this->moduleName, 'Config/config.php');
        if (file_exists($configPath)) {
            $this->publishes([
                $configPath => config_path($this->moduleNameLower . '.php'),
            ], 'config');

            $this->mergeConfigFrom($configPath, $this->moduleNameLower);
        }
    }

    protected function registerTranslations(): void
    {
        $langPath = resource_path('lang/modules/' . $this->moduleNameLower);

        if (is_dir($langPath)) {
            $this->loadTranslationsFrom($langPath, $this->moduleNameLower);
            $this->loadJsonTranslationsFrom($langPath);
        } else {
            $modulePath = module_path($this->moduleName, 'Resources/lang');
            if (is_dir($modulePath)) {
                $this->loadTranslationsFrom($modulePath, $this->moduleNameLower);
                $this->loadJsonTranslationsFrom($modulePath);
            }
        }
    }

    protected function registerViews(): void
    {
        $viewPath = resource_path('views/modules/' . $this->moduleNameLower);
        $sourcePath = module_path($this->moduleName, 'Resources/views');

        if (is_dir($sourcePath)) {
            $this->publishes([
                $sourcePath => $viewPath,
            ], ['views', $this->moduleNameLower . '-module-views']);

            $this->loadViewsFrom(
                array_merge(array_map(fn ($path) => $path . '/modules/' . $this->moduleNameLower, \Config::get('view.paths', [])), [$sourcePath]),
                $this->moduleNameLower
            );
        }
    }

    public function provides(): array
    {
        return [];
    }
}
