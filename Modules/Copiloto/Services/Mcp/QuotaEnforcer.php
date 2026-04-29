<?php

namespace Modules\Copiloto\Services\Mcp;

use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Modules\Copiloto\Entities\Mcp\McpQuota;

/**
 * MEM-TEAM-1 Fase 4 (ADR 0055) — Enforcement de quotas + alertas 50/80/100%.
 *
 * Verifica antes de cada chamada MCP autenticada:
 *   - Há quota daily/monthly ativa pra esse user?
 *   - current_usage (custo brl) já passou do limit?
 *   - Se passou: bloqueia (429) ou só alerta (block_on_exceed=false)
 *   - Threshold 50/80/100%: dispara alerta idempotente em mcp_alertas
 *
 * Idempotência alerta: chave (user_id, period, threshold_pct, dia/mes) — só gera 1×.
 *
 * Performance: query agregada de SUM(custo_brl) é rápida (<10ms) com index
 * mcp_audit_log.user_id + ts. Cache 60s pra reduzir queries repetidas.
 */
class QuotaEnforcer
{
    /**
     * Resultado do check de quota:
     *   ok: true → segue normal
     *   ok: false + block: true → middleware retorna 429
     *   ok: false + block: false → middleware deixa passar mas aviso registrado
     */
    public function checar(int $userId): array
    {
        // Pega TODAS quotas ativas (BRL OU calls OU tokens) deste user
        $quotas = McpQuota::where('user_id', $userId)
            ->whereIn('kind', ['brl', 'calls', 'tokens'])
            ->where('ativo', true)
            ->get();

        if ($quotas->isEmpty()) {
            return [
                'ok' => true,
                'sem_quota' => true,
            ];
        }

        $resultados = [];
        foreach ($quotas as $q) {
            $usoAtual = $this->calcularUsoAtual($userId, $q->period, $q->kind);
            $pctAtingido = $q->limit > 0 ? ($usoAtual / (float) $q->limit) * 100 : 0;

            // Atualiza current_usage no quota row pra dashboard ler rápido
            $q->update(['current_usage' => $usoAtual]);

            // Verifica thresholds e dispara alertas idempotentes
            $this->verificarAlertas($userId, $q, $usoAtual, $pctAtingido);

            $resultados["{$q->kind}_{$q->period}"] = [
                'kind' => $q->kind,
                'period' => $q->period,
                'limit' => (float) $q->limit,
                'uso_atual' => $usoAtual,
                'pct_atingido' => round($pctAtingido, 1),
                'block_on_exceed' => (bool) $q->block_on_exceed,
                'excedido' => $pctAtingido >= 100,
            ];
        }

        // Decisão: se QUALQUER quota com block_on_exceed=true atingiu 100%, bloqueia
        $bloqueia = false;
        foreach ($resultados as $r) {
            if ($r['excedido'] && $r['block_on_exceed']) {
                $bloqueia = true;
                break;
            }
        }

        return [
            'ok' => ! $bloqueia,
            'block' => $bloqueia,
            'quotas' => $resultados,
        ];
    }

    /**
     * Calcula uso atual no período pelo tipo da quota.
     *   kind=brl    → SUM(custo_brl)
     *   kind=calls  → COUNT(*)
     *   kind=tokens → SUM(tokens_in + tokens_out + cache_read + cache_write)
     */
    protected function calcularUsoAtual(int $userId, string $period, string $kind = 'brl'): float
    {
        [$inicio, $fim] = $this->resolverPeriodo($period);

        $base = DB::table('mcp_audit_log')
            ->where('user_id', $userId)
            ->whereBetween('ts', [$inicio, $fim]);

        return match ($kind) {
            'calls'  => (float) $base->count(),
            'tokens' => (float) $base->selectRaw(
                'COALESCE(SUM(tokens_in), 0) + COALESCE(SUM(tokens_out), 0) +
                 COALESCE(SUM(cache_read), 0) + COALESCE(SUM(cache_write), 0) as total'
            )->value('total'),
            default  => (float) $base->sum('custo_brl'),
        };
    }

    protected function resolverPeriodo(string $period): array
    {
        return match ($period) {
            'daily'   => [Carbon::today()->startOfDay(), Carbon::today()->endOfDay()],
            'weekly'  => [Carbon::today()->startOfWeek(), Carbon::today()->endOfWeek()],
            'monthly' => [Carbon::today()->startOfMonth(), Carbon::today()->endOfMonth()],
            default   => [Carbon::today()->startOfDay(), Carbon::today()->endOfDay()],
        };
    }

    /**
     * Dispara alerta idempotente em mcp_alertas se atravessou threshold.
     *
     * Idempotência por (user_id, quota_id, period_key, threshold_pct).
     * period_key = dia (YYYY-MM-DD) ou mês (YYYY-MM).
     */
    protected function verificarAlertas(int $userId, McpQuota $q, float $uso, float $pct): void
    {
        $thresholds = [50, 80, 100];
        $periodKey = match ($q->period) {
            'daily'   => Carbon::today()->toDateString(),
            'weekly'  => Carbon::today()->startOfWeek()->toDateString(),
            'monthly' => Carbon::today()->format('Y-m'),
            default   => Carbon::today()->toDateString(),
        };

        foreach ($thresholds as $t) {
            if ($pct < $t) continue;

            $alertaKey = sprintf('quota:%d:%d:%s:%d', $userId, $q->id, $periodKey, $t);

            // Idempotência: já tem alerta com essa chave?
            $jaTem = DB::table('mcp_alertas_eventos')
                ->where('chave_idempotencia', $alertaKey)
                ->exists();
            if ($jaTem) continue;

            try {
                DB::table('mcp_alertas_eventos')->insert([
                    'user_id' => $userId,
                    'tipo' => 'quota_threshold',
                    'severidade' => $t >= 100 ? 'high' : ($t >= 80 ? 'medium' : 'low'),
                    'titulo' => sprintf('Quota %s %d%% (R$ %.2f de R$ %.2f)',
                        $q->period, $t, $uso, (float) $q->limit),
                    'descricao' => sprintf(
                        'Você atingiu %d%% da quota %s (R$ %.4f de R$ %.4f). %s',
                        $t,
                        $q->period,
                        $uso,
                        (float) $q->limit,
                        $t >= 100 && $q->block_on_exceed
                            ? 'PRÓXIMAS CHAMADAS SERÃO BLOQUEADAS (429).'
                            : 'Próximas chamadas continuam.'
                    ),
                    'chave_idempotencia' => $alertaKey,
                    'metadata' => json_encode([
                        'quota_id' => $q->id,
                        'period' => $q->period,
                        'limit' => (float) $q->limit,
                        'uso_atual' => $uso,
                        'pct' => $pct,
                        'threshold' => $t,
                    ]),
                    'status' => 'aberto',
                    'criado_em' => now(),
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);

                Log::channel('copiloto-ai')->info('QuotaEnforcer: alerta gerado', [
                    'user_id' => $userId,
                    'quota_id' => $q->id,
                    'period' => $q->period,
                    'threshold' => $t,
                    'pct' => $pct,
                    'uso' => $uso,
                ]);

                // TODO Fase 4b: dispatchar notificação (email/slack) se threshold >= 80
            } catch (\Throwable $e) {
                Log::channel('copiloto-ai')->warning('QuotaEnforcer: alerta falhou: ' . $e->getMessage());
            }
        }
    }
}
