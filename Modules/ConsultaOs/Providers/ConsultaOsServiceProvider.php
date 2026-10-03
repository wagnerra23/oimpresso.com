<?php

namespace Modules\ConsultaOs\Providers;

use Illuminate\Support\ServiceProvider;
use Modules\ConsultaOs\Console\Commands\ConsultaOsHealthCommand;
use Modules\ConsultaOs\Contracts\ConsultaOsRepositoryInterface;
use Modules\ConsultaOs\Repositories\RepairConsultaOsRepository;

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

        // US-CONSULTA-001 (2026-10-02) — fonte real: folhas de OS do Modules/Repair.
        // O mock de 4 OS fixas (MockConsultaOsRepository) foi removido.
        $this->app->bind(
            ConsultaOsRepositoryInterface::class,
            RepairConsultaOsRepository::class,
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
