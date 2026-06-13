<?php

namespace Modules\Superadmin\Providers;

use App\Business;
use App\System;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Database\Eloquent\Factory;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;
use Modules\PaymentGateway\Events\CobrancaPaga;
use Modules\PaymentGateway\Events\CobrancaVencida;
use Modules\Superadmin\Console\SuperadminHealthCommand;
use Modules\Superadmin\Entities\Subscription;
use Modules\Superadmin\Entities\SuperadminFrontendPage;
use Modules\Superadmin\Listeners\OnCobrancaPagaUpdateSubscription;
use Modules\Superadmin\Listeners\OnCobrancaVencidaBloqueaSubscription;
use Modules\Superadmin\Observers\BusinessAutoSubscriptionObserver;

class SuperadminServiceProvider extends ServiceProvider
{
    /**
     * Guard contra duplicação do listener — nWidart pode rodar boot() 2x.
     * Pattern documentado em memory/reference/project-officeimpresso-modulo.md.
     */
    private static bool $paymentgatewayListenersRegistered = false;

    /**
     * Guard contra duplicação do Business observer (mesmo motivo nWidart).
     */
    private static bool $businessObserverRegistered = false;

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
        $this->registerPaymentGatewayListeners();
        $this->registerBusinessAutoSubscriptionObserver();

        // Wave 23 D9.c — registra o health-check command (espelha ConnectorServiceProvider).
        // Sem este registro o comando superadmin:health nunca aparece em Artisan::all().
        $this->commands([
            SuperadminHealthCommand::class,
        ]);

        view::composer('superadmin::layouts.partials.active_subscription', function ($view) {
            $business_id = session()->get('user.business_id');
            $module_util = new \App\Utils\ModuleUtil();
            $is_installed = $module_util->isSuperadminInstalled();
            if ($is_installed) {
                $__subscription = Subscription::active_subscription($business_id);
            } else {
                $__subscription = null;
            }

            $view->with(compact('__subscription'));
        });

        view::composer(['layouts.partials.home_header'], function ($view) {
            $frontend_pages = SuperadminFrontendPage::where('is_shown', 1)
                                                ->orderBy('menu_order', 'asc')
                                                ->get();
            $view->with(compact('frontend_pages'));
        });

        //Set superadmin currency
        view::composer(['superadmin::layouts.partials.currency'], function ($view) {
            $__system_currency = System::getCurrency();
            $view->with(compact('__system_currency'));
        });

        $this->registerScheduleCommands();
    }

    public function registerScheduleCommands()
    {
        $env = config('app.env');
        //schedule command for sending subscription expiry alert
        if ($env === 'live') {
            $this->app->booted(function () {
                $schedule = $this->app->make(Schedule::class);
                $schedule->command('pos:sendSubscriptionExpiryAlert')->daily();
                // ADR 0170 Onda 5.B — emite cobrança PIX Automático pra Subscriptions
                // waiting com trial expirado (auto-onboarding).
                $schedule->command('paymentgateway:emit-trial-expired')->dailyAt('08:00');
            });
        }
    }

    /**
     * ADR 0170 Onda 5.B SIMPLIFICADA — Business::created → Subscription waiting
     * automática. Cobre UI Superadmin + API Delphi simultaneamente.
     */
    protected function registerBusinessAutoSubscriptionObserver(): void
    {
        if (self::$businessObserverRegistered) {
            return;
        }

        Business::observe(BusinessAutoSubscriptionObserver::class);
        self::$businessObserverRegistered = true;
    }

    /**
     * ADR 0170 Onda 5 SIMPLIFICADA — escuta eventos canônicos do PaymentGateway
     * pra renovar/bloquear Superadmin::Subscription do tenant. Cross-tenant
     * intencional Wagner-only (pattern Subscription.php:30).
     *
     * Guard `$paymentgatewayListenersRegistered` evita registro duplicado em
     * boot() chamado 2x (pegadinha nWidart catalogada).
     */
    protected function registerPaymentGatewayListeners(): void
    {
        if (self::$paymentgatewayListenersRegistered) {
            return;
        }

        Event::listen(CobrancaPaga::class, [OnCobrancaPagaUpdateSubscription::class, 'handle']);
        Event::listen(CobrancaVencida::class, [OnCobrancaVencidaBloqueaSubscription::class, 'handle']);

        self::$paymentgatewayListenersRegistered = true;
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
            __DIR__.'/../Config/config.php' => config_path('superadmin.php'),
        ], 'config');
        $this->mergeConfigFrom(
            __DIR__.'/../Config/config.php', 'superadmin'
        );
    }

    /**
     * Register views.
     *
     * @return void
     */
    public function registerViews()
    {
        $viewPath = resource_path('views/modules/superadmin');

        $sourcePath = __DIR__.'/../Resources/views';

        $this->publishes([
            $sourcePath => $viewPath,
        ], 'views');

        $this->loadViewsFrom(array_merge(array_map(function ($path) {
            return $path.'/modules/superadmin';
        }, config('view.paths')), [$sourcePath]), 'superadmin');
    }

    /**
     * Register translations.
     *
     * @return void
     */
    public function registerTranslations()
    {
        $langPath = resource_path('lang/modules/superadmin');

        if (is_dir($langPath)) {
            $this->loadTranslationsFrom($langPath, 'superadmin');
        } else {
            $this->loadTranslationsFrom(__DIR__.'/../Resources/lang', 'superadmin');
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
