<?php

/**
 * Declaração de permissions do módulo KB pro PermissionRegistry novo
 * (contrato em flight em `app/Services/PermissionRegistry.php`).
 *
 * Campos:
 *   - key         (string)  identificador estável (ex.: kb.view)
 *   - label       (string)  nome curto humano
 *   - description (string)  o que a permission libera
 *   - risk        (low|medium|high) impacto se concedida indevidamente
 *   - requires    (array, opcional) outras permissions implícitas
 *
 * IMPORTANTE — dívida técnica preservada:
 *
 * As chaves abaixo são DECLARATIVAS pro PermissionRegistry / UI de roles.
 * O middleware Spatie `can:` real continua usando `copiloto.mcp.memory.manage`
 * nos controllers KB (V1). Rename real é PR separado (Spatie migration +
 * roles refresh).
 *
 * **ONDA 1 (2026-05-15) — adicionadas 7 novas permissions (SCHEMA §12):**
 *   - kb.write (medium)            → criar/editar artigos kb_nodes
 *   - kb.publish.path (medium)     → criar/editar kb_paths (trilhas)
 *   - kb.publish.troubleshoot (medium) → criar/editar kb_decision_trees
 *   - kb.graph.view (low)          → visualização-grafo Cytoscape
 *   - kb.favorite (low)            → favoritar próprio
 *   - kb.comment (low)             → comentar inline
 *   - kb.ai.ask (medium)           → perguntar IA (RAG ONDA 4)
 */

return [
    // ----- V0 (pré-ONDA 1) — chaves originais do KB browser MCP -----
    [
        'key'         => 'kb.view',
        'label'       => 'KB: Ver biblioteca',
        'description' => 'Lista, busca e abre docs da Knowledge Base (ADRs, sessions, runbooks, comparativos sincronizados de memory/* via webhook GitHub).',
        'risk'        => 'low',
    ],
    [
        'key'         => 'kb.softdelete',
        'label'       => 'KB: Soft-delete LGPD',
        'description' => 'Marca um doc como deletado (mantém auditoria em mcp_audit_log e history). Operação reversível em 30 dias via kb.restore. Próximo sync GitHub re-cria se o arquivo continuar no repo.',
        'risk'        => 'high',
        'requires'    => ['kb.view'],
    ],
    [
        'key'         => 'kb.restore',
        'label'       => 'KB: Restaurar doc',
        'description' => 'Restaura doc soft-deletado.',
        'risk'        => 'medium',
        'requires'    => ['kb.view'],
    ],
    [
        'key'         => 'kb.history.view',
        'label'       => 'KB: Ver histórico de versões',
        'description' => 'Lista revisões anteriores (git_sha + diffs) de cada doc.',
        'risk'        => 'low',
        'requires'    => ['kb.view'],
    ],

    // ----- ONDA 1 (2026-05-15) — KB unificado como grafo (ADR 0149) -----
    [
        'key'         => 'kb.write',
        'label'       => 'KB: Criar/editar artigos',
        'description' => 'Cria e edita kb_nodes de tipo article (operacionais editáveis). Bridges canônicos (ADR/session/charter) permanecem read-only mesmo com esta permission.',
        'risk'        => 'medium',
        'requires'    => ['kb.view'],
    ],
    [
        'key'         => 'kb.publish.path',
        'label'       => 'KB: Criar/editar trilhas',
        'description' => 'Cria e edita kb_paths (trilhas de aprendizado por persona) + steps ordenados.',
        'risk'        => 'medium',
        'requires'    => ['kb.view'],
    ],
    [
        'key'         => 'kb.publish.troubleshoot',
        'label'       => 'KB: Criar/editar troubleshooters',
        'description' => 'Cria e edita kb_decision_trees (perguntas Q→Sim/Não→Fix) e seus steps.',
        'risk'        => 'medium',
        'requires'    => ['kb.view'],
    ],
    [
        'key'         => 'kb.graph.view',
        'label'       => 'KB: Ver visualização-grafo',
        'description' => 'Acesso à página /kb/graph com visualização Cytoscape do grafo completo de nós + arestas.',
        'risk'        => 'low',
        'requires'    => ['kb.view'],
    ],
    [
        'key'         => 'kb.favorite',
        'label'       => 'KB: Favoritar (próprio)',
        'description' => 'Toggle bookmark de kb_nodes pra acesso rápido. Apenas favoritos próprios — não vê os dos outros.',
        'risk'        => 'low',
        'requires'    => ['kb.view'],
    ],
    [
        'key'         => 'kb.comment',
        'label'       => 'KB: Comentar inline',
        'description' => 'Cria comentários ancorados em block_idx específico do body_blocks. Visíveis pra todos do business.',
        'risk'        => 'low',
        'requires'    => ['kb.view'],
    ],
    [
        'key'         => 'kb.ai.ask',
        'label'       => 'KB: Perguntar à IA (RAG)',
        'description' => 'Endpoint /kb/ai/ask — IA generativa Anthropic via Copiloto/Jana responde sobre o corpus KB com citações. Custo monitorado.',
        'risk'        => 'medium',
        'requires'    => ['kb.view'],
    ],
];
