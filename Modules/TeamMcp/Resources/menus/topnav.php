<?php

/**
 * TopNav declarativo do TeamMcp.
 *
 * Lido pelo LegacyMenuAdapter::buildTopNavs() e exposto em
 * `shell.topnavs.TeamMcp` via Inertia.
 *
 * Permissions herdadas do Copiloto (não renomeadas — risco de quebrar usuários).
 */

return [
    'label' => 'teammcp::teammcp.module_label',
    'icon'  => 'Users',
    'items' => [
        ['label' => 'teammcp::teammcp.menu.team',         'href' => '/team-mcp/team',         'icon' => 'Users',        'can' => 'copiloto.mcp.usage.all'],
        ['label' => 'teammcp::teammcp.menu.tasks',        'href' => '/team-mcp/tasks',        'icon' => 'LayoutKanban', 'can' => 'copiloto.mcp.usage.all'],
        ['label' => 'teammcp::teammcp.menu.cc_sessions',  'href' => '/team-mcp/cc-sessions',  'icon' => 'Code2',        'can' => 'copiloto.cc.read.team'],
    ],
];
