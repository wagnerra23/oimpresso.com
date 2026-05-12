<?php

namespace Modules\NfeBrasil\Providers;

use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\ServiceProvider;
use Modules\NfeBrasil\Events\FiscalRuleCreated;
use Modules\NfeBrasil\Events\FiscalRuleDeleted;
use Modules\NfeBrasil\Events\FiscalRuleUpdated;
use Modules\NfeBrasil\Events\NFCeAutorizada;
use Modules\NfeBrasil\Events\NFeAutorizada;
use Modules\NfeBrasil\Listeners\EmitirNFeAoReceberPagamento;
use Modules\NfeBrasil\Listeners\EmitirNfceAoFinalizarVenda;
use Modules\NfeBrasil\Listeners\EnviarDanfeNFCePorEmail;
use Modules\NfeBrasil\Listeners\EnviarDanfePorEmail;
use Modules\NfeBrasil\Listeners\SyncFiscalRuleToTaxRate;
use Modules\RecurringBilling\Events\InvoicePaid;

class NfeBrasilServiceProvider extends ServiceProvider
{
    protected string $moduleName = 'NfeBrasil';

    protected string $moduleNameLower = 'nfebrasil';

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

        // US-RB-044: cobrança recorrente paga → emite NFe modelo 55.
        // Flag default false até business ter cert + ncm_default + contact configurados.
        Event::listen(InvoicePaid::class, EmitirNFeAoReceberPagamento::class);

        // US-NFE-002 fase 1: venda finalizada no POS → emite NFC-e modelo 65.
        // Flag default false (rollout gradual). Filtra type='sell' + status='final' +
        // payment_status in (paid|partial) — vendas due/draft não emitem.
        Event::listen(\App\Events\SellCreatedOrModified::class, EmitirNfceAoFinalizarVenda::class);

        // US-NFE-044: NFe autorizada → envia DANFE PDF + XML por e-mail ao destinatário.
        // Flag default true (recorrência sempre notifica cliente).
        Event::listen(NFeAutorizada::class, EnviarDanfePorEmail::class);

        // US-NFE-002 fase 2B: NFC-e autorizada → envia DANFE NFC-e por e-mail ao consumidor.
        // Flag default false (NFC-e B2C frequentemente é "consumidor anônimo" sem email;
        // cliente liga via UI quando quer envio automático). Resolve email via
        // Transaction.contact (venda POS) — diferente de NFe55 que usa Invoice.contact.
        Event::listen(NFCeAutorizada::class, EnviarDanfeNFCePorEmail::class);

        // ADR ARQ-0005: bridge nfe_fiscal_rules → tax_rates (core UPos compat).
        // 1 listener handles 3 events via método explícito (não confiável p/ Laravel
        // discovery automático — registra cada par event→method).
        Event::listen(FiscalRuleCreated::class, [SyncFiscalRuleToTaxRate::class, 'handleCreated']);
        Event::listen(FiscalRuleUpdated::class, [SyncFiscalRuleToTaxRate::class, 'handleUpdated']);
        Event::listen(FiscalRuleDeleted::class, [SyncFiscalRuleToTaxRate::class, 'handleDeleted']);
    }

    /**
     * Register the service provider.
     */
    public function register(): void
    {
        $this->app->register(RouteServiceProvider::class);

        $this->registerNfseCancelDrivers();
    }

    /**
     * US-NFSE-CANCEL-001 — Registra Service Manager + drivers per-município.
     *
     * Pattern: tag drivers no container ('nfse.cancel.drivers') e injeta o
     * `iterable` resolvido em `NfseCancelService`. Drivers novos (GINFES, IPM,
     * Tiplan, nfse.gov.br/sefin) são adicionados em PRs separadas só
     * extendendo a lista de tags abaixo — sem mexer no service ou no Job.
     *
     * @see Modules\NfeBrasil\Services\NfseCancelService
     * @see Modules\NfeBrasil\Contracts\NfseCancelDriverInterface
     */
    private function registerNfseCancelDrivers(): void
    {
        // Drivers registrados como singletons + taggeados.
        $this->app->singleton(\Modules\NfeBrasil\Services\NfseDrivers\AbrasfV204CancelDriver::class);

        $this->app->tag([
            \Modules\NfeBrasil\Services\NfseDrivers\AbrasfV204CancelDriver::class,
            // TODO US-NFSE-CANCEL-003+: GinfesV1CancelDriver, IpmCancelDriver,
            // TiplanCancelDriver, NfseGovBrCancelDriver — quando integração real.
        ], 'nfse.cancel.drivers');

        // Service Manager recebe drivers taggeados como iterable.
        $this->app->singleton(
            \Modules\NfeBrasil\Services\NfseCancelService::class,
            function ($app) {
                return new \Modules\NfeBrasil\Services\NfseCancelService(
                    $app->tagged('nfse.cancel.drivers')
                );
            }
        );
    }

    /**
     * Register commands in the format of Command::class
     */
    protected function registerCommands(): void
    {
        $this->commands([
            \Modules\NfeBrasil\Console\Commands\MigrateCertFromBusiness::class,
            \Modules\NfeBrasil\Console\Commands\PuxarDfesRecebidosCommand::class,
        ]);
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
