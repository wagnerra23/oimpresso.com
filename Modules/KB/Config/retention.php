<?php

declare(strict_types=1);

/**
 * Política de retenção de dados — Módulo KB (D7 LGPD compliance).
 *
 * KB = Knowledge Base estruturada (categorias/subcategorias/nodes/edges/paths/
 * decision-trees). Conteúdo dominantemente NÃO-PII (artigos, guias, runbooks),
 * com PII residual em:
 * - `kb_comments` (autor user_id + texto livre — possível PII no corpo)
 * - `kb_favorites` (user_id + node_id — preferência pessoal)
 * - `kb_node_versions` (autor da revisão; conteúdo geralmente público)
 * - `kb_bridge_state` (estado sync com fonte externa — sem PII bruta)
 *
 * LGPD Art. 16: dados pessoais devem ser eliminados após o término do tratamento.
 *
 * **Multi-tenant Tier 0 IRREVOGÁVEL** ([ADR 0093]):
 * Jobs de purge respeitam `business_id` global scope — NUNCA cross-tenant cleanup.
 *
 * **Append-only contrato:**
 * `kb_node_versions` é HISTÓRICO DE EDIÇÃO (audit conteúdo) — preservado long-term
 * por valor de governança documental. Anonymize autor preserva o conteúdo da
 * revisão.
 *
 * Valores em DIAS. Defaults conservadores priorizando preservação de conhecimento
 * (KB é ativo de valor crescente, NÃO dado operacional descartável).
 *
 * **Status atual (2026-05-16):** declaração canônica. Jobs `kb:retention-purge`
 * em backlog. Esta config É a fonte da verdade pra auditoria LGPD (sub-item D7.c
 * rubrica governance v3).
 *
 * @see memory/decisions/0093-multi-tenant-isolation-tier-0.md
 * @see Modules\Jana\Services\Privacy\PiiRedactor
 */

return [
    /*
    |--------------------------------------------------------------------------
    | Habilitar política de retenção
    |--------------------------------------------------------------------------
    */
    'enabled' => env('KB_RETENTION_ENABLED', false),

    /*
    |--------------------------------------------------------------------------
    | Retenção por tabela (em DIAS)
    |--------------------------------------------------------------------------
    | kb_categories/subcategories/nodes/edges/paths/path_steps/decision_trees/
    | decision_tree_steps: null (indefinido — conhecimento canônico, ativo
    |                      organizacional preservado long-term)
    | kb_node_versions: null (indefinido — histórico edição documental; autor
    |                   pode ser anonimizado preservando conteúdo)
    | kb_comments: 1095d (3y) — texto livre potencialmente PII; janela
    |              conservadora de relevância de discussão
    | kb_favorites: 1825d (5y) — preferência pessoal sobreviver mudanças de
    |               cargo/equipe
    | kb_bridge_state: 365d (1y) — estado operacional sync (cache/checkpoint)
    */
    'tabelas' => [
        'kb_categories'             => null,   // indefinido (catálogo)
        'kb_subcategories'          => null,   // indefinido (catálogo)
        'kb_nodes'                  => null,   // indefinido (conteúdo canônico)
        'kb_edges'                  => null,   // indefinido (grafo de relação)
        'kb_paths'                  => null,   // indefinido (trilha aprendizagem)
        'kb_path_steps'             => null,   // indefinido (passo trilha)
        'kb_decision_trees'         => null,   // indefinido (árvore decisão)
        'kb_decision_tree_steps'    => null,   // indefinido (passo árvore)
        'kb_node_versions'          => null,   // indefinido (audit edição)
        'kb_comments'               => 1095,   // 3 anos (PII possível em corpo)
        'kb_favorites'              => 1825,   // 5 anos (preferência pessoal)
        'kb_bridge_state'           => 365,    // 1 ano (sync cache)
    ],

    /*
    |--------------------------------------------------------------------------
    | Estratégia de purge
    |--------------------------------------------------------------------------
    | Default 'anonymize' substitui autor (user_id → null/placeholder) preservando
    | o conteúdo da revisão/comentário — alinha LGPD com preservação de
    | conhecimento organizacional.
    */
    'strategy' => env('KB_RETENTION_STRATEGY', 'anonymize'),

    /*
    |--------------------------------------------------------------------------
    | Janela de aviso prévio ao titular (em DIAS)
    |--------------------------------------------------------------------------
    */
    'notice_period_days' => 30,
];
