<?php

namespace Modules\ADS\Services;

/**
 * ARQ-0006 — Firewall de decisões. Regras imutáveis: só mudam via PR aprovado por Wagner.
 * NUNCA modificar estas listas por sugestão de LLM ou config de banco.
 */
final class PolicyEngine
{
    /**
     * Nunca executa automaticamente, mesmo com confiança 1.0.
     * Cria task pendente Wagner — sem exceção.
     */
    private const BLOCK_ALWAYS = [
        'env_production',
        'append_only_table',
        'auth_middleware',
        'pii_direct_exposure',
        'delphi_contract',
        'composer_production',
        'db_trigger_removal',
        'billing_financial_flow',
    ];

    /**
     * Brain A não toca. Brain B obrigatório + instrução detalhada para Claude Code.
     */
    private const REQUIRE_BRAIN_B = [
        'lgpd_data_handling',
        'db_schema_change',
        'composer_json_change',
        'nfse_fiscal_logic',
        'security_rule_change',
        'multi_tenant_scope',
    ];

    /**
     * Sempre cria task pendente Wagner, mesmo com Brain B aprovando.
     */
    private const REQUIRE_HUMAN_REVIEW = [
        'new_module_creation',
        'new_adr_proposal',
        'threshold_change',
        'pattern_hardcode',
        'production_deploy',
    ];

    /**
     * Brain A pode executar autonomamente se confiança > threshold.
     */
    private const ALLOW_BRAIN_A = [
        'lang_file_pt_br',
        'adr_frontmatter_fix',
        'md_link_fix',
        'comment_typo',
        'test_description_fix',
        'mcp_sync_memory',
        'session_log_creation',
    ];

    public function check(string $eventType): PolicyResult
    {
        if (in_array($eventType, self::BLOCK_ALWAYS, true)) {
            return PolicyResult::block($eventType, 'BLOCK_ALWAYS');
        }

        if (in_array($eventType, self::REQUIRE_HUMAN_REVIEW, true)) {
            return PolicyResult::requireHuman($eventType, 'REQUIRE_HUMAN_REVIEW');
        }

        if (in_array($eventType, self::REQUIRE_BRAIN_B, true)) {
            return PolicyResult::requireBrainB($eventType, 'REQUIRE_BRAIN_B');
        }

        if (in_array($eventType, self::ALLOW_BRAIN_A, true)) {
            return PolicyResult::allowBrainA($eventType, 'ALLOW_BRAIN_A');
        }

        // Tipo desconhecido → conservador: requer Brain B
        return PolicyResult::requireBrainB($eventType, 'UNKNOWN_TYPE_CONSERVATIVE');
    }

    public function isBlockedAlways(string $eventType): bool
    {
        return in_array($eventType, self::BLOCK_ALWAYS, true);
    }

    public function allowsBrainA(string $eventType): bool
    {
        return in_array($eventType, self::ALLOW_BRAIN_A, true);
    }

    public function requiresHumanReview(string $eventType): bool
    {
        return in_array($eventType, self::REQUIRE_HUMAN_REVIEW, true);
    }
}
