<?php

namespace Modules\Financeiro\Providers;

use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\ServiceProvider;
use Modules\Financeiro\Events\CashRegisterClosed;
use Modules\Financeiro\Listeners\OnCashRegisterClosedCreateFinanceiroTitulo;
use Modules\Financeiro\Listeners\OnCobrancaPagaCreateFinanceiroTitulo;
use Modules\PaymentGateway\Events\CobrancaPaga;

class FinanceiroServiceProvider extends ServiceProvider
{
    /**
     * Guard contra duplicação do listener — nWidart pode rodar boot() 2x.
     */
    private static bool $paymentgatewayListenersRegistered = false;

    protected string $moduleName = 'Financeiro';

    protected string $moduleNameLower = 'financeiro';

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
        $this->loadMigrationsFrom(module_path($this->moduleName, 'Database/Migrations'));
        $this->registerObservers();
        $this->registerPaymentGatewayListeners();
    }

    /**
     * ADR 0170 Onda 5 SIMPLIFICADA — auto-baixa de Titulo a receber em
     * fin_titulos quando cobrança SaaS é paga. Apenas business_id=1 (Wagner).
     */
    protected function registerPaymentGatewayListeners(): void
    {
        if (self::$paymentgatewayListenersRegistered) {
            return;
        }

        Event::listen(CobrancaPaga::class, [OnCobrancaPagaCreateFinanceiroTitulo::class, 'handle']);

        // ADR 0183 — Ponte cash_registers → fin_titulos (multi-caixa canon)
        Event::listen(CashRegisterClosed::class, [OnCashRegisterClosedCreateFinanceiroTitulo::class, 'handle']);

        self::$paymentgatewayListenersRegistered = true;
    }

    /**
     * Registra observers nas tabelas core do UltimatePOS.
     * Pattern explicado em ARCHITECTURE.md §5.2 e auto-memória reference_financeiro_integracao.md.
     *
     * Onda 2 (2026-04-25): + TransactionPaymentObserver pra baixa automática.
     */
    protected function registerObservers(): void
    {
        \App\Transaction::observe(\Modules\Financeiro\Observers\TransactionObserver::class);
        \App\TransactionPayment::observe(\Modules\Financeiro\Observers\TransactionPaymentObserver::class);
        // ADR 0183 — observa cash_registers pra detectar fechamento e disparar event
        \App\CashRegister::observe(\Modules\Financeiro\Observers\CashRegisterObserver::class);
    }

    /**
     * Register the service provider.
     */
    public function register(): void
    {
        $this->app->register(RouteServiceProvider::class);

        // Default BoletoStrategy: CnabDirectStrategy via lib eduardokum/laravel-boleto.
        // Sobrescrever via container binding em testes ou para usar GatewayStrategy
        // (futura — ADR ARQ-0003).
        $this->app->bind(
            \Modules\Financeiro\Contracts\BoletoStrategy::class,
            \Modules\Financeiro\Strategies\CnabDirectStrategy::class
        );

        // Wave 18 D4 — Repositories como singleton (sem state, cache OTel friendly).
        // Consumers injetam via type-hint nos Controllers/Services.
        $this->app->singleton(\Modules\Financeiro\Repositories\TituloRepository::class);
        // Wave 18 RETRY D4 saturação granular — BaixaRepository agrupa baixas (read-side).
        $this->app->singleton(\Modules\Financeiro\Repositories\BaixaRepository::class);
    }

    /**
     * Register commands in the format of Command::class
     */
    protected function registerCommands(): void
    {
        if ($this->app->runningInConsole()) {
            $this->commands([
                \Modules\Financeiro\Console\Commands\InstallCommand::class,
                // Wave 17 D9.c — Health check do módulo (governance v3 saturação 66→81).
                \Modules\Financeiro\Console\Commands\FinanceiroHealthCommand::class,
                // Backfill plano_conta_id em titulos NULL — DRE depende disso.
                // Wagner 2026-05-20: 18.054 titulos biz=4 com plano_conta_id NULL
                // (criados antes do schema fin_planos_conta). DRE renderiza vazia.
                \Modules\Financeiro\Console\Commands\BackfillPlanoContaCommand::class,
                // Wagner 2026-05-21 Fase 5 deprecação legacy — bridge transactions
                // tipo expense (core UltimatePOS) → fin_titulos AP (Financeiro).
                // Idempotente via UNIQUE(business_id, origem, origem_id, parcela_numero).
                \Modules\Financeiro\Console\Commands\BridgeExpenseToTitulosCommand::class,
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
