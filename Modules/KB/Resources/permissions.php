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
 * IMPORTANTE — dívida técnica:
 *
 * As chaves abaixo (`kb.view`, `kb.softdelete`, `kb.restore`,
 * `kb.history.view`) são DECLARATIVAS apenas — a permission Spatie real
 * usada pelo middleware `can:` no KbController continua sendo
 * `copiloto.mcp.memory.manage` (mesma roda Spatie do antigo
 * /copiloto/admin/memoria).
 *
 * Rename real → migration `update permissions set name='kb.manage' where
 * name='copiloto.mcp.memory.manage'` + atualização de roles assigned + fix
 * em McpScopesSeeder e seeders downstream — fora de escopo desta etapa
 * (move-only). Rastrear em PR separado.
 */

return [
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
];
