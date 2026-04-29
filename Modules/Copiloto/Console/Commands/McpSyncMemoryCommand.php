<?php

namespace Modules\Copiloto\Console\Commands;

use Illuminate\Console\Command;
use Modules\Copiloto\Services\Mcp\IndexarMemoryGitParaDb;

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
 */
class McpSyncMemoryCommand extends Command
{
    protected $signature = 'mcp:sync-memory
                            {--reason=manual : Origem da sincronização (manual|webhook|cron|fallback)}
                            {--user= : ID do user que disparou (opcional)}
                            {--base= : Override do path base do repo (default: base_path())}';

    protected $description = 'Sincroniza memory/ do filesystem com mcp_memory_documents (ADR 0053)';

    public function handle(): int
    {
        $base = (string) ($this->option('base') ?? base_path());
        $reason = (string) $this->option('reason');
        $userId = $this->option('user') ? (int) $this->option('user') : null;

        $this->info("Sincronizando memory/ → mcp_memory_documents");
        $this->line("  base: $base");
        $this->line("  reason: $reason");
        if ($userId) {
            $this->line("  user: $userId");
        }

        $service = new IndexarMemoryGitParaDb($base, $reason, $userId);

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
    }
}
