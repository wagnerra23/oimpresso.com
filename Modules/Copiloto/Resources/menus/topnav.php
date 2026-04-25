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
    'label' => 'Copiloto',
    'icon'  => 'Compass',
    'items' => [
        ['label' => 'Conversar',      'href' => '/copiloto',                'icon' => 'MessageSquare',   'can' => 'copiloto.chat'],
        ['label' => 'Dashboard',      'href' => '/copiloto/dashboard',      'icon' => 'LayoutDashboard', 'can' => 'copiloto.access'],
        ['label' => 'Metas',          'href' => '/copiloto/metas',          'icon' => 'Target',          'can' => 'copiloto.metas.manage'],
        ['label' => 'Alertas',        'href' => '/copiloto/alertas',        'icon' => 'Bell',            'can' => 'copiloto.access'],
        ['label' => 'Plataforma',     'href' => '/copiloto/superadmin/metas', 'icon' => 'Building2',     'can' => 'copiloto.superadmin'],
    ],
];
