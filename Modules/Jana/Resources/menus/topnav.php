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
 * - `href` é path relativo (/ia/... — canon ADR 0180 sidebar v3 IA topo)
 */

return [
    'label' => 'copiloto::copiloto.module_label',
    'icon'  => 'Compass',
    'items' => [
        ['label' => 'copiloto::copiloto.menu.conversar',  'href' => '/ia',                  'icon' => 'MessageSquare',   'can' => 'jana.chat'],
        ['label' => 'copiloto::copiloto.menu.dashboard',  'href' => '/ia/dashboard',        'icon' => 'LayoutDashboard', 'can' => 'jana.access'],
        ['label' => 'copiloto::copiloto.menu.metas',      'href' => '/ia/metas',            'icon' => 'Target',          'can' => 'jana.metas.manage'],
        // Wagner 2026-05-25: /ia/alertas REMOVIDO do topnav legacy — tela é STUB
        // ("spec-ready ver US-COPI-060"). Reativar quando US-COPI-060 entregar.
        // ['label' => 'copiloto::copiloto.menu.alertas',    'href' => '/ia/alertas',          'icon' => 'Bell',            'can' => 'jana.access'],
        ['label' => 'Governança MCP',                     'href' => '/ia/admin/governanca', 'icon' => 'ShieldCheck',     'can' => 'jana.mcp.usage.all'],
        // KB foi splitado pro módulo Modules/KB em 2026-05-03 (Etapa 2 modularização).
        // Link cross-module aqui pra continuidade visual.
        ['label' => 'KB →',                               'href' => '/kb',                      'icon' => 'BookOpen',        'can' => 'jana.mcp.memory.manage'],
        ['label' => 'Qualidade IA',                       'href' => '/ia/admin/qualidade',  'icon' => 'TrendingUp',      'can' => 'jana.mcp.usage.all'],
        // Fusão Forja↔TeamMcp (2026-06-16): o cross-link "Team MCP →" foi REMOVIDO
        // daqui porque o item /team-mcp/team roubava o match de /team-mcp/* no
        // useAutoModuleNav (este topnav vem antes da Forja no Object.values),
        // fazendo as telas absorvidas mostrarem o nav do Copiloto em vez do hub
        // Forja. Agora o hub Forja (core_topnavs['Forja']) é dono de /team-mcp/*.
        ['label' => 'copiloto::copiloto.menu.plataforma', 'href' => '/ia/superadmin/metas', 'icon' => 'Building2',       'can' => 'jana.superadmin'],
    ],
];
