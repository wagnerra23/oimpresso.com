<?php

namespace Modules\Compras\Providers;

use Illuminate\Support\Facades\Blade;
use Illuminate\Support\ServiceProvider;

/**
 * ServiceProvider do módulo Compras.
 *
 * Wave 1 scaffold (US-COM-001..004) — registra apenas RouteServiceProvider,
 * config, views e migrations. Sem event listeners ainda — a bridge
 * NfeDfeRecebido → Transaction(type=purchase) entra na Wave 6 (ImportarDfeComoCompraJob).
 *
 * Tier 0 — multi-tenant ADR 0093: todo controller scopa via session('user.business_id')
 * e queries via TransactionUtil já-scopado. Sem novo global scope necessário porque
 * tabela `transactions` (canônica polimórfica) já é scopada upstream.
 *
 * @see memory/requisitos/Compras/SPEC.md
 * @see memory/decisions/proposals/compras-modulo-greenfield-hibrido.md
 */
class ComprasServiceProvider extends ServiceProvider
{
    protected string $moduleName = 'Compras';

    protected string $moduleNameLower = 'compras';

    public function boot(): void
    {
        $this->registerTranslations();
        $this->registerConfig();
        $this->registerViews();
        $this->loadMigrationsFrom(module_path($this->moduleName, 'Database/Migrations'));

        // UC-FLX-33: congela o custo do insumo na linha de orçamento (venda em rascunho).
        // Só lê compras; nunca derruba a venda (ver o docblock do observer).
        \App\TransactionSellLine::observe(\Modules\Compras\Observers\TransactionSellLineCustoObserver::class);
        // US-PROD-024: compra recebida recalcula o custo médio móvel (flag compras.custo_medio_movel).
        \App\Transaction::observe(\Modules\Compras\Observers\CompraRecebidaCustoMedioObserver::class);

        $this->registerCommands();
        $this->registerCommandSchedules();
    }

    protected function registerCommands(): void
    {
        if ($this->app->runningInConsole()) {
            $this->commands([
                // US-COM-027 · UC-FLX-19 — estoque mínimo vira requisição de compra (default OFF por business).
                \Modules\Compras\Console\Commands\RequisicaoEstoqueMinimoCommand::class,
                // US-PROD-024 — semente do custo médio móvel (dry-run por padrão).
                \Modules\Compras\Console\Commands\CustoMedioSemearCommand::class,
            ]);
        }
    }

    /**
     * US-COM-027 · UC-FLX-19 — diário. O comando só enfileira job para business com a regra
     * LIGADA em common_settings (default OFF): agendar não cria requisição para ninguém até o
     * negócio ligar. Nunca move estoque nem cria pedido de compra.
     */
    protected function registerCommandSchedules(): void
    {
        $this->callAfterResolving(\Illuminate\Console\Scheduling\Schedule::class, function ($schedule): void {
            $schedule->command('compras:requisicao-estoque-minimo')
                ->dailyAt('06:30')
                ->withoutOverlapping()
                ->onOneServer();
        });
    }

    public function register(): void
    {
        $this->app->register(RouteServiceProvider::class);

        // O núcleo pede o custo do insumo pela interface, sem importar o módulo.
        $this->app->bind(\App\Contracts\Compras\CustoDoInsumo::class, \Modules\Compras\Services\CustoDoInsumoService::class);
        // US-PROD-024: a margem da venda lê o custo médio móvel do momento da venda.
        $this->app->bind(\App\Contracts\Compras\CustoMedio::class, \Modules\Compras\Services\CustoMedioMovel::class);
        // UC-FLX-17: o recebimento da compra aplica a regra de custo e preço da empresa.
        $this->app->bind(\App\Contracts\Compras\CustoDoRecebimento::class, \Modules\Compras\Services\AplicarCustoDoRecebimento::class);
        // UC-FLX-19: a tela Fluxos liga e desliga a requisição pelo estoque mínimo.
        $this->app->bind(\App\Contracts\Compras\RequisicaoEstoqueMinimo::class, \Modules\Compras\Services\RequisicaoEstoqueMinimoService::class);
        // US-COM-028: o detalhe da compra lista os preços propostos para aprovar ou recusar.
        $this->app->bind(\App\Contracts\Compras\PropostasDePreco::class, \Modules\Compras\Services\PropostasDePreco::class);
    }

    protected function registerTranslations(): void
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

    protected function registerConfig(): void
    {
        $this->publishes([module_path($this->moduleName, 'Config/config.php') => config_path($this->moduleNameLower.'.php')], 'config');
        $this->mergeConfigFrom(module_path($this->moduleName, 'Config/config.php'), $this->moduleNameLower);
    }

    public function registerViews(): void
    {
        $viewPath = resource_path('views/modules/'.$this->moduleNameLower);
        $sourcePath = module_path($this->moduleName, 'Resources/views');

        $this->publishes([$sourcePath => $viewPath], ['views', $this->moduleNameLower.'-module-views']);

        $this->loadViewsFrom(array_merge($this->getPublishableViewPaths(), [$sourcePath]), $this->moduleNameLower);

        $componentNamespace = str_replace('/', '\\', config('modules.namespace').'\\'.$this->moduleName.'\\'.config('modules.paths.generator.component-class.path'));
        Blade::componentNamespace($componentNamespace, $this->moduleNameLower);
    }

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
