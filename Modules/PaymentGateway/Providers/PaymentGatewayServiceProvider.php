<?php

namespace Modules\PaymentGateway\Providers;

use Illuminate\Support\ServiceProvider;

class PaymentGatewayServiceProvider extends ServiceProvider
{
    protected string $moduleName = 'PaymentGateway';

    protected string $moduleNameLower = 'paymentgateway';

    /**
     * Boot the application events.
     */
    public function boot(): void
    {
        $this->registerCommands();
        $this->registerTranslations();
        $this->registerConfig();
        $this->loadMigrationsFrom(module_path($this->moduleName, 'Database/Migrations'));
    }

    /**
     * Comandos artisan do módulo.
     */
    protected function registerCommands(): void
    {
        if ($this->app->runningInConsole()) {
            $this->commands([
                \Modules\PaymentGateway\Console\Commands\MigrateCredentialsCommand::class,
                \Modules\PaymentGateway\Console\Commands\RegisterPermissionsCommand::class,
                \Modules\PaymentGateway\Console\Commands\EmitTrialExpiredCobrancasCommand::class,
            ]);
        }
    }

    /**
     * Register the service provider.
     */
    public function register(): void
    {
        $this->app->register(RouteServiceProvider::class);
        $this->registerContracts();
    }

    /**
     * Binding container Onda 4a — PaymentGatewayContract resolve PaymentGatewayService.
     *
     * Drivers (InterDriver/C6Driver/AsaasDriver/BcbPixDriver) NÃO são bindados
     * por chave aqui — PaymentGatewayService::driverFor() resolve via mapa interno.
     */
    protected function registerContracts(): void
    {
        $this->app->bind(
            \Modules\PaymentGateway\Contracts\PaymentGatewayContract::class,
            \Modules\PaymentGateway\Services\PaymentGatewayService::class,
        );
    }

    /**
     * Register config.
     */
    protected function registerConfig(): void
    {
        $this->publishes([
            module_path($this->moduleName, 'Config/config.php') => config_path($this->moduleNameLower.'.php'),
        ], 'config');

        $this->mergeConfigFrom(
            module_path($this->moduleName, 'Config/config.php'), $this->moduleNameLower
        );
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
     * Get the services provided by the provider.
     */
    public function provides(): array
    {
        return [];
    }
}
