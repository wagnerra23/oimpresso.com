<?php

namespace Modules\Arquivos\Providers;

use Illuminate\Support\ServiceProvider;
use Modules\Arquivos\Services\ArquivosService;
use Modules\Arquivos\Services\Curador\CuradorEngine;

/**
 * ServiceProvider do módulo Arquivos (DMS backbone).
 *
 * Sprint 1 — ADR 0123 (Modules/Arquivos backbone).
 *
 * Boot:
 * - carrega rotas web (3 rotas Install + futuro /admin/arquivos)
 * - carrega migrations (arquivos, arquivos_audit_log, arquivos_dedupe)
 *
 * Register:
 * - bind ArquivosService como singleton
 * - bind CuradorEngine como singleton (port das regras JS de scripts/curador/lib/rules.mjs)
 *
 * @see memory/decisions/0123-modules-arquivos-backbone.md
 */
class ArquivosServiceProvider extends ServiceProvider
{
    /**
     * @var bool
     */
    protected $defer = false;

    public function boot(): void
    {
        $this->loadRoutesFrom(__DIR__ . '/../Routes/web.php');
        $this->loadMigrationsFrom(__DIR__ . '/../Database/Migrations');
    }

    public function register(): void
    {
        $this->app->singleton(CuradorEngine::class);
        $this->app->singleton(ArquivosService::class);
    }
}
