<?php

declare(strict_types=1);

namespace Modules\Jana\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Modules\Jana\Entities\Mcp\McpMemoryDocument;
use Modules\Jana\Services\Memoria\Contextual\ContextualizerService;
use Modules\Jana\Services\Memoria\Contextual\DocumentChunker;

/**
 * GAP D3 #1 — Backfill Contextual Retrieval Anthropic.
 *
 * Itera docs em `mcp_memory_documents` com `contextual_indexed=false` (default)
 * ou todos se `--force`. Gera contextual_context via ContextualizerService
 * (Haiku 4.5 com prompt caching) e persiste.
 *
 * Custo Operacional (Haiku 4.5, ADR 0053 §pricing snapshot):
 *   - 1500 docs × 8k tokens avg × $1.02/1M = ~$12 one-shot total
 *   - Steady state (re-sync ADRs modificadas): <$1/dia
 *
 * Multi-tenant Tier 0 ([ADR 0093](memory/decisions/0093-multi-tenant-isolation-tier-0.md)):
 *   mcp_memory_documents é repo-wide (ADR 0053 §Pilar 6). Backfill itera
 *   todos os business_id automaticamente — não precisa loop per-business.
 *
 * Uso:
 *   php artisan jana:contextualize-backfill                 # 100 docs default
 *   php artisan jana:contextualize-backfill --limit=500     # batch maior
 *   php artisan jana:contextualize-backfill --dry-run       # preview, não persiste
 *   php artisan jana:contextualize-backfill --force         # re-contextualiza tudo
 *   php artisan jana:contextualize-backfill --detail        # log detalhado por doc
 *
 * @see Modules/Jana/Services/Memoria/Contextual/ContextualizerService.php
 * @see memory/requisitos/Jana/CONTEXTUAL-RETRIEVAL-ANTHROPIC.md
 */
class ContextualizeBackfillCommand extends Command
{
    /**
     * NOTA: `--detail` (não `--verbose`) — Symfony reserva --verbose (-v/-vv/-vvv).
     * Lição catalogada handoff 2026-05-14 18:34 + .claude/rules/commands.md.
     */
    protected $signature = 'jana:contextualize-backfill
                            {--limit=100   : Quantos docs por execução}
                            {--dry-run     : Preview sem persistir (log estimativa custo)}
                            {--force       : Re-contextualiza docs já indexed=true}
                            {--detail      : Log detalhado por doc (auditoria)}';

    protected $description = 'Backfill Contextual Retrieval Anthropic em mcp_memory_documents (GAP D3 #1)';

    public function handle(
        ContextualizerService $contextualizer,
        DocumentChunker $chunker,
    ): int {
        $limit  = max(1, (int) $this->option('limit'));
        $dryRun = (bool) $this->option('dry-run');
        $force  = (bool) $this->option('force');
        $detail = (bool) $this->option('detail');

        if (! $contextualizer->isEnabled() && ! $dryRun && ! $contextualizer->isForceMock()) {
            $this->warn('Feature flag JANA_CONTEXTUAL_RETRIEVAL=false — habilite no .env primeiro.');
            $this->line('Ou use --dry-run pra estimar custo sem chamar API.');

            return self::FAILURE;
        }

        $query = McpMemoryDocument::query()
            ->whereNotNull('content_md');

        if (! $force) {
            $query->where(function ($q) {
                $q->where('contextual_indexed', false)
                  ->orWhereNull('contextual_indexed');
            });
        }

        $docs = $query->limit($limit)->get();

        if ($docs->isEmpty()) {
            $this->info('Nenhum doc pra contextualizar (use --force pra re-rodar).');

            return self::SUCCESS;
        }

        $this->info("Iniciando backfill — {$docs->count()} docs (limit={$limit}, dry-run=".
            ($dryRun ? 'YES' : 'NO').", force=".($force ? 'YES' : 'NO').')');

        $stats = [
            'processados'    => 0,
            'sucesso'        => 0,
            'falhas'         => 0,
            'pulados'        => 0,
            'usd_total'      => 0.0,
            'brl_total'      => 0.0,
            'duration_ms_total' => 0,
        ];

        $maxChars = (int) config('copiloto.contextual_retrieval.max_chunk_chars', 3200);
        $maxDocChars = (int) config('copiloto.contextual_retrieval.max_doc_chars', 200_000);

        foreach ($docs as $doc) {
            $stats['processados']++;
            $start = microtime(true);

            try {
                $body = (string) $doc->content_md;

                if (strlen($body) > $maxDocChars) {
                    $stats['pulados']++;
                    if ($detail) {
                        $this->line("  [PULADO] {$doc->slug} (".strlen($body)." chars > limite)");
                    }
                    continue;
                }

                $chunks = $chunker->chunk($body, $maxChars);
                if (empty($chunks)) {
                    $stats['pulados']++;
                    continue;
                }

                // Estimativa de custo (sempre calculada, mesmo dry-run).
                $docTokens = (int) round(strlen($body) / 4); // heurística 4 chars/token
                $custo = $contextualizer->estimarCusto($docTokens, count($chunks));
                $stats['usd_total'] += (float) $custo['usd'];
                $stats['brl_total'] += (float) $custo['brl'];

                if ($dryRun) {
                    if ($detail) {
                        $this->line(sprintf(
                            "  [DRY] %-50s chunks=%d  usd=%.4f  brl=%.4f",
                            mb_substr($doc->slug, 0, 50),
                            count($chunks),
                            $custo['usd'],
                            $custo['brl'],
                        ));
                    }
                    $stats['sucesso']++;
                    continue;
                }

                // Live call.
                $contextos = $contextualizer->contextualizeBatch($body, $chunks);

                $contextoFinal = collect($chunks)
                    ->map(fn ($chunk) => trim((string) ($contextos[sha1($chunk)] ?? '')))
                    ->filter(fn ($s) => $s !== '')
                    ->implode("\n");

                if ($contextoFinal === '') {
                    $stats['falhas']++;
                    Log::channel('copiloto-ai')->warning('ContextualizeBackfill: contexto vazio', [
                        'slug' => $doc->slug,
                    ]);
                    continue;
                }

                // Persiste sem disparar Scout (re-indexação Scout vem em outro passo).
                McpMemoryDocument::withoutSyncingToSearch(function () use ($doc, $contextoFinal) {
                    $doc->update([
                        'contextual_context' => $contextoFinal,
                        'contextual_indexed' => true,
                        'contextualized_at'  => now(),
                    ]);
                });

                $stats['sucesso']++;

                if ($detail) {
                    $this->line(sprintf(
                        "  [OK]  %-50s chunks=%d  ctx_chars=%d  brl=%.4f",
                        mb_substr($doc->slug, 0, 50),
                        count($chunks),
                        strlen($contextoFinal),
                        $custo['brl'],
                    ));
                }
            } catch (\Throwable $e) {
                $stats['falhas']++;
                Log::channel('copiloto-ai')->error('ContextualizeBackfill exception', [
                    'slug' => $doc->slug,
                    'erro' => $e->getMessage(),
                ]);
                if ($detail) {
                    $this->error("  [ERR] {$doc->slug}: ".$e->getMessage());
                }
            }

            $stats['duration_ms_total'] += (int) round((microtime(true) - $start) * 1000);
        }

        $this->newLine();
        $this->info('Concluído:');
        $this->line(sprintf(
            '  Processados : %d  (sucesso=%d, falhas=%d, pulados=%d)',
            $stats['processados'],
            $stats['sucesso'],
            $stats['falhas'],
            $stats['pulados'],
        ));
        $this->line(sprintf(
            '  Custo total : USD %.4f (BRL %.4f)  %s',
            $stats['usd_total'],
            $stats['brl_total'],
            $dryRun ? '[ESTIMATIVA dry-run]' : '[REAL gasto]',
        ));
        $this->line(sprintf(
            '  Tempo total : %.2fs',
            $stats['duration_ms_total'] / 1000,
        ));

        Log::channel('copiloto-ai')->info('jana:contextualize-backfill concluído', $stats);

        return $stats['falhas'] > 0 ? self::FAILURE : self::SUCCESS;
    }
}
