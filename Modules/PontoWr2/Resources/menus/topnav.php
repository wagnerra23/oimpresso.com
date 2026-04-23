<?php

/**
 * TopNav declarativo do PontoWr2 (ADR arq/0009 DocVault).
 *
 * Espelho React do padrão Blade `Resources/views/layouts/nav.blade.php`
 * que outros módulos usam. Este arquivo é lido por LegacyMenuAdapter::buildTopNavs()
 * e exposto em `shell.topnavs.PontoWr2` via Inertia.
 *
 * Regras:
 * - Ordem do array = ordem na barra horizontal (esquerda → direita)
 * - `can` opcional: se setado, Spatie filtra item por permissão do user
 * - `icon` usa nome Lucide (ver resources/js/Components/Icon.tsx)
 * - `href` é path relativo (/ponto/...), LegacyMenuAdapter detecta se é Inertia
 */

return [
    'label' => 'Ponto WR2',
    'icon'  => 'Clock',
    'items' => [
        [
            'label' => 'Dashboard',
            'href'  => '/ponto',
            'icon'  => 'LayoutDashboard',
            'can'   => 'ponto.access',
        ],
        [
            'label' => 'Espelho',
            'href'  => '/ponto/espelho',
            'icon'  => 'ClipboardList',
            'can'   => 'ponto.access',
        ],
        [
            'label' => 'Intercorrências',
            'href'  => '/ponto/intercorrencias',
            'icon'  => 'AlertTriangle',
            'can'   => 'ponto.access',
        ],
        [
            'label' => 'Aprovações',
            'href'  => '/ponto/aprovacoes',
            'icon'  => 'CheckCheck',
            'can'   => 'ponto.aprovacoes.manage',
        ],
        [
            'label' => 'Banco de Horas',
            'href'  => '/ponto/banco-horas',
            'icon'  => 'PiggyBank',
            'can'   => 'ponto.access',
        ],
        [
            'label' => 'Colaboradores',
            'href'  => '/ponto/colaboradores',
            'icon'  => 'Users',
            'can'   => 'ponto.colaboradores.manage',
        ],
        [
            'label' => 'Escalas',
            'href'  => '/ponto/escalas',
            'icon'  => 'CalendarDays',
            'can'   => 'ponto.configuracoes.manage',
        ],
        [
            'label' => 'Importações',
            'href'  => '/ponto/importacoes',
            'icon'  => 'FileUp',
            'can'   => 'ponto.access',
        ],
        [
            'label' => 'Relatórios',
            'href'  => '/ponto/relatorios',
            'icon'  => 'BarChart3',
            'can'   => 'ponto.relatorios.view',
        ],
        [
            'label' => 'Configurações',
            'href'  => '/ponto/configuracoes',
            'icon'  => 'Settings',
            'can'   => 'ponto.configuracoes.manage',
        ],
    ],
];
