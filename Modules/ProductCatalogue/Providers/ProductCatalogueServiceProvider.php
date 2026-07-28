<?php

namespace Modules\ProductCatalogue\Providers;

use Illuminate\Database\Eloquent\Factory;
use Illuminate\Support\ServiceProvider;
use Modules\ProductCatalogue\Console\Commands\ProductCatalogueHealthCommand;

class ProductCatalogueServiceProvider extends ServiceProvider
{
    /**
     * Boot the application events.
     *
     * @return void
     */
    public function boot()
    {
        $this->registerTranslations();
        $this->registerConfig();
        $this->registerViews();
        $this->registerFactories();
        $this->loadMigrationsFrom(__DIR__.'/../Database/Migrations');

        // Sem isto o comando existe no disco mas NUNCA chega ao Artisan — mesmo
        // defeito medido em ProjectMgmt (2026-07-28). Eram os 2 únicos de 33
        // módulos que não registravam os próprios comandos.
        if ($this->app->runningInConsole()) {
            $this->commands([
                ProductCatalogueHealthCommand::class,
            ]);
        }
    }

    /**
     * Register the service provider.
     *
     * @return void
     */
    public function register()
    {
        $this->app->register(RouteServiceProvider::class);
    }

    /**
     * Register config.
     *
     * @return void
     */
    protected function registerConfig()
    {
        $this->publishes([
            __DIR__.'/../Config/config.php' => config_path('productcatalogue.php'),
        ], 'config');
        $this->mergeConfigFrom(
            __DIR__.'/../Config/config.php', 'productcatalogue'
        );
    }

    /**
     * Register views.
     *
     * @return void
     */
    public function registerViews()
    {
        $viewPath = resource_path('views/modules/productcatalogue');

        $sourcePath = __DIR__.'/../Resources/views';

        $this->publishes([
            $sourcePath => $viewPath,
        ], 'views');

        $this->loadViewsFrom(array_merge(array_map(function ($path) {
            return $path.'/modules/productcatalogue';
        }, config('view.paths')), [$sourcePath]), 'productcatalogue');
    }

    /**
     * Register translations.
     *
     * @return void
     */
    public function registerTranslations()
    {
        $langPath = resource_path('lang/modules/productcatalogue');

        if (is_dir($langPath)) {
            $this->loadTranslationsFrom($langPath, 'productcatalogue');
        } else {
            $this->loadTranslationsFrom(__DIR__.'/../Resources/lang', 'productcatalogue');
        }
    }

    /**
     * Register an additional directory of factories.
     *
     * @return void
     */
    public function registerFactories()
    {
        if (! app()->environment('production') && $this->app->runningInConsole()) {
            app(Factory::class)->load(__DIR__.'/../Database/factories');
        }
    }

    /**
     * Get the services provided by the provider.
     *
     * @return array
     */
    public function provides()
    {
        return [];
    }
}
