<?php

namespace Modules\ADS\Services;

/**
 * Observabilidade D9.a (ADR 0155): formatação pura in-memory; Tracer via
 * `OtelHelper::span(` reservado pra fontes de dados upstream.
 *
 * Converte estado técnico de uma decision em linguagem clara para Wagner.
 *
 * Toda decision tem 3 campos chave: destination, policy_applied, brain_used.
 * Esta classe gera:
 *   - oneLine:     resumo em 1 frase
 *   - whyBadge:    rótulo legível para policy_applied
 *   - actionable:  bool (true se Wagner pode/deve agir)
 *   - statusLabel: estado claro
 */
final class DecisionPresenter
{
    /** @return array{
     *   one_line:string, why_badge:string, status_label:string,
     *   actionable:bool, action_hint:string, risk_label:string
     * }
     */
    public static function explain(object $decision): array
    {
        $eventLabel  = self::eventLabel($decision->event_type);
        $domainLabel = $decision->domain;
        $dest        = $decision->destination;
        $policy      = $decision->policy_applied;
        $outcome     = $decision->outcome;
        $brainUsed   = $decision->brain_used;

        // Status legível
        $statusLabel = match (true) {
            $dest === 'blocked'                                  => 'Bloqueado permanentemente pelo firewall',
            $dest === 'pending_wagner' && $outcome === 'cancelled' => 'Aguardando você decidir',
            $dest === 'brain_b' && $brainUsed === 'brain_b'      => 'Brain B preparou instrução — aguarda sua aprovação',
            $dest === 'brain_b' && $brainUsed === 'none'         => 'Na fila do Brain B (será processado em até 5min)',
            $dest === 'brain_a'                                  => 'Executado automaticamente pelo Brain A',
            $dest === 'queued'                                   => 'Na fila (outro agente está mexendo nos mesmos arquivos)',
            $outcome === 'success'                               => 'Concluído com sucesso',
            $outcome === 'wagner_rejected'                       => 'Rejeitado por você',
            $outcome === 'wagner_modified'                       => 'Aprovado com modificações',
            $outcome === 'fail'                                  => 'Falhou na execução',
            default                                              => ucfirst($dest),
        };

        // Por que essa policy foi aplicada
        $whyBadge = match ($policy) {
            'BLOCK_ALWAYS'              => 'Firewall: ação proibida em produção',
            'REQUIRE_HUMAN_REVIEW'      => 'Sempre exige humano (não pode ser automatizado)',
            'REQUIRE_BRAIN_B'           => 'Exige análise do Brain B (Claude API)',
            'ALLOW_BRAIN_A'             => 'Brain A pode executar sozinho (se confiança suficiente)',
            'UNKNOWN_TYPE_CONSERVATIVE' => 'Tipo desconhecido — escala por segurança',
            default                     => $policy ?? '—',
        };

        // É acionável?
        $actionable = $dest === 'pending_wagner'
            || ($dest === 'brain_b' && $brainUsed === 'brain_b' && $outcome === 'cancelled');

        $actionHint = match (true) {
            $dest === 'blocked'              => 'Nada a fazer — firewall bloqueou. Apenas auditoria.',
            $dest === 'pending_wagner'       => 'Aprove ou rejeite. Sua decisão treina o sistema.',
            $dest === 'brain_b' && $brainUsed === 'brain_b' && $outcome === 'cancelled'
                                             => 'Revise a instrução do Brain B abaixo e decida.',
            $dest === 'brain_b' && $brainUsed === 'none'
                                             => 'O cron processará automaticamente nos próximos 5min.',
            $outcome === 'success'           => 'Já foi executado.',
            default                          => '',
        };

        $riskLabel = self::riskZoneLabel((float) $decision->risk_score);

        $oneLine = sprintf(
            '%s — %s (%s)',
            $eventLabel,
            $statusLabel,
            $domainLabel,
        );

        return [
            'one_line'     => $oneLine,
            'why_badge'    => $whyBadge,
            'status_label' => $statusLabel,
            'actionable'   => $actionable,
            'action_hint'  => $actionHint,
            'risk_label'   => $riskLabel,
        ];
    }

    private static function eventLabel(string $eventType): string
    {
        return match ($eventType) {
            'env_production'          => 'Tentativa de mexer em .env de produção',
            'append_only_table'       => 'Tentativa de modificar tabela imutável (CLT/Portaria 671)',
            'auth_middleware'         => 'Mudança em middleware de autenticação',
            'pii_direct_exposure'     => 'Exposição de dados pessoais (LGPD)',
            'delphi_contract'         => 'Mudança no contrato Delphi WR2',
            'composer_production'     => 'composer install/update em produção',
            'db_trigger_removal'      => 'Remoção de trigger MySQL',
            'billing_financial_flow' => 'Mudança no fluxo de cobrança',
            'lgpd_data_handling'      => 'Manipulação de dados LGPD',
            'db_schema_change'        => 'Alteração de schema do banco',
            'composer_json_change'    => 'Mudança em composer.json',
            'nfse_fiscal_logic'       => 'Lógica fiscal NFSe',
            'security_rule_change'    => 'Mudança em regra de segurança',
            'multi_tenant_scope'      => 'Mudança em scope multi-tenant',
            'new_module_creation'     => 'Criação de novo módulo Laravel',
            'service_layer_refactor'  => 'Refator de Service layer',
            'blade_view_ui_only'      => 'Mudança apenas em view',
            'migration_new_column'    => 'Migration adicionando coluna',
            'test_only_change'        => 'Mudança apenas em teste',
            'lang_file_pt_br'         => 'Atualização de tradução PT-BR',
            'adr_frontmatter_fix'     => 'Correção de frontmatter de ADR',
            'md_link_fix'             => 'Correção de link em Markdown',
            'comment_typo'            => 'Typo em comentário',
            'test_description_fix'   => 'Correção de descrição de teste',
            'mcp_sync_memory'         => 'Sync de memória MCP',
            'session_log_creation'    => 'Criação de session log',
            'unknown_commit'          => 'Commit não classificado',
            default                   => $eventType,
        };
    }

    private static function riskZoneLabel(float $score): string
    {
        return match (true) {
            $score < 0.20 => 'Baixo',
            $score < 0.40 => 'Médio',
            $score < 0.70 => 'Alto',
            default       => 'Crítico',
        };
    }
}
