<?php

declare(strict_types=1);

namespace Modules\Jana\Console\Commands;

use Illuminate\Console\Command;
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
 * PREVENTIVO (não é reclaim): roda a cada poucas horas e mantém a tabela pequena
 * DESDE O INÍCIO. TETO DURO por document_id: preserva só as --keep versões mais
 * novas, INDEPENDENTE da idade. O git é a fonte canônica de longo prazo
 * (ADR 0061) — history antigo no DB é descartável.
 *
 * REINCIDÊNCIA 2026-06-26 (motivo desta mudança): a versão anterior só deletava
 * o que reprovava em AMBAS as defesas (fora de --days=90d E além do top-N). Como
 * a maratona de governança 21-25/06 gerou dezenas de merges/dia em docs quentes
 * (handoffs, índices, scorecards), TODAS as ~374k versões nasceram dentro da
 * janela de 90d → a poda não deletava NADA → tabela reinflou pra 5,2 GB e a
 * Hostinger AUTO-REVOGOU INSERT/UPDATE do ERP (Larissa/biz=4 não salvava produto
 * nem venda). A janela temporal era o buraco; agora o teto é só por --keep.
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
                            {--keep=20 : Teto duro de versões a preservar por document_id (idade ignorada)}
                            {--days= : DEPRECADO (ignorado) — a janela temporal era o buraco do burst 2026-06-26}
                            {--chunk=2000 : Tamanho do lote de DELETE}
                            {--dry-run : Apenas conta o que seria podado, não deleta}';

    protected $description = 'Poda preventiva de mcp_memory_documents_history (teto duro de N versões por doc)';

    private const TABLE = 'mcp_memory_documents_history';

    public function handle(): int
    {
        if (! \Illuminate\Support\Facades\Schema::hasTable(self::TABLE)) {
            $this->warn('Tabela ' . self::TABLE . ' ausente — nada a podar (pré-migração/dev).');

            return self::SUCCESS;
        }

        $keep = max(1, (int) $this->option('keep'));
        $chunk = max(100, (int) $this->option('chunk'));
        $dryRun = (bool) $this->option('dry-run');

        $this->info('jana:memory-history-prune — ' . now()->toDateTimeString());
        $this->line("  teto duro (por doc): {$keep} versões mais novas — idade ignorada");
        if ($dryRun) {
            $this->warn('  [DRY-RUN] nada será deletado');
        }

        $totalBefore = (int) DB::table(self::TABLE)->count();

        // TETO DURO por document_id: candidato a DELETE = já saiu do top-{keep} do
        // seu doc (há >= keep versões mais novas), INDEPENDENTE da idade. A antiga
        // "defesa temporal" (--days) blindava todo recente e foi o buraco que
        // deixou o burst 21-25/06 reinflar pra 5,2 GB (ver docblock). Desempate por
        // id pra contagem determinística quando changed_at é igual.
        $idsQuery = DB::table(self::TABLE . ' as h')
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
            'matched' => $matched,
            'deleted' => $deleted,
            'total_before' => $totalBefore,
            'total_after' => $totalAfter,
        ]);

        $this->info(sprintf('Podadas: %d · antes: %d · depois: %d', $deleted, $totalBefore, $totalAfter));

        return self::SUCCESS;
    }
}
