<?php

namespace Modules\Fiscal\Providers;

use Illuminate\Support\ServiceProvider;

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
