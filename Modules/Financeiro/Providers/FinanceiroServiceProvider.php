<?php

namespace Modules\Financeiro\Providers;

use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\ServiceProvider;
use Modules\Financeiro\Events\CashRegisterClosed;
use Modules\Financeiro\Events\TituloCriado;
use Modules\Financeiro\Listeners\OnCashRegisterClosedCreateFinanceiroTitulo;
use Modules\Financeiro\Listeners\OnCobrancaPagaCreateFinanceiroTitulo;
use Modules\Financeiro\Listeners\OnTituloCriadoLog;
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

        // PR F (2026-05-25) G9 — Event TituloCriado dispatched no store() manual
        // (UnificadoController). Listener stub log INFO + abre caminho extensão
        // (notify, cache, accounting). Não dispara em auto-criação observers.
        Event::listen(TituloCriado::class, [OnTituloCriadoLog::class, 'handle']);

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
        // US-FIN-072 — NFS-e autorizada/cancelada lança/estorna a baixa de retenção (chave por negócio, default OFF)
        if (class_exists(\Modules\NFSe\Models\NfseEmissao::class)) {
            \Modules\NFSe\Models\NfseEmissao::observe(\Modules\Financeiro\Observers\NfseEmissaoRetencaoObserver::class);
        }
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

        // US-FIN-079 — banco que paga título a pagar. Só é chamado por PagamentoBancoService,
        // atrás de FINANCEIRO_PAGAMENTO_BANCO_ENABLED e da aprovação do usuário.
        $this->app->bind(
            \Modules\Financeiro\Contracts\PagadorBanco::class,
            \Modules\Financeiro\Services\Banking\InterPagadorBanco::class
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
                // US-FIN-072 — prévia (só --dry-run) do ajuste por retenção da NFS-e.
                \Modules\Financeiro\Console\Commands\IssRetidoAjusteCommand::class,
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
                // Incidente num_uf #2279/#2280 — re-sincroniza fin_titulos inflados
                // com o final_total JÁ CORRIGIDO do core. O fix do core (#2280) foi
                // UPDATE direto (não dispara Observer), deixando o espelho fin_titulos
                // congelado no valor-lixo. DRY-RUN default, --business Tier 0, append-only.
                \Modules\Financeiro\Console\Commands\ResyncFromCoreCommand::class,
                // Fase 2 ADR 0236 — backfill OFX (fin_bank_statement_lines) →
                // fin_extrato_lancamentos (canônica). Idempotente, --business
                // obrigatório (Tier 0), --dry default-seguro. Unifica external_id.
                \Modules\Financeiro\Console\Commands\BackfillExtratoOfxCommand::class,
                // US-FIN-070 — custeio RKW do mês, só leitura (aplicar é ação na tela).
                \Modules\Financeiro\Console\Commands\CusteioRkwCommand::class,
                // US-FIN-078 — DDA → contas a pagar sugeridas (FINANCEIRO_DDA_ENABLED, default OFF).
                \Modules\Financeiro\Console\Commands\DdaSyncCommand::class,
                // US-FIN-071 · UC-FLX-29 — régua de cobrança D+1/D+5/D+15 (default OFF por business).
                \Modules\Financeiro\Console\Commands\ReguaCobrancaCommand::class,
                // US-FIN-079 — confirma no banco os pagamentos de título aguardando e baixa os pagos.
                \Modules\Financeiro\Console\Commands\PagamentosBancoSyncCommand::class,
                // US-FIN-074 — extrato Pluggy (Open Finance) → fin_extrato_lancamentos.
                \Modules\Financeiro\Console\Commands\PluggySyncCommand::class,
                \Modules\Financeiro\Console\Commands\ImportarExtratoOfxCommand::class,
            ]);
        }
    }

    /**
     * Register command Schedules.
     */
    protected function registerCommandSchedules(): void
    {
        // US-FIN-071 · UC-FLX-29 — régua de cobrança diária. O comando só enfileira job para
        // business com a régua LIGADA em common_settings (default OFF), então agendar não envia
        // nada a ninguém até o negócio ligar.
        $this->callAfterResolving(\Illuminate\Console\Scheduling\Schedule::class, function ($schedule): void {
            $schedule->command('financeiro:regua-cobranca')
                ->dailyAt('09:00')
                ->withoutOverlapping()
                ->onOneServer();

            // US-FIN-074 — extrato Pluggy. Só roda com PLUGGY_ENABLED=true (default false); depois
            // do sync Inter das 07:00, para a conciliação abrir o dia com as duas fontes.
            $schedule->command('financeiro:pluggy-sync')
                ->dailyAt('07:15')
                ->when(fn () => (bool) config('financeiro.pluggy.enabled', false))
                ->withoutOverlapping()
                ->onOneServer();

            // US-FIN-079 — confirmação dos pagamentos pelo banco. Só consulta (nunca envia) e só
            // com FINANCEIRO_PAGAMENTO_BANCO_ENABLED=true (default false).
            $schedule->command('financeiro:pagamentos-banco-sync')
                ->everyFifteenMinutes()
                ->when(fn () => (bool) config('financeiro.pagamento_banco.enabled', false))
                ->withoutOverlapping()
                ->onOneServer();
        });
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
