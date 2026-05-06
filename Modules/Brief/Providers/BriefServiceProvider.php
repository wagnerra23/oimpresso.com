<?php

namespace Modules\Brief\Providers;

use Illuminate\Support\ServiceProvider;
use Modules\Brief\Console\Commands\GenerateBriefCommand;

/**
 * ServiceProvider do módulo Brief.
 *
 * Sprint 1 — Daily Brief (camada L7 da Constituição V2). Ver ADR 0091.
 *
 * Boot mínimo: rotas API + comandos artisan. Não tem UI, não tem
 * permissões, não aparece no menu — é infraestrutura backend pura.
 */
class BriefServiceProvider extends ServiceProvider
{
    /**
     * @var bool
     */
    protected $defer = false;

    public function boot(): void
    {
        $this->loadRoutesFrom(__DIR__ . '/../Routes/api.php');

        if ($this->app->runningInConsole()) {
            $this->commands([
                GenerateBriefCommand::class,
            ]);
        }
    }

    public function register(): void
    {
        // Service container: nada especial — autoresolve dependências.
    }
}
