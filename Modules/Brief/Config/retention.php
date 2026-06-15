<?php

declare(strict_types=1);

/**
 * Política de retenção de dados — Módulo Brief (D7 LGPD compliance — Wave 13).
 *
 * Declara explicitamente o tempo de retenção dos snapshots gerados pelo
 * Daily Brief (ADR 0091). Briefs são **regeneráveis** a partir das tabelas-fonte
 * (mcp_brief_inputs_cache + ADRs/commits/skills do dia) — retenção curta é
 * aceitável + economiza storage.
 *
 * **LGPD Art. 16**: dados pessoais devem ser eliminados após o término do
 * tratamento. Briefs podem conter referências indiretas (nomes/emails de
 * usuários do time interno aparecem em narrativa de "EM VOO AGORA").
 *
 * **Multi-tenant Tier 0 IRREVOGÁVEL** ([ADR 0093](memory/decisions/0093-multi-tenant-isolation-tier-0.md)):
 * Brief atualmente é estado consolidado do agent Wagner (projeto-wide), mas
 * schema mcp_briefs tem coluna `business_id` nullable pra suportar briefs
 * por tenant no futuro. Job de purge respeita scope quando coluna preenchida.
 *
 * **Zero auto-mem privada** ([ADR 0061](memory/decisions/0061-conhecimento-canonico-git-mcp-zero-automem.md)):
 * Esta config É a fonte canônica via git — visível pro time MCP.
 *
 * **PiiRedactor integrado** ([Modules\Jana\Services\Privacy\PiiRedactor](../../Jana/Services/Privacy/PiiRedactor.php)):
 * BriefGeneratorService redacta o payload JSON ANTES de mandar pra OpenAI
 * (provedor externo BR) — defesa em profundidade pra D7 LGPD.
 *
 * **Status atual (2026-05-16, Wave 13):** declaração canônica. Job
 * `brief:retention-purge` ficará em backlog Governance (ADR 0105 — sinal
 * qualificado: drift detectado OU titular pedir exclusão LGPD). Esta config
 * É a fonte da verdade pra auditoria LGPD (sub-item D7.c).
 *
 * Valores em DIAS.
 *
 * @see memory/decisions/0091-daily-brief.md
 * @see memory/decisions/0093-multi-tenant-isolation-tier-0.md
 * @see Modules\Jana\Services\Privacy\PiiRedactor
 */

return [
    /*
    |--------------------------------------------------------------------------
    | Habilitar política de retenção
    |--------------------------------------------------------------------------
    | Quando true, job `brief:retention-purge` (backlog) consulta estas configs
    | antes de deletar. Default false até job estar implementado + Wagner
    | aprovar canary (ADR 0105).
    */
    'enabled' => env('BRIEF_RETENTION_ENABLED', false),

    /*
    |--------------------------------------------------------------------------
    | Retenção por entidade (em DIAS)
    |--------------------------------------------------------------------------
    | - briefs (mcp_briefs)        : 90d — snapshots regeneráveis a partir
    |                                de mcp_brief_inputs_cache + sources.
    |                                Curto porque brief é "estado do dia",
    |                                histórico não tem valor operacional
    |                                após 3 meses (Cycle de 2 sem × 6).
    |
    | - briefs_invalid (mcp_briefs valid=0) : 30d — falhas de geração; mantém
    |                                pouco tempo pra debug, depois deleta.
    |
    | - brief_inputs_cache (mcp_brief_inputs_cache singleton) : N/A — refresh
    |                                idempotente sobrescreve linha 1; sem
    |                                histórico.
    |
    | - audit_log (mcp_audit_log tool_or_resource='brief-fetch') : 365d
    |                                (já configurado em
    |                                config/copiloto/mcp/audit_retention_days)
    */
    'entities' => [
        'briefs'           => 90,   // snapshots válidos
        'briefs_invalid'   => 30,   // snapshots com erro
        'audit_log'        => 365,  // já em mcp/audit_retention_days
    ],

    /*
    |--------------------------------------------------------------------------
    | Estratégia de purge
    |--------------------------------------------------------------------------
    | 'hard_delete' = DELETE definitivo (LGPD Art. 18 §VI — direito eliminação)
    | 'anonymize'   = mantém registro mas substitui PII via PiiRedactor
    |
    | Default 'hard_delete' pra briefs porque conteúdo é regenerável + não tem
    | valor agregado em manter histórico anonimizado (vs Jana mensagens que
    | mantém métricas agregadas via anonymize).
    */
    'strategy' => env('BRIEF_RETENTION_STRATEGY', 'hard_delete'),

    /*
    |--------------------------------------------------------------------------
    | PII Redactor integrado no path quente
    |--------------------------------------------------------------------------
    | BriefGeneratorService chama PiiRedactor->redactArray() no payload
    | aggregated ANTES de mandar pro OpenAI (provedor externo). Mesmo que
    | brief seja interno (time Wagner/Maiara/Felipe/Eliana/Luiz), payload
    | da cache pode conter nomes/emails que vazariam pra LLM externo.
    |
    | Defesa em profundidade: BriefValidator pós-geração já valida CPF/CNPJ
    | (linha 54-57 de BriefValidator.php) — esse flag controla o redact
    | pré-LLM (input-side).
    */
    'redact_pii_before_llm' => env('BRIEF_REDACT_PII_BEFORE_LLM', true),

    /*
    |--------------------------------------------------------------------------
    | Janela de aviso prévio ao titular (em DIAS)
    |--------------------------------------------------------------------------
    | Brief contém referência indireta a usuários do time. Aviso prévio não
    | aplicável (time interno, ciência implícita via Termo de Uso).
    */
    'notice_period_days' => 0,

    /*
    |--------------------------------------------------------------------------
    | Kill-switch C2+C3 (default ON) — bloco de leases ATIVOS no Daily Brief
    |--------------------------------------------------------------------------
    | SDD Leva 2 (ADR 0278). Quando true, LeaseBriefSectionService injeta sob
    | `## EM VOO AGORA` a lista de leases de coordenação ativos + o nudge
    | "claim antes de pegar". Desligar = override de config (runtime/deploy) pra
    | false → o service devolve o brief intacto (zero bloco, zero query). SEM env()
    | aqui: Larastan barra env() fora do config/ root (NoEnvCallsOutsideOfConfig);
    | o toggle vive em config('brief.lease_section') com default ON.
    | @see Modules\Brief\Services\LeaseBriefSectionService
    */
    'lease_section' => true,
];
