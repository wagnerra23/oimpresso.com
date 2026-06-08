<?php

namespace Modules\ADS\Services;

use App\Util\OtelHelper;
use Illuminate\Support\Facades\DB;

/**
 * ARQ-0005 — Confiança por (domínio × tipo de evento).
 *
 * confiança = média_ponderada(outcomes) × similaridade_contexto × fator_estabilidade
 *
 * Peso especial: modificação humana vale 3× mais que fail comum.
 *
 * Observabilidade: `recordOutcome()` envolto em OTel span (D9.a — ADR 0155).
 * Multi-tenant Tier 0: `OtelHelper::spanBiz` auto-resolve `business_id` da sessão.
 */
class ConfidenceEngine
{
    private const OUTCOME_WEIGHTS = [
        'success'          =>  1.0,
        'fail'             => -1.5,
        'wagner_modified'  => -0.5,  // penalidade base; multiplicada por diff abaixo
        'wagner_rejected'  => -2.0,
        'cancelled'        =>  0.0,
        'expired'          =>  0.0,
    ];

    /** Peso extra quando diff_size_pct > 30 (modificação substancial). ARQ-0005. */
    private const HUMAN_MODIFY_WEIGHT = 3.0;

    private const INITIAL_SCORE = 0.50;

    /** Janela de outcomes considerados para cálculo. */
    private const WINDOW_SIZE = 20;

    public function getScore(string $domain, string $eventType): float
    {
        $row = DB::table('mcp_confidence_scores')
            ->where('domain', $domain)
            ->where('event_type', $eventType)
            ->first();

        return $row ? (float) $row->score : self::INITIAL_SCORE;
    }

    public function getHitlLevel(string $domain, string $eventType): int
    {
        $row = DB::table('mcp_confidence_scores')
            ->where('domain', $domain)
            ->where('event_type', $eventType)
            ->first();

        return $row ? (int) $row->hitl_level : 2;
    }

    /**
     * Registra o outcome de uma execução e recalcula o score.
     * Chamado pelo Learning Loop L1 após cada tarefa concluída.
     */
    public function recordOutcome(
        string $domain,
        string $eventType,
        string $outcome,
        int    $diffSizePct = 0,
    ): float {
        return OtelHelper::spanBiz('ads.confidence_engine.record_outcome', function () use ($domain, $eventType, $outcome, $diffSizePct): float {
            $delta = $this->computeDelta($outcome, $diffSizePct);

            $row = DB::table('mcp_confidence_scores')
                ->where('domain', $domain)
                ->where('event_type', $eventType)
                ->first();

            if (! $row) {
                $newScore = max(0.0, min(1.0, self::INITIAL_SCORE + ($delta * 0.05)));
                DB::table('mcp_confidence_scores')->insert([
                    'domain'                => $domain,
                    'event_type'            => $eventType,
                    'score'                 => $newScore,
                    'sample_size'           => 1,
                    'hitl_level'            => 2,
                    'last_outcome'          => $outcome,
                    'consecutive_approvals' => $outcome === 'success' ? 1 : 0,
                    'consecutive_failures'  => in_array($outcome, ['fail', 'wagner_rejected'], true) ? 1 : 0,
                ]);
                return $newScore;
            }

            $newScore = max(0.0, min(1.0, (float) $row->score + ($delta * 0.05)));
            $consecutiveApprovals = $outcome === 'success' ? (int) $row->consecutive_approvals + 1 : 0;
            $consecutiveFailures  = in_array($outcome, ['fail', 'wagner_rejected'], true)
                ? (int) $row->consecutive_failures + 1
                : 0;

            $hitlLevel = $this->computeHitlLevel(
                (int) $row->hitl_level,
                $consecutiveApprovals,
                $consecutiveFailures,
                $newScore,
            );

            DB::table('mcp_confidence_scores')
                ->where('domain', $domain)
                ->where('event_type', $eventType)
                ->update([
                    'score'                 => round($newScore, 3),
                    'sample_size'           => min((int) $row->sample_size + 1, self::WINDOW_SIZE),
                    'hitl_level'            => $hitlLevel,
                    'last_outcome'          => $outcome,
                    'consecutive_approvals' => $consecutiveApprovals,
                    'consecutive_failures'  => $consecutiveFailures,
                ]);

            return $newScore;
        }, [
            'module' => 'ADS',
            'domain' => $domain,
            'event_type' => $eventType,
            'outcome' => $outcome,
        ]);
    }

    /**
     * Calcula a variação de score baseada no outcome.
     */
    public function computeDelta(string $outcome, int $diffSizePct = 0): float
    {
        $base = self::OUTCOME_WEIGHTS[$outcome] ?? 0.0;

        // Modificação humana substancial (>30%) multiplica pelo peso especial
        if ($outcome === 'wagner_modified' && $diffSizePct > 30) {
            $base *= self::HUMAN_MODIFY_WEIGHT;
        }

        return $base;
    }

    /**
     * Progressão de HiTL: 2 → 1 (≥5 aprovações consecutivas), 1 → 0 (≥10 + score > 0.85).
     * Regressão imediata para HiTL-2 em qualquer rejeição ou modificação substancial.
     */
    private function computeHitlLevel(
        int   $currentLevel,
        int   $consecutiveApprovals,
        int   $consecutiveFailures,
        float $score,
    ): int {
        // Regressão imediata
        if ($consecutiveFailures > 0) {
            return 2;
        }

        // Progressão
        if ($currentLevel === 2 && $consecutiveApprovals >= 5) {
            return 1;
        }
        if ($currentLevel === 1 && $consecutiveApprovals >= 10 && $score > 0.85) {
            return 0;
        }

        return $currentLevel;
    }
}
