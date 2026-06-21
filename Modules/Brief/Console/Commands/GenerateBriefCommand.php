<?php

namespace Modules\Brief\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Modules\Brief\Services\BriefGeneratorService;
use Modules\Brief\Services\BriefValidator;
use Modules\Brief\Services\LeaseBriefSectionService;
use Modules\Governance\Services\PlanHealthBriefLineService;
use Modules\Governance\Services\SddBriefLineService;
use Throwable;

/**
 * Comando que gera o Daily Brief.
 *
 * Roda 6x/dia via cron (ver routes/console.php / Console/Kernel.php):
 *   0 7,11,14,17,20,23 * * * America/Sao_Paulo
 *
 * Pipeline (ADR 0091):
 *  1. CALL refresh_brief_inputs_cache()
 *  2. Lê cache + chama Brain B (sonnet-4-6)
 *  3. Valida output (7 headers, ≤3500 tokens, sem PII)
 *  4. Grava em mcp_briefs (valid=1) ou registra erro (valid=0)
 *  5. Invalida cache 'brief.current'
 *
 * --dry-run: imprime o brief no console e NÃO grava no banco.
 */
final class GenerateBriefCommand extends Command
{
    protected $signature = 'brief:generate {--dry-run : Imprime brief sem gravar no banco}';

    protected $description = 'Gera o Daily Brief (camada L7) via Brain B e grava em mcp_briefs';

    public function handle(BriefGeneratorService $svc): int
    {
        $this->info('Brief: refresh cache + chamando Brain B...');

        try {
            $content = $svc->generateNow();
        } catch (Throwable $e) {
            $this->error("Falha ao gerar brief: {$e->getMessage()}");
            $this->alertOps("Brief gerador falhou: {$e->getMessage()}");

            return self::FAILURE;
        }

        // GT-G8 (ADR 0275) — linha SDD determinística (pós-LLM) na seção FLAGS:
        // só aparece quando a composta mudou vs último snapshot da
        // mcp_sdd_scorecard_history OU há alerta (armada regrediu/fonte
        // vermelha). inject() é best-effort — brief nunca falha por causa dela.
        $content = app(SddBriefLineService::class)->inject($content);

        // ADR 0294 Onda 1 — linha de SAÚDE DOS PLANOS (pós-LLM, determinística) na
        // seção FLAGS: shell-out de scripts/governance/plan-health.mjs --json e
        // injeta "Planos: N vivos · X órfãos · Y a revisar". Best-effort: `node`
        // ausente / índice não-deployado → brief intacto. Catraca-irmã do gate CI
        // plan-health-gate.yml (PLANS-INDEX §"Como manter vivo" item 2).
        $content = app(PlanHealthBriefLineService::class)->inject($content);

        // C2+C3 (SDD Leva 2, ADR 0278) — bloco de leases ATIVOS + nudge "claim
        // antes de pegar", injetado sob `## EM VOO AGORA`. Best-effort (pós-LLM):
        // sem leases / tabela ausente / qualquer erro → brief intacto. Roteia por
        // WorkLeaseService::activeLeases() (varre expirados antes de listar).
        $content = app(LeaseBriefSectionService::class)->inject($content);

        $aggregatedHash = hash('sha256', $content);

        $validator = new BriefValidator();
        $result = $validator->validate($content);

        if (! $result->isOk()) {
            $this->error("Brief inválido: {$result->reason}");

            DB::table('mcp_briefs')->insert([
                'content' => mb_substr($content, 0, 1_000_000),
                'token_count' => (int) ceil(mb_strlen($content) / 4),
                'source_hash' => $aggregatedHash,
                'cost_usd' => $svc->lastCallCost(),
                'valid' => 0,
                'error_msg' => $result->reason,
            ]);

            $this->alertOps("Brief inválido: {$result->reason}");

            return self::FAILURE;
        }

        if ($this->option('dry-run')) {
            $this->line('--- DRY RUN — brief NÃO gravado ---');
            $this->line($content);
            $this->info("OK: {$result->tokenCount} tokens · \${$svc->lastCallCost()}");

            return self::SUCCESS;
        }

        DB::table('mcp_briefs')->insert([
            'content' => $content,
            'token_count' => $result->tokenCount,
            'source_hash' => $aggregatedHash,
            'cost_usd' => $svc->lastCallCost(),
            'valid' => 1,
        ]);

        Cache::forget('brief.current');

        $this->info(
            "Brief gerado: {$result->tokenCount} tokens · \${$svc->lastCallCost()}"
        );

        return self::SUCCESS;
    }

    /**
     * Posta alerta no MCP inbox (channel ops). Best-effort:
     * se mcp_inbox não existir ainda, log local + segue.
     */
    private function alertOps(string $message): void
    {
        try {
            DB::table('mcp_inbox')->insert([
                'channel' => 'ops',
                'severity' => 'critical',
                'message' => '🚨 '.$message,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        } catch (Throwable $e) {
            \Log::error('[brief:generate] alertOps falhou: '.$e->getMessage(), [
                'original_message' => $message,
            ]);
        }
    }
}
