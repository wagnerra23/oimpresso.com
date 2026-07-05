<?php

namespace Modules\Jana\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;
use Modules\Jana\Services\Mcp\IndexarMemoryGitParaDb;

/**
 * MEM-MCP-1.a (ADR 0053) — Sincroniza memory/ git → mcp_memory_documents.
 *
 * Modos:
 *   - manual: dev roda à mão depois de commit
 *   - webhook: chamado por endpoint POST /api/mcp/sync-memory (GitHub)
 *   - cron: scheduler 5min como fallback se webhook falhar
 *
 * Uso:
 *   php artisan mcp:sync-memory                    # manual padrão
 *   php artisan mcp:sync-memory --reason=cron      # registra origem no history
 *   php artisan mcp:sync-memory --user=1           # quem disparou
 *   php artisan mcp:sync-memory --base=/path/repo  # override do path
 *   php artisan mcp:sync-memory --only=briefing    # sync PARCIAL por type
 *
 * Sync robusto (handoff 2026-07-05 — deadlock + OOM no sync completo):
 *   - Lock atômico `mcp:sync-memory` (Cache::lock) impede webhook + cron
 *     concorrentes — a causa nº 1 dos deadlocks MySQL no Hostinger.
 *   - `--only=<type>` roda subconjunto barato (ex: os 73 BRIEFINGs) sem
 *     varrer os 1500 docs. Sync parcial NÃO roda a fase de soft-delete.
 */
class McpSyncMemoryCommand extends Command
{
    /** TTL do lock em segundos — sync completo leva minutos, não horas. */
    protected const LOCK_TTL = 900;

    protected $signature = 'mcp:sync-memory
                            {--reason=manual   : Origem da sincronização (manual|webhook|cron|fallback)}
                            {--user=           : ID do user que disparou (opcional)}
                            {--business=1      : business_id dono destes documentos (default: 1 = oimpresso dev)}
                            {--base=           : Override do path base do repo (default: base_path())}
                            {--only=           : Sync parcial: só docs deste type (briefing|adr|spec|session|...)}';

    protected $description = 'Sincroniza memory/ do filesystem com mcp_memory_documents (ADR 0053)';

    public function handle(): int
    {
        $base       = (string) ($this->option('base') ?? base_path());
        $reason     = (string) $this->option('reason');
        $userId     = $this->option('user')     ? (int) $this->option('user')     : null;
        $businessId = $this->option('business') ? (int) $this->option('business') : 1;
        $onlyType   = $this->option('only') ? (string) $this->option('only') : null;

        // Lock anti-concorrência: webhook GitHub + cron 5min disparando juntos
        // era a receita do deadlock (UPSERTs simultâneos na mesma tabela).
        // get() não-bloqueante: quem chegar segundo pula o run — o próximo
        // cron (5min) reconcilia, então pular é seguro e não perde dado.
        $lock = Cache::lock('mcp:sync-memory', self::LOCK_TTL);
        if (! $lock->get()) {
            $this->warn('Sync já em andamento (lock mcp:sync-memory ativo) — pulando este run.');
            return self::SUCCESS;
        }

        try {
            $this->info("Sincronizando memory/ → mcp_memory_documents");
            $this->line("  base       : $base");
            $this->line("  reason     : $reason");
            $this->line("  business_id: $businessId");
            if ($userId) {
                $this->line("  user: $userId");
            }
            if ($onlyType) {
                $this->line("  only (parcial, sem soft-delete): $onlyType");
            }

            $service = new IndexarMemoryGitParaDb($base, $reason, $userId, $businessId, $onlyType);

            try {
                $stats = $service->run();
            } catch (\Throwable $e) {
                $this->error('Sync falhou: ' . $e->getMessage());
                return self::FAILURE;
            }

            $this->info(sprintf(
                "Concluído: %d indexados (%d novos, %d atualizados), %d removidos, %d redactions PII",
                $stats['indexados'],
                $stats['novos'],
                $stats['atualizados'],
                $stats['removidos'],
                $stats['redactions'],
            ));

            return self::SUCCESS;
        } finally {
            $lock->release();
        }
    }
}
