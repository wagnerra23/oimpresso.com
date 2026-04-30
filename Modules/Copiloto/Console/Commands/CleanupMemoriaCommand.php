<?php

namespace Modules\Copiloto\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * MEM-FASE8 — Esquecimento controlado de memórias.
 *
 * Três limpezas em uma passada:
 *
 *   1. BLOAT: fatos com hits_count=0 e criados há mais de 30 dias.
 *      Nunca foram úteis — ruído que prejudica recall (aumenta falsos positivos).
 *
 *   2. EXPIRADOS: fatos com valid_until preenchido há mais de 90 dias.
 *      Já foram supersedidos e não têm mais utilidade histórica no hot path.
 *      ADRs supersedidas continuam acessíveis em mcp_memory_documents;
 *      apenas o fato RAG ativo é removido.
 *
 *   3. ORFÃOS MCP: fatos seedados (seeded_from_mcp=true) cujo documento
 *      fonte foi soft-deleted em mcp_memory_documents.
 *
 * Tudo é soft-delete (deleted_at). Nada é destruído permanentemente.
 * LGPD: hard-delete manual via `copiloto:cleanup-memoria --hard --business=N`.
 *
 * Roda semanalmente via scheduler (todo domingo às 03h).
 *
 * Métricas: memory_bloat_ratio = deleted / total (ADR 0050).
 */
class CleanupMemoriaCommand extends Command
{
    protected $signature = 'copiloto:cleanup-memoria
                            {--business=    : Limitar a um business_id específico (default: todos)}
                            {--bloat-days=30  : Dias sem uso pra considerar bloat (default 30)}
                            {--expired-days=90 : Dias após valid_until pra remover expirado (default 90)}
                            {--hard         : Hard-delete (LGPD opt-out). Requer --business explícito.}
                            {--dry-run      : Mostra o que seria deletado sem agir}';

    protected $description = 'Esquecimento controlado: remove fatos bloat + expirados + órfãos (MEM-FASE8)';

    public function handle(): int
    {
        $businessId  = $this->option('business') ? (int) $this->option('business') : null;
        $bloatDays   = max(7, (int) $this->option('bloat-days'));
        $expiredDays = max(1, (int) $this->option('expired-days'));
        $hardDelete  = (bool) $this->option('hard');
        $dryRun      = (bool) $this->option('dry-run');

        if ($hardDelete && $businessId === null) {
            $this->error('--hard requer --business explícito por segurança LGPD.');
            return self::FAILURE;
        }

        $this->info('Cleanup de memória — ' . now()->toDateTimeString());
        if ($dryRun) $this->warn('  [DRY RUN]');
        if ($businessId) $this->line("  business_id : $businessId");
        $this->line("  bloat_days  : $bloatDays");
        $this->line("  expired_days: $expiredDays");

        $stats = ['bloat' => 0, 'expirados' => 0, 'orfaos' => 0];

        // ── 1. BLOAT — nunca usados, velhos ──────────────────────────────────
        $bloatQuery = DB::table('copiloto_memoria_facts')
            ->whereNull('deleted_at')
            ->where('hits_count', 0)
            ->where('core_memory', false)
            ->where('created_at', '<', now()->subDays($bloatDays));

        if ($businessId !== null) {
            $bloatQuery->where('business_id', $businessId);
        }

        $stats['bloat'] = $bloatQuery->count();
        $this->line("  bloat encontrados     : {$stats['bloat']}");

        if (! $dryRun && $stats['bloat'] > 0) {
            $hardDelete
                ? (clone $bloatQuery)->delete()
                : (clone $bloatQuery)->update(['deleted_at' => now()]);
        }

        // ── 2. EXPIRADOS — valid_until preenchido há muito tempo ─────────────
        $expiredQuery = DB::table('copiloto_memoria_facts')
            ->whereNull('deleted_at')
            ->whereNotNull('valid_until')
            ->where('valid_until', '<', now()->subDays($expiredDays));

        if ($businessId !== null) {
            $expiredQuery->where('business_id', $businessId);
        }

        $stats['expirados'] = $expiredQuery->count();
        $this->line("  expirados encontrados : {$stats['expirados']}");

        if (! $dryRun && $stats['expirados'] > 0) {
            $hardDelete
                ? (clone $expiredQuery)->delete()
                : (clone $expiredQuery)->update(['deleted_at' => now()]);
        }

        // ── 3. ÓRFÃOS — fatos seedados cujo doc MCP foi deletado ─────────────
        $orfaosQuery = DB::table('copiloto_memoria_facts as f')
            ->whereNull('f.deleted_at')
            ->whereRaw("JSON_EXTRACT(f.metadata, '$.seeded_from_mcp') = true")
            ->whereNotExists(function ($sub) {
                $sub->select(DB::raw(1))
                    ->from('mcp_memory_documents as d')
                    ->whereNull('d.deleted_at')
                    ->whereRaw("d.slug = JSON_UNQUOTE(JSON_EXTRACT(f.metadata, '$.source_slug'))");
            });

        if ($businessId !== null) {
            $orfaosQuery->where('f.business_id', $businessId);
        }

        $stats['orfaos'] = $orfaosQuery->count();
        $this->line("  órfãos MCP encontrados: {$stats['orfaos']}");

        if (! $dryRun && $stats['orfaos'] > 0) {
            // Órfãos sempre soft-delete (nunca hard — preserva histórico de ADR)
            DB::table('copiloto_memoria_facts as f')
                ->whereNull('f.deleted_at')
                ->whereRaw("JSON_EXTRACT(f.metadata, '$.seeded_from_mcp') = true")
                ->whereNotExists(function ($sub) {
                    $sub->select(DB::raw(1))
                        ->from('mcp_memory_documents as d')
                        ->whereNull('d.deleted_at')
                        ->whereRaw("d.slug = JSON_UNQUOTE(JSON_EXTRACT(f.metadata, '$.source_slug'))");
                })
                ->when($businessId !== null, fn ($q) => $q->where('f.business_id', $businessId))
                ->update(['f.deleted_at' => now()]);
        }

        // ── Relatório ─────────────────────────────────────────────────────────
        $total = $stats['bloat'] + $stats['expirados'] + $stats['orfaos'];

        $this->newLine();
        $this->info('Concluído:');
        $this->line("  bloat removidos   : {$stats['bloat']}");
        $this->line("  expirados removidos: {$stats['expirados']}");
        $this->line("  órfãos removidos  : {$stats['orfaos']}");
        $this->line("  TOTAL             : $total");

        if ($total > 0 && ! $dryRun) {
            // Calcula bloat_ratio para log de métrica (ADR 0050)
            $totalAtivos = DB::table('copiloto_memoria_facts')
                ->whereNull('deleted_at')
                ->when($businessId !== null, fn ($q) => $q->where('business_id', $businessId))
                ->count();

            $bloatRatio = $totalAtivos > 0
                ? round($total / ($total + $totalAtivos), 4)
                : 0;

            \Illuminate\Support\Facades\Log::channel('copiloto-ai')->info('CleanupMemoria concluído', [
                'business_id'   => $businessId,
                'bloat'         => $stats['bloat'],
                'expirados'     => $stats['expirados'],
                'orfaos'        => $stats['orfaos'],
                'total_removido'=> $total,
                'total_ativos'  => $totalAtivos,
                'bloat_ratio'   => $bloatRatio,
                'hard_delete'   => $hardDelete,
            ]);
        }

        return self::SUCCESS;
    }
}
