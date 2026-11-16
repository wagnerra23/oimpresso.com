<?php

namespace Modules\Manufacturing\Providers;

use Illuminate\Database\Eloquent\Factory;
use Illuminate\Support\ServiceProvider;
use App\Utils\Util;

class ManufacturingServiceProvider extends ServiceProvider
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
        $this->registerScheduleCommands();
    }

    /**
     * Register the service provider.
     *
     * @return void
     */
    public function register()
    {
        $this->app->register(RouteServiceProvider::class);

        // US-PCP-031 · UC-FLX-14: a receita é a fonte do material reservado na venda sob encomenda.
        $this->app->bind(\App\Contracts\Fabricacao\InsumosDaReceita::class, \Modules\Manufacturing\Services\InsumosDaReceitaService::class);
        // UC-PIDX-44 · CV1: o item que entra em alguma receita é "Material" no filtro "Uso" da consulta de produto.
        $this->app->bind(\App\Contracts\Fabricacao\ProdutosDeReceita::class, \Modules\Manufacturing\Services\ProdutosDeReceitaService::class);
        // UC-PIDX-48 · D-29: a ficha da receita, só leitura, no painel da consulta de produto.
        $this->app->bind(\App\Contracts\Fabricacao\ReceitaDoProduto::class, \Modules\Manufacturing\Services\RecipeBomService::class);
    }

    /**
     * Register config.
     *
     * @return void
     */
    protected function registerConfig()
    {
        $this->publishes([
            __DIR__.'/../Config/config.php' => config_path('manufacturing.php'),
        ], 'config');
        $this->mergeConfigFrom(
            __DIR__.'/../Config/config.php', 'manufacturing'
        );
    }

    /**
     * Register views.
     *
     * @return void
     */
    public function registerViews()
    {
        $viewPath = resource_path('views/modules/manufacturing');

        $sourcePath = __DIR__.'/../Resources/views';

        $this->publishes([
            $sourcePath => $viewPath,
        ], 'views');

        $this->loadViewsFrom(array_merge(array_map(function ($path) {
            return $path.'/modules/manufacturing';
        }, config('view.paths')), [$sourcePath]), 'manufacturing');
    }

    /**
     * Register translations.
     *
     * @return void
     */
    public function registerTranslations()
    {
        $langPath = resource_path('lang/modules/manufacturing');

        if (is_dir($langPath)) {
            $this->loadTranslationsFrom($langPath, 'manufacturing');
        } else {
            $this->loadTranslationsFrom(__DIR__.'/../Resources/lang', 'manufacturing');
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

    public function registerScheduleCommands()
    {
        
    }
}
