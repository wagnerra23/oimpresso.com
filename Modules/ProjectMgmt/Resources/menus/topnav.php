<?php

/**
 * TopNav declarativo do ProjectMgmt.
 *
 * Lido pelo LegacyMenuAdapter::buildTopNavs() e exposto em
 * `shell.topnavs.ProjectMgmt` via Inertia.
 *
 * Permissions herdadas do Copiloto (não renomeadas — mesmo padrão TeamMcp).
 *
 * Itens listados refletem o roadmap PROJECT-1 (UI Web Fase 7 do ADR 0070);
 * só Board está implementado nesta primeira PR. Backlog/Roadmap/My Work/Inbox
 * são placeholders pra ordem de menu — entram nas próximas PRs.
 */

return [
    'label' => 'Project Mgmt',
    'icon'  => 'KanbanSquare',
    'items' => [
        ['label' => 'My Work',          'href' => '/project-mgmt/my-work',  'icon' => 'CheckSquare',   'can' => 'copiloto.mcp.usage.all'],
        // 2026-05-29: Triagem + Caixa de entrada adicionadas ao lado de My Work.
        // Antes só acessíveis por URL direta — agora navegáveis via nav intra-módulo.
        // hrefs single-prefix /project-mgmt/{triage,inbox} (NÃO dobrar o prefixo).
        ['label' => 'Triagem',          'href' => '/project-mgmt/triage',   'icon' => 'Inbox',         'can' => 'copiloto.mcp.usage.all'],
        ['label' => 'Caixa de entrada', 'href' => '/project-mgmt/inbox',    'icon' => 'Bell',          'can' => 'copiloto.mcp.usage.all'],
        ['label' => 'Board',            'href' => '/project-mgmt/board',    'icon' => 'KanbanSquare',  'can' => 'copiloto.mcp.usage.all'],
        ['label' => 'Backlog',          'href' => '/project-mgmt/backlog',  'icon' => 'List',          'can' => 'copiloto.mcp.usage.all'],
        ['label' => 'Roadmap',          'href' => '/project-mgmt/roadmap',  'icon' => 'CalendarRange', 'can' => 'copiloto.mcp.usage.all'],
        ['label' => 'Activity',         'href' => '/project-mgmt/activity', 'icon' => 'Activity',      'can' => 'copiloto.mcp.usage.all'],
        ['label' => 'Burndown',         'href' => '/project-mgmt/burndown', 'icon' => 'TrendingDown',  'can' => 'copiloto.mcp.usage.all'],
    ],
];
