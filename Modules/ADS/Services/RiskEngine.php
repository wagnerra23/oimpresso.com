<?php

namespace Modules\ADS\Services;

use App\Util\OtelHelper;

/**
 * ARQ-0004 — Quantifica o risco de executar uma ação sem supervisão humana.
 *
 * risco = impacto × incerteza × (1 − reversibilidade) × criticidade_sistema
 *
 * Priors hardcoded por event_type. Nunca modificar por LLM ou banco.
 * Calibração de incerteza via histórico após 10+ execuções (Learning Loop L1).
 *
 * Observabilidade: `calculate()` envolto em OTel span (D9.a — ADR 0155).
 * Multi-tenant Tier 0: `OtelHelper::spanBiz` auto-resolve `business_id` da sessão.
 */
final class RiskEngine
{
    /**
     * [impacto, incerteza_inicial, reversibilidade, criticidade]
     * Todos os valores em range 0.0–1.0.
     */
    private const PRIORS = [
        'env_production'          => [1.0, 1.0, 0.00, 1.0],  // → 1.000 BLOCK
        'append_only_table'       => [1.0, 1.0, 0.00, 1.0],  // → 1.000 BLOCK
        'pii_direct_exposure'     => [1.0, 0.9, 0.00, 1.0],  // → 0.900 BLOCK
        'auth_middleware'         => [0.9, 0.7, 0.20, 1.0],  // → 0.504
        'billing_financial_flow'  => [0.8, 0.6, 0.30, 0.9],  // → 0.302
        'db_schema_change'        => [0.9, 0.8, 0.10, 0.9],  // → 0.583
        'composer_production'     => [0.7, 0.8, 0.20, 0.8],  // → 0.358
        'db_trigger_removal'      => [0.9, 0.9, 0.05, 0.9],  // → 0.690
        'delphi_contract'         => [1.0, 1.0, 0.00, 1.0],  // → 1.000 BLOCK
        'lgpd_data_handling'      => [0.8, 0.7, 0.20, 0.9],  // → 0.403
        'composer_json_change'    => [0.7, 0.7, 0.30, 0.8],  // → 0.275
        'nfse_fiscal_logic'       => [0.9, 0.8, 0.10, 0.9],  // → 0.583
        'security_rule_change'    => [0.8, 0.7, 0.20, 0.9],  // → 0.403
        'multi_tenant_scope'      => [0.8, 0.8, 0.20, 0.8],  // → 0.410
        'new_module_creation'     => [0.6, 0.7, 0.40, 0.7],  // → 0.176
        'service_layer_refactor'  => [0.5, 0.5, 0.60, 0.5],  // → 0.050
        'blade_view_ui_only'      => [0.3, 0.4, 0.80, 0.2],  // → 0.005
        'migration_new_column'    => [0.6, 0.6, 0.40, 0.7],  // → 0.101
        'test_only_change'        => [0.2, 0.3, 0.90, 0.1],  // → 0.001
        'lang_file_pt_br'         => [0.2, 0.2, 0.90, 0.1],  // → 0.001
        'adr_frontmatter_fix'     => [0.1, 0.3, 1.00, 0.1],  // → 0.000
        'md_link_fix'             => [0.1, 0.2, 1.00, 0.1],  // → 0.000
        'comment_typo'            => [0.1, 0.2, 1.00, 0.1],  // → 0.000
        'test_description_fix'    => [0.1, 0.2, 1.00, 0.1],  // → 0.000
        'mcp_sync_memory'         => [0.2, 0.2, 0.90, 0.1],  // → 0.001
        'session_log_creation'    => [0.1, 0.1, 1.00, 0.1],  // → 0.000
    ];

    /**
     * Incerteza default para event_type nunca visto (conservador).
     */
    private const UNKNOWN_UNCERTAINTY = 0.80;

    /**
     * Calcula o score de risco (0.0–1.0) para um evento.
     *
     * @param  string      $eventType          Tipo canônico do evento
     * @param  float|null  $calibratedUncertainty  Incerteza calibrada pelo L1 (null = usa prior)
     */
    public function calculate(string $eventType, ?float $calibratedUncertainty = null): RiskResult
    {
        return OtelHelper::spanBiz('ads.risk_engine.calculate', function () use ($eventType, $calibratedUncertainty): RiskResult {
            [$impact, $priorUncertainty, $reversibility, $criticality] = $this->getPrior($eventType);

            $uncertainty = $calibratedUncertainty ?? $priorUncertainty;
            $uncertainty = max(0.0, min(1.0, $uncertainty));

            $score = $impact * $uncertainty * (1.0 - $reversibility) * $criticality;
            $score = round(max(0.0, min(1.0, $score)), 3);

            return new RiskResult(
                score: $score,
                eventType: $eventType,
                impact: $impact,
                uncertainty: $uncertainty,
                reversibility: $reversibility,
                criticality: $criticality,
                usedPrior: $calibratedUncertainty === null,
            );
        }, [
            'module' => 'ADS',
            'event_type' => $eventType,
            'used_prior' => $calibratedUncertainty === null,
        ]);
    }

    /**
     * Calcula incerteza calibrada usando histórico de execuções.
     * Chamado pelo Learning Loop L1 após cada execução.
     * ARQ-0004: redução máxima de 50% do prior com 100% de sucesso.
     *
     * @param  string  $eventType
     * @param  float   $historicalSuccessRate  Taxa de sucesso (0.0–1.0)
     */
    public function calibrateUncertainty(string $eventType, float $historicalSuccessRate): float
    {
        [, $priorUncertainty] = $this->getPrior($eventType);
        $calibrated = $priorUncertainty * (1.0 - ($historicalSuccessRate * 0.5));
        return round(max(0.01, $calibrated), 3);
    }

    public function zone(float $score): string
    {
        return match (true) {
            $score < 0.20 => 'green',
            $score < 0.40 => 'yellow',
            $score < 0.70 => 'orange',
            default       => 'red',
        };
    }

    private function getPrior(string $eventType): array
    {
        return self::PRIORS[$eventType] ?? [0.5, self::UNKNOWN_UNCERTAINTY, 0.40, 0.5];
    }
}
