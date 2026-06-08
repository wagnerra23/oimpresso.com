<?php

namespace Modules\ADS\Ai\Agents;

use Laravel\Ai\Contracts\Agent;
use Laravel\Ai\Promptable;
use Stringable;

/**
 * T9 — PlannerAgent (decompõe tarefa complexa em subtarefas executáveis).
 *
 * Inspirado em ReAct (Yao 2022) + Tree-of-Thoughts (Yao 2023).
 *
 * Disparado quando uma decision é classificada como "complexa" — heurística:
 *   - event_type indica refator/criação (service_layer_refactor, new_module_creation)
 *   - estimated_complexity > 0.6 (vem de annotation futura)
 *   - Wagner clica "Quebrar em subtarefas" no detalhe
 *
 * Output: JSON com lista de subtarefas, cada uma com event_type/domain/files.
 * O PlannerService cria child decisions com parent_decision_id apontando pra original.
 *
 * Modelo: Claude Sonnet 4.6 (planning é tarefa complexa, não usa Haiku).
 */
class PlannerAgent implements Agent
{
    use Promptable;

    public function __construct(
        public string  $eventType,
        public string  $domain,
        public string  $originalDescription,
        public array   $filesAffected = [],
        public array   $context = [],
    ) {}

    public function instructions(): Stringable|string
    {
        return <<<PROMPT
        Você é o PlannerAgent do Adaptive Decision System (ADS) do oimpresso ERP.

        Recebe uma tarefa complexa e decompõe em SUBTAREFAS atômicas, cada uma:
          - executável independentemente (ou com dependência declarada)
          - mapeada para event_type canônico do ADS
          - com files_affected concretos
          - com critério de aceite claro

        REGRAS DE DECOMPOSIÇÃO:
        1. Cada subtarefa = 1 unidade de trabalho (~30 min de execução máx)
        2. Ordene por dependência: subtarefa N só roda após dependências
        3. Se possível, prefira subtarefas ALLOW_BRAIN_A (ex: lang_file_pt_br,
           md_link_fix, adr_frontmatter_fix) — Brain A executa autônomo
        4. Mantenha ao menos 1 subtarefa de teste (test_only_change) ao final
        5. Se a tarefa é muito grande (>10 subtarefas), pare em 10 e marque
           "needs_further_planning": true

        CATÁLOGO DE event_type DISPONÍVEIS:
          db_schema_change, service_layer_refactor, blade_view_ui_only,
          migration_new_column, test_only_change, lang_file_pt_br,
          adr_frontmatter_fix, md_link_fix, comment_typo, test_description_fix,
          mcp_sync_memory, session_log_creation, composer_json_change,
          nfse_fiscal_logic, billing_financial_flow, lgpd_data_handling

        REGRAS INVIOLÁVEIS:
        - NUNCA decomponha em event_types que estão em BLOCK_ALWAYS
          (env_production, append_only_table, auth_middleware, pii_direct_exposure,
           delphi_contract, composer_production, db_trigger_removal,
           billing_financial_flow). Se a decomposição precisaria disso,
           retorne JSON com "rejected": true e motivo.

        FORMATO OBRIGATÓRIO (JSON estrito, sem markdown):
        {
          "decomposition_summary": "1 frase explicando estratégia",
          "subtasks": [
            {
              "order": 1,
              "depends_on": [],
              "event_type": "...",
              "domain": "...",
              "title": "...",
              "files_affected": [],
              "acceptance_criteria": "...",
              "estimated_minutes": 15
            }
          ],
          "needs_further_planning": false,
          "rejected": false,
          "rejection_reason": null,
          "confidence": 0.0-1.0
        }
        PROMPT;
    }

    public function montarPrompt(): string
    {
        $files = empty($this->filesAffected) ? '(nenhum especificado)' : implode("\n  - ", $this->filesAffected);
        $ctx = empty($this->context) ? '(vazio)' : json_encode($this->context, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);

        return <<<PROMPT
        TAREFA COMPLEXA PARA DECOMPOR:

        event_type:  {$this->eventType}
        domain:      {$this->domain}

        DESCRIÇÃO:
        {$this->originalDescription}

        files_affected:
          - {$files}

        CONTEXTO:
        {$ctx}

        Decomponha em subtarefas atômicas seguindo as regras. Retorne JSON estrito.
        PROMPT;
    }
}
