<?php

namespace Modules\Brief\Providers;

use Illuminate\Support\ServiceProvider;
use Modules\Brief\Console\Commands\GenerateBriefCommand;

/**
 * ServiceProvider do módulo Brief.
 *
 * Sprint 1 — Daily Brief (camada L7 da Constituição V2). Ver ADR 0091.
 *
 * Boot: rotas API (brief-fetch HTTP) + rotas web (3 rotas Install ADR 0024)
 * + comandos artisan. UI admin futura virá em US-COPI-090/091.
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
        $this->loadRoutesFrom(__DIR__ . '/../Routes/web.php');

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
