<?php

namespace Modules\ConsultaOs\Providers;

use Illuminate\Support\ServiceProvider;
use Modules\ConsultaOs\Console\Commands\ConsultaOsHealthCommand;
use Modules\ConsultaOs\Contracts\ConsultaOsRepositoryInterface;
use Modules\ConsultaOs\Repositories\MockConsultaOsRepository;

class ConsultaOsServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        $this->registerConfig();
        $this->registerCommands();
    }

    /**
     * Wave 23 F6 — registra command CLI `consultaos:health` no kernel.
     */
    protected function registerCommands(): void
    {
        if ($this->app->runningInConsole()) {
            $this->commands([
                ConsultaOsHealthCommand::class,
            ]);
        }
    }

    public function register(): void
    {
        $this->app->register(RouteServiceProvider::class);

        // Wave 18 D4 — Repository bind. Mock-only ate US-CONSULTA-001 entregar
        // RepairConsultaOsRepository com query real em transactions (multi-tenant
        // via protocolo + rate limit IP). Troca = 1 linha aqui.
        $this->app->bind(
            ConsultaOsRepositoryInterface::class,
            MockConsultaOsRepository::class,
        );
    }

    protected function registerConfig(): void
    {
        $this->publishes([
            __DIR__ . '/../Config/config.php' => config_path('consultaos.php'),
        ], 'config');

        $this->mergeConfigFrom(__DIR__ . '/../Config/config.php', 'consultaos');
    }
}
