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
        ['label' => 'Board',    'href' => '/project-mgmt/board', 'icon' => 'KanbanSquare', 'can' => 'copiloto.mcp.usage.all'],
        // Próximas (US-TR-202/204/203/205/206) — habilitar conforme PRs landam:
        // ['label' => 'Backlog',  'href' => '/project-mgmt/backlog',  'icon' => 'List',           'can' => 'copiloto.mcp.usage.all'],
        // ['label' => 'My Work',  'href' => '/project-mgmt/my-work',  'icon' => 'CheckSquare',    'can' => 'copiloto.mcp.usage.all'],
        // ['label' => 'Roadmap',  'href' => '/project-mgmt/roadmap',  'icon' => 'CalendarRange',  'can' => 'copiloto.mcp.usage.all'],
        // ['label' => 'Inbox',    'href' => '/project-mgmt/inbox',    'icon' => 'Inbox',          'can' => 'copiloto.mcp.usage.all'],
        // ['label' => 'Triage',   'href' => '/project-mgmt/triage',   'icon' => 'AlertTriangle',  'can' => 'copiloto.mcp.usage.all'],
    ],
];
