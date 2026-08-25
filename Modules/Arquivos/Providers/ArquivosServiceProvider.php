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
        // O modulo tinha Config/ desde a Sprint 1 e NUNCA registrava — medido em
        // 2026-08-24: zero `mergeConfigFrom` no provider. Tudo sobrevivia porque cada
        // leitura passa default inline (`config('arquivos.disk_vault', 'vault')`), entao
        // o config nulo era invisivel. A chave `retention_days_policy` — a unica sem
        // default inline — ficava inalcancavel, e e justamente ela que carrega a BASE
        // LEGAL por contexto. Todo modulo irmao (Auditoria, Cms, Compras...) ja fazia isto.
        //
        // Neutro em comportamento, medido antes de aplicar: nenhuma env ARQUIVOS_* existe
        // no repo (logo os valores == os defaults inline) e `arquivos:retention:cleanup`
        // nao tem schedule — nada apaga sozinho por causa deste registro.
        $this->mergeConfigFrom(__DIR__ . '/../Config/config.php', 'arquivos');

        $this->app->singleton(CuradorEngine::class);
        $this->app->singleton(VaultEncryptionService::class);
        $this->app->singleton(ArquivosService::class);
    }
}
