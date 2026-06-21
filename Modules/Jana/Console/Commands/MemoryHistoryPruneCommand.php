<?php

declare(strict_types=1);

namespace Modules\Jana\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * jana:memory-history-prune — poda preventiva de mcp_memory_documents_history.
 *
 * CONTEXTO (incidente 2026-06-21): a tabela de histórico bitemporal das memórias
 * é append-only — cada mudança real de um doc grava um snapshot do content_md
 * inteiro (mediumtext). Docs "quentes" (CURRENT/TASKS/handoffs/CLAUDE) mudam a
 * cada push e acumulam centenas de versões. SEM TETO, inflou pra 4.97 GB / 296k
 * linhas, estourou a cota de disco do Hostinger e o provedor AUTO-REVOGOU a
 * escrita do ERP. Foi dropada+recriada vazia; este comando impede a reincidência.
 *
 * PREVENTIVO (não é reclaim): roda diário e mantém a tabela pequena DESDE O
 * INÍCIO. Por document_id preserva as últimas --keep versões E tudo dentro de
 * --days; deleta só o que reprova AMBAS as defesas. O git é a fonte canônica de
 * longo prazo (ADR 0061) — history antigo no DB é descartável.
 *
 * Tabela de SISTEMA (sem business_id) — cross-tenant intencional, igual
 * mcp_audit_log / mcp_module_grades_history (ADR 0093 escopo plataforma).
 *
 * Driver-agnóstico (MySQL/MariaDB prod + SQLite CI): usa subquery correlacionada,
 * sem window function. Skip gracioso se a tabela não existir.
 *
 * @see Modules\Jana\Entities\Mcp\McpMemoryDocumentHistory
 * @see Modules\Jana\Console\Commands\RetentionPurgeCommand (molde)
 */
class MemoryHistoryPruneCommand extends Command
{
    protected $signature = 'jana:memory-history-prune
                            {--keep=20 : Versões mais recentes a preservar por document_id}
                            {--days=90 : Preservar tudo mais novo que N dias (defesa temporal)}
                            {--chunk=2000 : Tamanho do lote de DELETE}
                            {--dry-run : Apenas conta o que seria podado, não deleta}';

    protected $description = 'Poda preventiva de mcp_memory_documents_history (mantém últimas N por doc + janela de X dias)';

    private const TABLE = 'mcp_memory_documents_history';

    public function handle(): int
    {
        if (! \Illuminate\Support\Facades\Schema::hasTable(self::TABLE)) {
            $this->warn('Tabela ' . self::TABLE . ' ausente — nada a podar (pré-migração/dev).');

            return self::SUCCESS;
        }

        $keep = max(1, (int) $this->option('keep'));
        $days = max(1, (int) $this->option('days'));
        $chunk = max(100, (int) $this->option('chunk'));
        $dryRun = (bool) $this->option('dry-run');
        $cutoff = Carbon::now()->subDays($days);

        $this->info('jana:memory-history-prune — ' . now()->toDateTimeString());
        $this->line("  keep (por doc): {$keep} · days (janela): {$days} (cutoff {$cutoff->toDateTimeString()})");
        if ($dryRun) {
            $this->warn('  [DRY-RUN] nada será deletado');
        }

        $totalBefore = (int) DB::table(self::TABLE)->count();

        // Candidatos a DELETE: reprovam em AMBAS as defesas — estão FORA da janela
        // de dias E já saíram do top-N do seu document_id (há >= keep versões mais
        // novas). Desempate por id pra contagem determinística em changed_at igual.
        $idsQuery = DB::table(self::TABLE . ' as h')
            ->where('h.changed_at', '<', $cutoff)
            ->whereRaw(
                '(SELECT COUNT(*) FROM ' . self::TABLE . ' AS n
                    WHERE n.document_id = h.document_id
                      AND (n.changed_at > h.changed_at
                           OR (n.changed_at = h.changed_at AND n.id > h.id))) >= ?',
                [$keep],
            )
            ->select('h.id');

        $matched = (clone $idsQuery)->count();

        if ($matched === 0 || $dryRun) {
            $this->info("Matched: {$matched} · total atual: {$totalBefore}" . ($dryRun ? ' (dry-run — nada deletado)' : ''));

            return self::SUCCESS;
        }

        $ids = $idsQuery->pluck('id')->all();
        $deleted = 0;
        foreach (array_chunk($ids, $chunk) as $batch) {
            $deleted += DB::table(self::TABLE)->whereIn('id', $batch)->delete();
        }

        $totalAfter = (int) DB::table(self::TABLE)->count();

        Log::channel('copiloto-ai')->info('MemoryHistoryPrune concluído', [
            'keep' => $keep,
            'days' => $days,
            'cutoff' => $cutoff->toIso8601String(),
            'matched' => $matched,
            'deleted' => $deleted,
            'total_before' => $totalBefore,
            'total_after' => $totalAfter,
        ]);

        $this->info(sprintf('Podadas: %d · antes: %d · depois: %d', $deleted, $totalBefore, $totalAfter));

        return self::SUCCESS;
    }
}
