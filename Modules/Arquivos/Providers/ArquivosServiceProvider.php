<?php

namespace Modules\Arquivos\Providers;

use Illuminate\Support\ServiceProvider;
use Modules\Arquivos\Services\ArquivosService;
use Modules\Arquivos\Services\Curador\CuradorEngine;
use Modules\Arquivos\Services\VaultEncryptionService;

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

        if ($this->app->runningInConsole()) {
            $this->commands([
                \Modules\Arquivos\Console\Commands\RecalcularMetadataCommand::class,
                \Modules\Arquivos\Console\Commands\DedupeStatsCommand::class,
                \Modules\Arquivos\Console\Commands\ReencryptVaultCommand::class,
                \Modules\Arquivos\Console\Commands\AuditLogCommand::class,
                \Modules\Arquivos\Console\Commands\RetentionCleanupCommand::class,
                \Modules\Arquivos\Console\Commands\HealthCheckCommand::class,
                \Modules\Arquivos\Console\Commands\ExportZipCommand::class,
            ]);
        }
    }

    public function register(): void
    {
        $this->app->singleton(CuradorEngine::class);
        $this->app->singleton(VaultEncryptionService::class);
        $this->app->singleton(ArquivosService::class);
    }
}
