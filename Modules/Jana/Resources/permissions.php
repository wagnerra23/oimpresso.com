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
            'key'      => 'jana.access',
            'label'    => 'Copiloto: acessar módulo',
            'risk'     => 'low',
            'requires' => [],
        ],
        [
            'key'      => 'jana.chat',
            'label'    => 'Copiloto: usar chat IA',
            'risk'     => 'low',
            'requires' => ['jana.access'],
        ],

        // ── Metas / fontes / alertas ────────────────────────────────────
        [
            'key'      => 'jana.metas.manage',
            'label'    => 'Copiloto: gerenciar metas e fontes',
            'risk'     => 'medium',
            'requires' => ['jana.access'],
        ],

        // ── Plataforma (Wagner / superadmin) ────────────────────────────
        [
            'key'      => 'jana.superadmin',
            'label'    => 'Copiloto: superadmin de plataforma',
            'risk'     => 'critical',
            'requires' => [],
        ],
        [
            'key'      => 'jana.admin.custos.view',
            'label'    => 'Copiloto: ver custos administrativos',
            'risk'     => 'high',
            'requires' => [],
        ],

        // ── MCP server (scopes via mcp_scopes / mcp_user_scopes) ────────
        [
            'key'      => 'jana.mcp.use',
            'label'    => 'MCP: usar servidor MCP (token Claude Code/Desktop)',
            'risk'     => 'medium',
            'requires' => ['jana.access'],
        ],
        [
            'key'      => 'jana.mcp.tasks.read',
            'label'    => 'MCP: ler task board',
            'risk'     => 'low',
            'requires' => ['jana.mcp.use'],
        ],
        [
            // Umbrella legado (backfill A3): existia no catálogo de scopes mas não
            // em permissions.php. Autoriza advance + close (backward-safe).
            'key'      => 'jana.mcp.tasks.write',
            'label'    => 'MCP: criar/atualizar/comentar tasks (umbrella legado)',
            'risk'     => 'medium',
            'requires' => ['jana.mcp.use'],
        ],
        [
            'key'      => 'jana.mcp.tasks.advance',
            'label'    => 'MCP: avançar tasks (mutação não-terminal)',
            'risk'     => 'medium',
            'requires' => ['jana.mcp.use'],
        ],
        [
            'key'      => 'jana.mcp.tasks.close',
            'label'    => 'MCP: fechar tasks (done/cancelled — terminal)',
            'risk'     => 'medium',
            'requires' => ['jana.mcp.use'],
        ],
        [
            'key'      => 'jana.mcp.decisions.read',
            'label'    => 'MCP: ler ADRs (decisions)',
            'risk'     => 'low',
            'requires' => ['jana.mcp.use'],
        ],
        [
            'key'      => 'jana.mcp.sessions.read',
            'label'    => 'MCP: ler sessions logs',
            'risk'     => 'low',
            'requires' => ['jana.mcp.use'],
        ],
        [
            'key'      => 'jana.mcp.usage.self',
            'label'    => 'MCP: ver uso/custo próprio',
            'risk'     => 'low',
            'requires' => ['jana.mcp.use'],
        ],
        [
            'key'      => 'jana.mcp.usage.all',
            'label'    => 'MCP: ver uso/custo de todo o time',
            'risk'     => 'high',
            'requires' => [],
        ],
        [
            'key'      => 'jana.mcp.governanca.financeiro',
            'label'    => 'MCP: governança visão financeiro',
            'risk'     => 'high',
            'requires' => [],
        ],
        [
            'key'      => 'jana.mcp.governanca.tecnico',
            'label'    => 'MCP: governança visão técnico',
            'risk'     => 'high',
            'requires' => [],
        ],
        [
            'key'      => 'jana.mcp.memory.manage',
            'label'    => 'MCP: gerenciar memória/KB (soft-delete LGPD)',
            'risk'     => 'critical',
            'requires' => [],
        ],

        // ── Claude Code sessions (cc.read.*) ────────────────────────────
        [
            'key'      => 'jana.cc.read.self',
            'label'    => 'CC: ler sessões Claude Code próprias',
            'risk'     => 'low',
            'requires' => [],
        ],
        [
            'key'      => 'jana.cc.read.team',
            'label'    => 'CC: ler sessões Claude Code do time',
            'risk'     => 'medium',
            'requires' => [],
        ],
        [
            'key'      => 'jana.cc.read.all',
            'label'    => 'CC: ler todas as sessões Claude Code',
            'risk'     => 'high',
            'requires' => [],
        ],
        [
            'key'      => 'jana.cc.curate',
            'label'    => 'CC: curar/promover sessões pra base de conhecimento',
            'risk'     => 'high',
            'requires' => ['jana.cc.read.team'],
        ],
        [
            'key'      => 'jana.cc.ingest.self',
            'label'    => 'CC: ingerir sessões locais via watcher',
            'risk'     => 'medium',
            'requires' => [],
        ],
    ],
];
