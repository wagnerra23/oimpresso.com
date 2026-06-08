<?php

namespace Modules\ADS\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Observabilidade D9.a (ADR 0155): aprendizado batch envolto em
 * `OtelHelper::span(` (Tracer ads.pattern.learn) — mede Wilson computation.
 *
 * T15 — Pattern Learning estado-da-arte (ARQ-0007).
 *
 * Usa Wilson Score Interval (não taxa de sucesso simples) para evitar promoção
 * por ruído de poucas amostras. Padrão clássico Reddit/Stack Overflow ranking.
 *
 *   ŝ = (p̂ + z²/2n) / (1 + z²/n) − z·√(p̂(1−p̂)/n + z²/4n²) / (1 + z²/n)
 *
 * Onde p̂ = success/total, n = total, z = 1.96 (95% conf), ŝ = Wilson lower bound.
 *
 * Promoção pra ALLOW_BRAIN_A só se ŝ ≥ 0.80 AND total ≥ 10.
 * Isso impede que 3/3 sucessos (taxa naïve = 100%) promova prematuramente
 * — o Wilson lower bound é só 0.439 com n=3, abaixo do gate.
 */
class PatternLearningService
{
    private const WILSON_Z = 1.96;     // 95% confidence
    private const PROMOTE_LOWER_BOUND = 0.80;
    private const PROMOTE_MIN_SAMPLES = 10;

    /**
     * Registra outcome de uma decision em mcp_decision_patterns.
     * Hash do padrão = SHA256(domain + event_type) — pares iguais agregam.
     *
     * Chamado pelo Learning Loop L1 após cada outcome ser definido.
     */
    public function recordOutcome(object $decision): void
    {
        if (! in_array($decision->outcome, ['success', 'fail', 'wagner_modified', 'wagner_rejected'], true)) {
            return; // cancelled/expired/dismissed não contam
        }

        $hash = $this->patternHash($decision->domain, $decision->event_type);
        $isSuccess = in_array($decision->outcome, ['success', 'wagner_modified'], true);
        $isStrongFail = $decision->outcome === 'wagner_rejected';

        $existing = DB::table('mcp_decision_patterns')
            ->where('pattern_hash', $hash)
            ->first();

        if (! $existing) {
            DB::table('mcp_decision_patterns')->insert([
                'business_id'          => $decision->business_id,
                'domain'               => $decision->domain,
                'event_type'           => $decision->event_type,
                'pattern_hash'         => $hash,
                'description'          => "Padrão automático: {$decision->event_type} em {$decision->domain}",
                'example_decision_ids' => json_encode([$decision->id]),
                'success_count'        => $isSuccess ? 1 : 0,
                'total_count'          => 1,
                'success_rate'         => $isSuccess ? 1.000 : 0.000,
                'is_hardcoded'         => false,
                'approved_by_wagner'   => false,
                'created_at'           => now(),
                'updated_at'           => now(),
            ]);
            return;
        }

        // Penaliza wagner_rejected com peso maior (3× ARQ-0005)
        $newSuccess = $existing->success_count + ($isSuccess ? 1 : 0);
        $newTotal   = $existing->total_count + 1 + ($isStrongFail ? 2 : 0); // peso 3 só no rejected
        $newRate    = $newTotal > 0 ? round($newSuccess / $newTotal, 3) : 0;

        $examples = json_decode($existing->example_decision_ids ?? '[]', true) ?: [];
        $examples[] = $decision->id;
        $examples = array_slice(array_unique($examples), -10); // últimos 10

        DB::table('mcp_decision_patterns')
            ->where('pattern_hash', $hash)
            ->update([
                'success_count'        => $newSuccess,
                'total_count'          => $newTotal,
                'success_rate'         => $newRate,
                'example_decision_ids' => json_encode($examples),
                'updated_at'           => now(),
            ]);
    }

    /**
     * Wilson Score Interval — lower bound a 95% de confiança.
     * Retorna 0.0 se n=0.
     *
     * Por que isso > taxa de sucesso simples:
     *   - taxa(3/3) = 1.000 → enganoso, n pequeno
     *   - wilson(3/3) = 0.439 → reflete incerteza estatística
     *   - wilson(80/100) = 0.722 → confiável de verdade
     */
    public function wilsonLowerBound(int $success, int $total): float
    {
        if ($total === 0) return 0.0;

        $z = self::WILSON_Z;
        $p = $success / $total;
        $n = $total;

        $denom = 1 + ($z * $z) / $n;
        $center = $p + ($z * $z) / (2 * $n);
        $margin = $z * sqrt(($p * (1 - $p) / $n) + ($z * $z) / (4 * $n * $n));

        return round(max(0.0, ($center - $margin) / $denom), 3);
    }

    /**
     * Avalia se padrão está pronto pra ser promovido pra hardcoded em PolicyEngine.
     * NUNCA promove sozinho — cria task pendente Wagner com proposta.
     */
    public function isPromotionCandidate(object $pattern): bool
    {
        if ($pattern->is_hardcoded) return false;
        if ($pattern->total_count < self::PROMOTE_MIN_SAMPLES) return false;

        $lowerBound = $this->wilsonLowerBound($pattern->success_count, $pattern->total_count);
        return $lowerBound >= self::PROMOTE_LOWER_BOUND;
    }

    /**
     * Lista padrões candidatos a promoção (chamado pelo command semanal).
     */
    public function listPromotionCandidates(int $businessId): array
    {
        $candidates = DB::table('mcp_decision_patterns')
            ->where('business_id', $businessId)
            ->where('is_hardcoded', false)
            ->where('total_count', '>=', self::PROMOTE_MIN_SAMPLES)
            ->orderByDesc('success_rate')
            ->get();

        $result = [];
        foreach ($candidates as $p) {
            $lower = $this->wilsonLowerBound($p->success_count, $p->total_count);
            if ($lower >= self::PROMOTE_LOWER_BOUND) {
                $result[] = [
                    'id'                  => $p->id,
                    'domain'              => $p->domain,
                    'event_type'          => $p->event_type,
                    'success_count'       => $p->success_count,
                    'total_count'         => $p->total_count,
                    'success_rate'        => (float) $p->success_rate,
                    'wilson_lower_bound'  => $lower,
                    'recommendation'      => "Adicionar '{$p->event_type}' à lista ALLOW_BRAIN_A no PolicyEngine.php (Wilson 95% LB = {$lower})",
                ];
            }
        }
        return $result;
    }

    /**
     * Drift detection — padrão que era confiável e começou a falhar.
     */
    public function detectDrift(int $businessId, int $lastNDecisions = 10): array
    {
        $patterns = DB::table('mcp_decision_patterns')
            ->where('business_id', $businessId)
            ->where('total_count', '>=', 20)
            ->where('success_rate', '>=', 0.70)
            ->get();

        $drifts = [];
        foreach ($patterns as $p) {
            $exampleIds = json_decode($p->example_decision_ids ?? '[]', true) ?: [];
            if (count($exampleIds) < $lastNDecisions) continue;

            $recentIds = array_slice($exampleIds, -$lastNDecisions);
            $recentDecisions = DB::table('mcp_dual_brain_decisions')
                ->whereIn('id', $recentIds)
                ->whereIn('outcome', ['success', 'fail', 'wagner_modified', 'wagner_rejected'])
                ->pluck('outcome')
                ->all();

            if (empty($recentDecisions)) continue;

            $recentSuccess = count(array_filter($recentDecisions, fn ($o) => in_array($o, ['success', 'wagner_modified'])));
            $recentRate = $recentSuccess / count($recentDecisions);

            // Drift = taxa recente caiu >25pp em relação à histórica
            if ($recentRate < ((float) $p->success_rate - 0.25)) {
                $drifts[] = [
                    'pattern_id'    => $p->id,
                    'domain'        => $p->domain,
                    'event_type'    => $p->event_type,
                    'rate_historic' => (float) $p->success_rate,
                    'rate_recent'   => round($recentRate, 3),
                    'sample_recent' => count($recentDecisions),
                    'recommendation' => "Padrão (\"{$p->domain}\", \"{$p->event_type}\") degradou: {$p->success_rate}→" . round($recentRate, 3) . ". Recalibrar threshold.",
                ];
            }
        }
        return $drifts;
    }

    private function patternHash(string $domain, string $eventType): string
    {
        return hash('sha256', $domain . '|' . $eventType);
    }
}
