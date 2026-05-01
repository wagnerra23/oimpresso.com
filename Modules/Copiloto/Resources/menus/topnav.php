<?php

/**
 * TopNav declarativo do Copiloto.
 *
 * Espelho React do padrão usado pelos demais módulos. Lido pelo
 * LegacyMenuAdapter::buildTopNavs() e exposto em
 * `shell.topnavs.Copiloto` via Inertia.
 *
 * Convenções:
 * - Ordem do array = ordem na barra horizontal (esquerda → direita)
 * - `can` opcional: se setado, Spatie filtra item por permissão do user
 * - `icon` usa nome Lucide
 * - `href` é path relativo (/copiloto/...)
 */

return [
    'label' => 'copiloto::copiloto.module_label',
    'icon'  => 'Compass',
    'items' => [
        ['label' => 'copiloto::copiloto.menu.conversar',  'href' => '/copiloto',                  'icon' => 'MessageSquare',   'can' => 'copiloto.chat'],
        ['label' => 'copiloto::copiloto.menu.dashboard',  'href' => '/copiloto/dashboard',        'icon' => 'LayoutDashboard', 'can' => 'copiloto.access'],
        ['label' => 'copiloto::copiloto.menu.metas',      'href' => '/copiloto/metas',            'icon' => 'Target',          'can' => 'copiloto.metas.manage'],
        ['label' => 'copiloto::copiloto.menu.alertas',    'href' => '/copiloto/alertas',          'icon' => 'Bell',            'can' => 'copiloto.access'],
        ['label' => 'Governança MCP',                     'href' => '/copiloto/admin/governanca', 'icon' => 'ShieldCheck',     'can' => 'copiloto.mcp.usage.all'],
        ['label' => 'Team Admin',                         'href' => '/copiloto/admin/team',       'icon' => 'Users',           'can' => 'copiloto.mcp.usage.all'],
        ['label' => 'KB MCP (memória)',                   'href' => '/copiloto/admin/memoria',    'icon' => 'BookOpen',        'can' => 'copiloto.mcp.memory.manage'],
        ['label' => 'CC do time',                         'href' => '/copiloto/admin/cc-sessions','icon' => 'Code2',           'can' => 'copiloto.cc.read.team'],
        ['label' => 'Qualidade IA',                       'href' => '/copiloto/admin/qualidade',  'icon' => 'TrendingUp',      'can' => 'copiloto.mcp.usage.all'],
        ['label' => 'Task Board',                         'href' => '/copiloto/admin/tasks',       'icon' => 'LayoutKanban',     'can' => 'copiloto.mcp.usage.all'],
        ['label' => 'copiloto::copiloto.menu.plataforma', 'href' => '/copiloto/superadmin/metas', 'icon' => 'Building2',       'can' => 'copiloto.superadmin'],
    ],
];
