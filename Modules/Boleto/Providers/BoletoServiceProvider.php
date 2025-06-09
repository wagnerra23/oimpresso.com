<?php

namespace Modules\Boleto\Providers;

use App\Utils\ModuleUtil;
use Illuminate\Database\Eloquent\Factory;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;

class BoletoServiceProvider extends ServiceProvider
{
    /**
     * Indicates if loading of the provider is deferred.
     *
     * @var bool
     */
    protected $defer = false;

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
        $this->loadMigrationsFrom(__DIR__ . '/../Database/Migrations');

        //TODO:Remove
        View::composer('boleto::layouts.partials.sidebar', function ($view) {
            if (auth()->user()->can('superadmin')) {
                $__is_mfg_enabled = true;
            } else {
                $business_id = session()->get('user.business_id');
                $module_util = new ModuleUtil();
                $__is_mfg_enabled = (boolean)$module_util->hasThePermissionInSubscription($business_id, 'boleto_module', 'superadmin_package');
            }

            $view->with(compact('__is_mfg_enabled'));
        });
    }

    /**
     * Register the service provider.
     *
     * @return void
     */
    public function register()
    {
        //
    }

    /**
     * Register config.
     *
     * @return void
     */
    protected function registerConfig()
    {
        $this->publishes([
            __DIR__.'/../Config/config.php' => config_path('boleto.php'),
        ], 'config');
        $this->mergeConfigFrom(
            __DIR__.'/../Config/config.php',
            'boleto'
        );
    }

    /**
     * Register views.
     *
     * @return void
     */
    public function registerViews()
    {
        $viewPath = resource_path('views/modules/boleto');

        $sourcePath = __DIR__.'/../Resources/views';

        $this->publishes([
            $sourcePath => $viewPath
        ], 'views');

        $this->loadViewsFrom(array_merge(array_map(function ($path) {
            return $path . '/modules/boleto';
        }, \Config::get('view.paths')), [$sourcePath]), 'boleto');
    }

    /**
     * Register translations.
     *
     * @return void
     */
    public function registerTranslations()
    {
        $langPath = resource_path('lang/modules/boleto');

        if (is_dir($langPath)) {
            $this->loadTranslationsFrom($langPath, 'boleto');
        } else {
            $this->loadTranslationsFrom(__DIR__ .'/../Resources/lang', 'boleto');
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
