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
        ['label' => 'copiloto::copiloto.menu.conversar',  'href' => '/jana',                  'icon' => 'MessageSquare',   'can' => 'jana.chat'],
        ['label' => 'copiloto::copiloto.menu.dashboard',  'href' => '/jana/dashboard',        'icon' => 'LayoutDashboard', 'can' => 'jana.access'],
        ['label' => 'copiloto::copiloto.menu.metas',      'href' => '/jana/metas',            'icon' => 'Target',          'can' => 'jana.metas.manage'],
        ['label' => 'copiloto::copiloto.menu.alertas',    'href' => '/jana/alertas',          'icon' => 'Bell',            'can' => 'jana.access'],
        ['label' => 'Governança MCP',                     'href' => '/jana/admin/governanca', 'icon' => 'ShieldCheck',     'can' => 'jana.mcp.usage.all'],
        // KB foi splitado pro módulo Modules/KB em 2026-05-03 (Etapa 2 modularização).
        // Link cross-module aqui pra continuidade visual.
        ['label' => 'KB →',                               'href' => '/kb',                        'icon' => 'BookOpen',        'can' => 'jana.mcp.memory.manage'],
        ['label' => 'Qualidade IA',                       'href' => '/jana/admin/qualidade',  'icon' => 'TrendingUp',      'can' => 'jana.mcp.usage.all'],
        // Team MCP saiu do Copiloto e virou módulo próprio (split TeamMcp).
        // Mantém entry como atalho cross-module pra Wagner.
        ['label' => 'Team MCP →',                         'href' => '/team-mcp/team',             'icon' => 'Users',           'can' => 'jana.mcp.usage.all'],
        ['label' => 'copiloto::copiloto.menu.plataforma', 'href' => '/jana/superadmin/metas', 'icon' => 'Building2',       'can' => 'jana.superadmin'],
    ],
];
