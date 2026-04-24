<?php

namespace Modules\Officeimpresso\Providers;

use Illuminate\Database\Eloquent\Factory;
use Illuminate\Support\ServiceProvider;

class OfficeimpressoServiceProvider extends ServiceProvider
{
    /** @var bool flag pra evitar double-registration de listener */
    protected static $listenerRegistered = false;

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

        if ($this->app->runningInConsole()) {
            $this->commands([
                \Modules\Officeimpresso\Console\ParseLicencaLogCommand::class,
            ]);
        }

        // Middlewares de log:
        // 'log.desktop' — API moderna /api/officeimpresso/* (flat `hd`)
        // 'log.delphi'  — API legada /connector/api/* (extrai HD de NOME_TABELA=LICENCIAMENTO)
        $this->app['router']->aliasMiddleware(
            'log.desktop',
            \Modules\Officeimpresso\Http\Middleware\LogDesktopAccess::class
        );
        $this->app['router']->aliasMiddleware(
            'log.delphi',
            \Modules\Officeimpresso\Http\Middleware\LogDelphiAccess::class
        );
    }

    /**
     * Register the service provider.
     */
    public function register()
    {
        $this->app->register(RouteServiceProvider::class);

        // Listener pra AccessTokenCreated — guarded por flag static pra
        // evitar double-registration (provider pode ser carregado 2x pelo
        // nwidart/modules em algumas condicoes de boot).
        if (! self::$listenerRegistered) {
            \Illuminate\Support\Facades\Event::listen(
                \Laravel\Passport\Events\AccessTokenCreated::class,
                \Modules\Officeimpresso\Listeners\LogPassportAccessToken::class
            );
            self::$listenerRegistered = true;
        }
    }

    /**
     * Register config.
     *
     * @return void
     */
    protected function registerConfig()
    {
        $this->publishes([
            __DIR__.'/../Config/config.php' => config_path('officeimpresso.php'),
        ], 'config');
        $this->mergeConfigFrom(
            __DIR__.'/../Config/config.php', 'officeimpresso'
        );
    }

    /**
     * Register views.
     *
     * @return void
     */
    public function registerViews()
    {
        $viewPath = resource_path('views/modules/officeimpresso');

        $sourcePath = __DIR__.'/../Resources/views';

        $this->publishes([
            $sourcePath => $viewPath,
        ], 'views');

        $this->loadViewsFrom(array_merge(array_map(function ($path) {
            return $path.'/modules/officeimpresso';
        }, config('view.paths')), [$sourcePath]), 'officeimpresso');
    }

    /**
     * Register translations.
     *
     * @return void
     */
    public function registerTranslations()
    {
        $langPath = resource_path('lang/modules/officeimpresso');

        if (is_dir($langPath)) {
            $this->loadTranslationsFrom($langPath, 'officeimpresso');
        } else {
            $this->loadTranslationsFrom(__DIR__.'/../Resources/lang', 'officeimpresso');
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
