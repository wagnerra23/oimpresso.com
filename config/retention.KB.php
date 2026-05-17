<?php

declare(strict_types=1);

/**
 * Política de retenção — Módulo KB (D7.c LGPD compliance).
 *
 * Wave 26 — Mirror canonical de `Modules/KB/Config/retention.php` no path
 * `config/retention.{Name}.php` que o ModuleGradeService D7.c procura.
 *
 * KB = Knowledge Base estruturada (categorias/subcategorias/nodes/edges/paths/
 * decision-trees). Conteúdo dominantemente NÃO-PII, com PII residual em:
 *   - `kb_comments` (autor + texto livre — possível PII no corpo)
 *   - `kb_favorites` (user_id + node_id — preferência pessoal)
 *   - `kb_node_versions` (autor da revisão; conteúdo geralmente público)
 *   - `kb_bridge_state` (estado sync com fonte externa — sem PII bruta)
 *
 * LGPD Art. 16: dados pessoais devem ser eliminados após tratamento.
 *
 * Multi-tenant Tier 0 IRREVOGÁVEL (ADR 0093): jobs de purge respeitam
 * `business_id` global scope (`BusinessScope`) — NUNCA cross-tenant cleanup.
 *
 * Append-only: kb_node_versions é HISTÓRICO DE EDIÇÃO documental, preservado
 * long-term. Strategy `anonymize` substitui autor (user_id → null) mantendo
 * conteúdo organizacional.
 *
 * @see Modules/KB/Config/retention.php — fonte detalhada com explicações
 * @see memory/decisions/0093-multi-tenant-isolation-tier-0.md
 * @see memory/decisions/0061-conhecimento-canonico-git-mcp-zero-automem.md
 */

return [
    'enabled' => env('KB_RETENTION_ENABLED', false),

    // Valores em DIAS — null = indefinido (preservação canônica)
    'tabelas' => [
        'kb_categories'             => null,
        'kb_subcategories'          => null,
        'kb_nodes'                  => null,
        'kb_edges'                  => null,
        'kb_paths'                  => null,
        'kb_path_steps'             => null,
        'kb_decision_trees'         => null,
        'kb_decision_tree_steps'    => null,
        'kb_node_versions'          => null,
        'kb_comments'               => 1095,   // 3y — texto livre PII potencial
        'kb_favorites'              => 1825,   // 5y — preferência pessoal
        'kb_bridge_state'           => 365,    // 1y — sync cache operacional
    ],

    'strategy'           => env('KB_RETENTION_STRATEGY', 'anonymize'),
    'notice_period_days' => 30,
];
