<?php

/*
|--------------------------------------------------------------------------
| Permission Registry — Copiloto
|--------------------------------------------------------------------------
| Declarado para o PermissionRegistry (app/Services/PermissionRegistry.php)
| capturar via auto-discovery. As permissions Spatie já existem (declaradas
| em DataController@user_permissions + McpScopesSeeder).
|
| Risk levels:
|   low      — apenas leitura, sem efeito sobre dados de outros users
|   medium   — escrita reversível ou leitura cross-user
|   high     — admin de plataforma (custos/quotas/team)
|   critical — destrutivo ou impacta governança LGPD/auditoria
*/

return [
    'group' => 'Copiloto',
    'icon'  => 'compass',
    'permissions' => [
        // ── Acesso base ─────────────────────────────────────────────────
        [
            'key'      => 'copiloto.access',
            'label'    => 'Copiloto: acessar módulo',
            'risk'     => 'low',
            'requires' => [],
        ],
        [
            'key'      => 'copiloto.chat',
            'label'    => 'Copiloto: usar chat IA',
            'risk'     => 'low',
            'requires' => ['copiloto.access'],
        ],

        // ── Metas / fontes / alertas ────────────────────────────────────
        [
            'key'      => 'copiloto.metas.manage',
            'label'    => 'Copiloto: gerenciar metas e fontes',
            'risk'     => 'medium',
            'requires' => ['copiloto.access'],
        ],

        // ── Plataforma (Wagner / superadmin) ────────────────────────────
        [
            'key'      => 'copiloto.superadmin',
            'label'    => 'Copiloto: superadmin de plataforma',
            'risk'     => 'critical',
            'requires' => [],
        ],
        [
            'key'      => 'copiloto.admin.custos.view',
            'label'    => 'Copiloto: ver custos administrativos',
            'risk'     => 'high',
            'requires' => [],
        ],

        // ── MCP server (scopes via mcp_scopes / mcp_user_scopes) ────────
        [
            'key'      => 'copiloto.mcp.use',
            'label'    => 'MCP: usar servidor MCP (token Claude Code/Desktop)',
            'risk'     => 'medium',
            'requires' => ['copiloto.access'],
        ],
        [
            'key'      => 'copiloto.mcp.tasks.read',
            'label'    => 'MCP: ler task board',
            'risk'     => 'low',
            'requires' => ['copiloto.mcp.use'],
        ],
        [
            'key'      => 'copiloto.mcp.decisions.read',
            'label'    => 'MCP: ler ADRs (decisions)',
            'risk'     => 'low',
            'requires' => ['copiloto.mcp.use'],
        ],
        [
            'key'      => 'copiloto.mcp.sessions.read',
            'label'    => 'MCP: ler sessions logs',
            'risk'     => 'low',
            'requires' => ['copiloto.mcp.use'],
        ],
        [
            'key'      => 'copiloto.mcp.usage.self',
            'label'    => 'MCP: ver uso/custo próprio',
            'risk'     => 'low',
            'requires' => ['copiloto.mcp.use'],
        ],
        [
            'key'      => 'copiloto.mcp.usage.all',
            'label'    => 'MCP: ver uso/custo de todo o time',
            'risk'     => 'high',
            'requires' => [],
        ],
        [
            'key'      => 'copiloto.mcp.governanca.financeiro',
            'label'    => 'MCP: governança visão financeiro',
            'risk'     => 'high',
            'requires' => [],
        ],
        [
            'key'      => 'copiloto.mcp.governanca.tecnico',
            'label'    => 'MCP: governança visão técnico',
            'risk'     => 'high',
            'requires' => [],
        ],
        [
            'key'      => 'copiloto.mcp.memory.manage',
            'label'    => 'MCP: gerenciar memória/KB (soft-delete LGPD)',
            'risk'     => 'critical',
            'requires' => [],
        ],

        // ── Claude Code sessions (cc.read.*) ────────────────────────────
        [
            'key'      => 'copiloto.cc.read.self',
            'label'    => 'CC: ler sessões Claude Code próprias',
            'risk'     => 'low',
            'requires' => [],
        ],
        [
            'key'      => 'copiloto.cc.read.team',
            'label'    => 'CC: ler sessões Claude Code do time',
            'risk'     => 'medium',
            'requires' => [],
        ],
        [
            'key'      => 'copiloto.cc.read.all',
            'label'    => 'CC: ler todas as sessões Claude Code',
            'risk'     => 'high',
            'requires' => [],
        ],
        [
            'key'      => 'copiloto.cc.curate',
            'label'    => 'CC: curar/promover sessões pra base de conhecimento',
            'risk'     => 'high',
            'requires' => ['copiloto.cc.read.team'],
        ],
        [
            'key'      => 'copiloto.cc.ingest.self',
            'label'    => 'CC: ingerir sessões locais via watcher',
            'risk'     => 'medium',
            'requires' => [],
        ],
    ],
];
