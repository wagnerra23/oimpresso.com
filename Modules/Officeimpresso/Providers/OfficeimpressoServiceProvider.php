<?php

namespace Modules\Officeimpresso\Providers;

use Illuminate\Database\Eloquent\Factory;
use Illuminate\Support\ServiceProvider;

class OfficeimpressoServiceProvider extends ServiceProvider
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

        if ($this->app->runningInConsole()) {
            $this->commands([
                \Modules\Officeimpresso\Console\ParseLicencaLogCommand::class,
            ]);
        }

        // Listener pra Passport AccessTokenCreated — grava login_success com
        // contexto completo (IP, UA, user, client). Substitui trigger MySQL.
        \Illuminate\Support\Facades\Event::listen(
            \Laravel\Passport\Events\AccessTokenCreated::class,
            \Modules\Officeimpresso\Listeners\LogPassportAccessToken::class
        );

        // Middleware registrado como alias 'log.desktop' pra uso nas rotas.
        $this->app['router']->aliasMiddleware(
            'log.desktop',
            \Modules\Officeimpresso\Http\Middleware\LogDesktopAccess::class
        );
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
