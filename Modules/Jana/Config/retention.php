<?php

declare(strict_types=1);

/**
 * Política de retenção de dados pessoais — Módulo Jana (D7 LGPD compliance).
 *
 * Declara explicitamente o tempo de retenção de cada entidade que armazena PII
 * no módulo Jana (chat IA + memória persistente). LGPD Art. 16: dados pessoais
 * devem ser eliminados após o término do tratamento; LGPD Art. 7º §IX permite
 * tratamento para legítimo interesse com retenção mínima necessária.
 *
 * **Multi-tenant Tier 0 IRREVOGÁVEL** ([ADR 0093](memory/decisions/0093-multi-tenant-isolation-tier-0.md)):
 * Jobs de purge respeitam `business_id` global scope — NUNCA cross-tenant cleanup.
 *
 * **Append-only contrato:**
 * - `activity_log` é AUDITORIA (LogsActivity) — NUNCA purgada, mesmo que dado-fonte seja.
 * - `jana_mensagens` é append-only (UPDATED_AT=null) — retention é hard_delete
 *   após período pra dar efeito ao Art. 18 §VI direito de eliminação.
 *
 * **Stack IA canônica preservada** ([ADR 0035](memory/decisions/0035-stack-ai-canonica-wagner-2026-04-26.md)):
 * Esta política NÃO altera contratos do `LaravelAiSdkDriver`, `MeilisearchDriver`,
 * `MemoriaContrato` nem `ContextoNegocio`. Atua apenas em jobs separados que
 * leem destas tabelas e aplicam estratégia (anonymize/hard_delete) sobre o
 * registro vivo, fora do path quente do chat.
 *
 * **Vizra rejeitada** ([ADR 0048](memory/decisions/0048-framework-agentes-laravel-ai-vizra-rejeitada.md)):
 * Implementação direta sem dependência de framework de agents externo.
 *
 * **Zero auto-mem privada** ([ADR 0061](memory/decisions/0061-conhecimento-canonico-git-mcp-zero-automem.md)):
 * Esta config É a fonte canônica via git — visível pro time MCP Felipe/Maiara/
 * Eliana/Luiz. Nenhuma decisão de retenção fica em `~/.claude/projects/`.
 *
 * Valores em DIAS. Defaults conservadores (vide racional inline).
 *
 * **Status atual (2026-05-16):** declaração canônica. Jobs `jana:retention-purge`
 * que aplicam efetivamente a política ficam em backlog pra próxima onda
 * Governance (vide ADR 0105 — só nasce com sinal qualificado: titular pedir
 * exclusão LGPD OU compliance gate detectar drift). Esta config É a fonte da
 * verdade pra auditoria LGPD (sub-item D7.c).
 *
 * @see memory/decisions/0035-stack-ai-canonica-wagner-2026-04-26.md
 * @see memory/decisions/0048-framework-agentes-laravel-ai-vizra-rejeitada.md
 * @see memory/decisions/0061-conhecimento-canonico-git-mcp-zero-automem.md
 * @see memory/decisions/0093-multi-tenant-isolation-tier-0.md
 * @see Modules\Jana\Services\Privacy\PiiRedactor
 */

return [
    /*
    |--------------------------------------------------------------------------
    | Habilitar política de retenção
    |--------------------------------------------------------------------------
    | Quando true, jobs de purge consultam estas configs antes de deletar.
    | Default false até job `jana:retention-purge` estar implementado +
    | aprovado por Wagner em canary (regra ADR 0105 — sinal qualificado).
    */
    'enabled' => env('JANA_RETENTION_ENABLED', false),

    /*
    |--------------------------------------------------------------------------
    | Retenção por entidade (em DIAS)
    |--------------------------------------------------------------------------
    | Racional (PT-BR) por entidade — escolha conservadora (mais longo > mais
    | curto) pra preservar valor do produto (Jana sem memória vira inútil), mas
    | finita pra honrar LGPD Art. 16:
    |
    | - conversa  (jana_conversas)        : 730d / 2 anos — histórico de chat
    |                                       relevante pra novas conversas via
    |                                       summarizer + recall semântico
    |
    | - mensagem  (jana_mensagens)        : 1825d / 5 anos — append-only audit
    |                                       fiscal (custo IA tracking ADR 0094
    |                                       §4); contém PII do user em `content`
    |                                       (NÃO redactado em DB — só ao mandar
    |                                       pro LLM externo via PiiRedactor)
    |
    | - sugestao  (jana_sugestoes)        : 365d / 1 ano — propostas de meta
    |                                       que NÃO viraram Meta (rejeitadas);
    |                                       sem PII direta, mas reflete intent
    |
    | - cache_semantico (jana_cache_semantico) : 90d — caches expiram natural,
    |                                       purge mais agressivo (não é memória
    |                                       canônica, é cache de resposta)
    |
    | - memoria_fato (jana_memoria_facts) : 1825d / 5 anos — memória persistente
    |                                       do user (já tem SoftDeletes via
    |                                       LGPD `esquecer()`); 5 anos cobre
    |                                       ciclo cliente longevo
    |
    | - meta + meta_periodo + meta_apuracao + meta_fonte : indefinido —
    |                                       agregados de negócio, sem PII direta
    |                                       (slugs/valores numéricos); audit
    |                                       fiscal recomenda manter
    |
    | - memoria_gabarito (jana_memoria_gabarito) : indefinido — perguntas de
    |                                       eval do sistema, sem PII real
    |
    | - memoria_metrica (jana_memoria_metricas) : 1095d / 3 anos — métricas
    |                                       diárias agregadas (Recall@3 etc),
    |                                       sem PII direta mas reflete uso
    |
    | - brief_diario (jana_brief_diarios) : 365d / 1 ano — narrativas diárias
    |                                       agregadas do business; contém
    |                                       referência a nomes de cliente
    |                                       (sanitizadas via PiiRedactor)
    |
    | - health_narrative (jana_health_narratives) : 730d / 2 anos — narrativa
    |                                       horária do Cockpit Saúde (sem PII
    |                                       direta — fatos da plataforma)
    |
    | - mcp_audit_log (mcp_audit_log)     : 365d (já configurado em
    |                                       config/copiloto/mcp/audit_retention_days)
    |
    | - embedder index Meilisearch (`jana_memoria_facts` index Scout) : 90d —
    |                                       reindex completo idempotente via
    |                                       `jana:freshness-check` + Scout
    |                                       Searchable observers; valor reduzido
    |                                       (embeddings derivados, regeneráveis)
    */
    'entities' => [
        'conversa'           => 730,    // 2 anos
        'mensagem'           => 1825,   // 5 anos (append-only + audit fiscal)
        'sugestao'           => 365,    // 1 ano (rejeitadas)
        'cache_semantico'    => 90,     // 90d (cache derivado, regenerável)
        'memoria_fato'       => 1825,   // 5 anos (memória persistente user)
        'meta'               => null,   // indefinido (agregado de negócio)
        'meta_periodo'       => null,   // indefinido (agregado de negócio)
        'meta_apuracao'      => null,   // indefinido (audit fiscal)
        'meta_fonte'         => null,   // indefinido (config técnica)
        'memoria_gabarito'   => null,   // indefinido (eval sintético)
        'memoria_metrica'    => 1095,   // 3 anos (métricas agregadas)
        'brief_diario'       => 365,    // 1 ano (narrativa diária)
        'health_narrative'   => 730,    // 2 anos (Cockpit Saúde)
        'mcp_audit_log'      => 365,    // 1 ano (já em config/copiloto/mcp)
        'embedder_index'     => 90,     // 90d (Meilisearch derivado)
    ],

    /*
    |--------------------------------------------------------------------------
    | Estratégia de purge
    |--------------------------------------------------------------------------
    | 'soft_delete' = marca `deleted_at` (recuperável; só onde Model tem
    |                 SoftDeletes — ex: MemoriaFato)
    | 'hard_delete' = DELETE definitivo (LGPD Art. 18 §VI — direito eliminação)
    | 'anonymize'   = mantém registro mas substitui PII em colunas livres por
    |                 placeholder via PiiRedactor (ex: jana_mensagens.content
    |                 com CPF do user — redact mas manter linha)
    |
    | Default 'anonymize' preserva métricas agregadas (count conversas, tokens
    | médios, ticket médio do chat) sem reter dado pessoal — alinha LGPD com
    | necessidade operacional.
    */
    'strategy' => env('JANA_RETENTION_STRATEGY', 'anonymize'),

    /*
    |--------------------------------------------------------------------------
    | Janela de aviso prévio ao titular (em DIAS)
    |--------------------------------------------------------------------------
    | LGPD Art. 18 §VI sugere aviso prévio antes de eliminação. Job de purge
    | dispara notificação ao titular (via owner do business) N dias antes
    | do delete real.
    */
    'notice_period_days' => 30,

    /*
    |--------------------------------------------------------------------------
    | Override per-business (TODO US-JANA-LGPD-001)
    |--------------------------------------------------------------------------
    | Mecanismo opt-in pra cliente B2B configurar retenção mais curta que o
    | default (Art. 18 §IV — direito de informação sobre tratamento). Quando
    | implementado, ler de `jana_business_settings.retention_overrides` JSON.
    */
    'allow_per_business_override' => false,
];
