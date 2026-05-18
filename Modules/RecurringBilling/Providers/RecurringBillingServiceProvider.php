<?php

namespace Modules\RecurringBilling\Providers;

use Illuminate\Support\Facades\Blade;
use Illuminate\Support\ServiceProvider;

class RecurringBillingServiceProvider extends ServiceProvider
{
    protected string $moduleName = 'RecurringBilling';

    protected string $moduleNameLower = 'recurringbilling';

    /**
     * Boot the application events.
     */
    public function boot(): void
    {
        $this->registerCommands();
        $this->registerCommandSchedules();
        $this->registerTranslations();
        $this->registerConfig();
        $this->registerViews();
        $this->registerObservers();
        $this->loadMigrationsFrom(module_path($this->moduleName, 'Database/Migrations'));
    }

    /**
     * Observers — Onda 2 v9,75 cached fields denormalizados.
     *
     * SubscriptionCachedFieldsObserver mantém rb_subscriptions cached cols
     * (total_paid_cached / failed_count_cached / total_revenue_cached /
     * contact_phone_cached) em sincronia com rb_invoices state changes +
     * Contact updates.
     *
     * Backfill bulk: php artisan rb:backfill-cached-fields
     */
    protected function registerObservers(): void
    {
        $observer = new \Modules\RecurringBilling\Observers\SubscriptionCachedFieldsObserver();

        \Modules\RecurringBilling\Models\Invoice::saved(function ($invoice) use ($observer) {
            $observer->invoiceSaved($invoice);
        });

        \Modules\RecurringBilling\Models\Invoice::deleted(function ($invoice) use ($observer) {
            $observer->invoiceDeleted($invoice);
        });

        \Modules\RecurringBilling\Models\Subscription::saving(function ($sub) use ($observer) {
            $observer->subscriptionSaving($sub);
        });
    }

    /**
     * Register the service provider.
     */
    public function register(): void
    {
        $this->app->register(RouteServiceProvider::class);

        $this->registerInterPixServices();
        $this->registerRepositories();
        $this->registerPolicies();
    }

    /**
     * Policies — Onda 3 v9,75 autorização granular Subscription.
     */
    protected function registerPolicies(): void
    {
        \Illuminate\Support\Facades\Gate::policy(
            \Modules\RecurringBilling\Models\Subscription::class,
            \Modules\RecurringBilling\Policies\SubscriptionPolicy::class
        );
    }

    /**
     * Wave 18 D4 saturação RecurringBilling (69→95) — Repositories como singleton.
     *
     * SoC brutal (Constituição v2 §5): Controllers/Services injetam via type-hint
     * em vez de chamar Model::where() inline. Singleton porque Repositories
     * são stateless (sem estado mutável entre requests).
     *
     * @see Modules\RecurringBilling\Repositories\SubscriptionRepository
     * @see Modules\RecurringBilling\Repositories\InvoiceRepository
     */
    protected function registerRepositories(): void
    {
        $this->app->singleton(\Modules\RecurringBilling\Repositories\SubscriptionRepository::class);
        $this->app->singleton(\Modules\RecurringBilling\Repositories\InvoiceRepository::class);

        // Wave 18 RETRY D4 saturação granular — Services extraídos de Controllers/inline
        $this->app->singleton(\Modules\RecurringBilling\Services\AssinaturaService::class);
        $this->app->singleton(\Modules\RecurringBilling\Services\Boleto\BoletoCredentialResolver::class);

        // Wave 23 D2 — bind interface => concrete (reuse cross-module Financeiro/NfeBrasil)
        $this->app->bind(
            \Modules\RecurringBilling\Contracts\BoletoCredentialResolverInterface::class,
            \Modules\RecurringBilling\Services\Boleto\BoletoCredentialResolver::class,
        );
    }

    /**
     * Registra services Inter PJ PIX cobrança imediata (US-RB-050) + webhook
     * receiver (US-RB-051). Singleton — InterPixCobrancaService partilha cliente
     * HTTP mTLS + token cache OAuth entre chamadas do mesmo request.
     *
     * Implementação física do service vem em PRs paralelos (M1+M2). Wiring aqui
     * registra o binding pra Laravel resolver via container. Multi-tenant Tier 0
     * IRREVOGÁVEL (ADR 0093): credenciais lidas SEMPRE de `rb_boleto_credentials`
     * por `business_id`, NUNCA de `config('services.inter')`. Config aqui é só
     * shared infra (api_base_url, paths default).
     */
    protected function registerInterPixServices(): void
    {
        $serviceClass = 'Modules\\RecurringBilling\\Services\\Inter\\InterPixCobrancaService';

        if (class_exists($serviceClass)) {
            $this->app->singleton($serviceClass);
        }
    }

    /**
     * Register commands in the format of Command::class
     */
    protected function registerCommands(): void
    {
        if ($this->app->runningInConsole()) {
            $this->commands([
                \Modules\RecurringBilling\Console\Commands\SyncBankBalancesCommand::class,
                \Modules\RecurringBilling\Console\Commands\RecurringHealthCommand::class,
                \Modules\RecurringBilling\Console\Commands\BackfillCachedFieldsCommand::class,
            ]);
        }
    }

    /**
     * Register command Schedules.
     */
    protected function registerCommandSchedules(): void
    {
        // $this->app->booted(function () {
        //     $schedule = $this->app->make(Schedule::class);
        //     $schedule->command('inspire')->hourly();
        // });
    }

    /**
     * Register translations.
     */
    public function registerTranslations(): void
    {
        $langPath = resource_path('lang/modules/'.$this->moduleNameLower);

        if (is_dir($langPath)) {
            $this->loadTranslationsFrom($langPath, $this->moduleNameLower);
            $this->loadJsonTranslationsFrom($langPath);
        } else {
            $this->loadTranslationsFrom(module_path($this->moduleName, 'Resources/lang'), $this->moduleNameLower);
            $this->loadJsonTranslationsFrom(module_path($this->moduleName, 'Resources/lang'));
        }
    }

    /**
     * Register config.
     */
    protected function registerConfig(): void
    {
        $this->publishes([module_path($this->moduleName, 'Config/config.php') => config_path($this->moduleNameLower.'.php')], 'config');
        $this->mergeConfigFrom(module_path($this->moduleName, 'Config/config.php'), $this->moduleNameLower);
    }

    /**
     * Register views.
     */
    public function registerViews(): void
    {
        $viewPath = resource_path('views/modules/'.$this->moduleNameLower);
        $sourcePath = module_path($this->moduleName, 'Resources/views');

        $this->publishes([$sourcePath => $viewPath], ['views', $this->moduleNameLower.'-module-views']);

        $this->loadViewsFrom(array_merge($this->getPublishableViewPaths(), [$sourcePath]), $this->moduleNameLower);

        $componentNamespace = str_replace('/', '\\', config('modules.namespace').'\\'.$this->moduleName.'\\'.config('modules.paths.generator.component-class.path'));
        Blade::componentNamespace($componentNamespace, $this->moduleNameLower);
    }

    /**
     * Get the services provided by the provider.
     */
    public function provides(): array
    {
        return [];
    }

    private function getPublishableViewPaths(): array
    {
        $paths = [];
        foreach (config('view.paths') as $path) {
            if (is_dir($path.'/modules/'.$this->moduleNameLower)) {
                $paths[] = $path.'/modules/'.$this->moduleNameLower;
            }
        }

        return $paths;
    }
}
