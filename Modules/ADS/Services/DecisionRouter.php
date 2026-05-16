<?php

namespace Modules\ADS\Services;

use App\Util\OtelHelper;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * ARQ-0003 — Ponto único de entrada de eventos no ADS.
 * Algoritmo determinístico: mesmo input → mesmo output. Sem LLM.
 *
 * Hierarquia de precedência (ARQ-0010):
 *   1. Policy Engine (veto absoluto)
 *   2. Serialização por mutex de arquivo
 *   3. Risk + Confidence → threshold
 *   4. Destino: brain_a / brain_b / pending_wagner / blocked / queued
 *
 * Observabilidade: `route()` envolto em OTel span (D9.a — ADR 0155).
 * Multi-tenant Tier 0: span auto-resolve `business_id` + propaga `$input->businessId` no attribute.
 */
class DecisionRouter
{
    public function __construct(
        private readonly PolicyEngine    $policy,
        private readonly RiskEngine      $risk,
        private readonly ConfidenceEngine $confidence,
    ) {}

    /**
     * Roteia o evento e retorna a decisão.
     * Grava em mcp_dual_brain_decisions ANTES de retornar.
     */
    public function route(RoutingInput $input): RoutingDecision
    {
        return OtelHelper::spanBiz('ads.decision_router.route', function () use ($input): RoutingDecision {
            // 1. Policy Engine — veto absoluto
            $policyResult = $this->policy->check($input->eventType);

            if ($policyResult->isBlocked()) {
                return $this->record($input, 'blocked', 0.0, 0.0, $policyResult->rule, 3);
            }

            if ($policyResult->requiresHuman()) {
                return $this->record($input, 'pending_wagner', 0.0, 0.0, $policyResult->rule, 3);
            }

            // 2. Serialização — mutex por arquivo
            if ($this->hasActiveLock($input->filesAffected)) {
                return $this->record($input, 'queued', 0.0, 0.0, $policyResult->rule, 2);
            }

            // 3. Calcular scores
            $riskResult       = $this->risk->calculate($input->eventType);
            $confidenceScore  = $this->confidence->getScore($input->domain, $input->eventType);
            $hitlLevel        = $this->confidence->getHitlLevel($input->domain, $input->eventType);

            // 4. Threshold do DB (global ou por domínio/tipo)
            $threshold = $this->getThreshold($input->domain, $input->eventType);

            // 5. Roteamento
            $destination = $this->computeDestination(
                $riskResult->score,
                $confidenceScore,
                $policyResult,
                $threshold,
            );

            return $this->record(
                $input,
                $destination,
                $riskResult->score,
                $confidenceScore,
                $policyResult->rule,
                $destination === 'brain_a' ? min($hitlLevel, 1) : ($destination === 'brain_b' ? 2 : 3),
            );
        }, [
            'module' => 'ADS',
            'input_business_id' => $input->businessId,
            'event_type' => $input->eventType,
            'domain' => $input->domain,
            'event_source' => $input->eventSource,
        ]);
    }

    private function computeDestination(
        float        $riskScore,
        float        $confidenceScore,
        PolicyResult $policyResult,
        array        $threshold,
    ): string {
        // Policy explicitamente requer Brain B
        if ($policyResult->action === PolicyResult::ACTION_REQUIRE_BRAIN_B) {
            if ($riskScore >= $threshold['brain_b_risk_max']) {
                return 'pending_wagner';
            }
            return 'brain_b';
        }

        // Zone vermelha → sempre Wagner
        if ($riskScore >= $threshold['brain_b_risk_max']) {
            return 'pending_wagner';
        }

        // Zone verde + confiança alta + Policy permite Brain A → autônomo
        if (
            $riskScore < $threshold['brain_a_risk_max']
            && $confidenceScore >= $threshold['brain_a_conf_min']
            && $policyResult->allowsBrainA()
        ) {
            return 'brain_a';
        }

        // Zona intermediária → Brain B
        return 'brain_b';
    }

    protected function record(
        RoutingInput $input,
        string       $destination,
        float        $riskScore,
        float        $confidenceScore,
        string       $policyApplied,
        int          $hitlLevel,
    ): RoutingDecision {
        $id = DB::table('mcp_dual_brain_decisions')->insertGetId([
            'business_id'      => $input->businessId,
            'event_type'       => $input->eventType,
            'event_source'     => $input->eventSource,
            'domain'           => $input->domain,
            'files_affected'   => json_encode($input->filesAffected),
            'event_metadata'   => json_encode($input->metadata),
            'risk_score'       => $riskScore,
            'confidence_score' => $confidenceScore,
            'policy_applied'   => $policyApplied,
            'destination'      => $destination,
            'hitl_level'       => $hitlLevel,
            'brain_used'       => 'none',
            'outcome'          => 'cancelled',
            'created_at'       => now(),
        ]);

        return new RoutingDecision(
            decisionId:      $id,
            destination:     $destination,
            riskScore:       $riskScore,
            confidenceScore: $confidenceScore,
            policyApplied:   $policyApplied,
            hitlLevel:       $hitlLevel,
        );
    }

    protected function hasActiveLock(array $files): bool
    {
        if (empty($files)) {
            return false;
        }

        return DB::table('mcp_file_locks')
            ->whereIn('file_path', $files)
            ->where('expires_at', '>', now())
            ->exists();
    }

    protected function getThreshold(string $domain, string $eventType): array
    {
        // Tenta threshold específico por (domínio × tipo), fallback global
        $row = DB::table('mcp_decision_thresholds')
            ->where(function ($q) use ($domain, $eventType) {
                $q->where('domain', $domain)->where('event_type', $eventType);
            })
            ->orWhere(function ($q) use ($domain) {
                $q->where('domain', $domain)->where('event_type', '*');
            })
            ->orWhere(function ($q) {
                $q->where('domain', '*')->where('event_type', '*');
            })
            ->orderByRaw("CASE WHEN domain != '*' AND event_type != '*' THEN 0
                               WHEN domain != '*' THEN 1
                               ELSE 2 END")
            ->first();

        if ($row) {
            return [
                'brain_a_risk_max' => (float) $row->brain_a_risk_max,
                'brain_a_conf_min' => (float) $row->brain_a_conf_min,
                'brain_b_risk_max' => (float) $row->brain_b_risk_max,
            ];
        }

        // Fallback para config (sem DB)
        return [
            'brain_a_risk_max' => (float) config('ads.brain_a_risk_max', 0.30),
            'brain_a_conf_min' => (float) config('ads.brain_a_conf_min', 0.70),
            'brain_b_risk_max' => (float) config('ads.brain_b_risk_max', 0.70),
        ];
    }
}
