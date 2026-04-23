<?php

/**
 * TopNav declarativo do Officeimpresso (ADR 0011 DocVault).
 *
 * Fonte INDEPENDENTE da sidebar (que vem de DataController::modifyAdminMenu)
 * e do Blade `Resources/views/layouts/nav.blade.php`. Usado por React/Inertia
 * pages que extendam o layout shell moderno — vem via shell.topnavs.Officeimpresso
 * lido por LegacyMenuAdapter::buildTopNavs().
 *
 * Convencoes:
 * - Ordem do array = ordem na barra horizontal (esquerda → direita)
 * - `can` opcional: se setado, filtra por permissao do user
 * - `icon` usa nome Lucide (resources/js/Components/Icon.tsx)
 * - `href` e path relativo (/officeimpresso/...)
 */

return [
    'label' => 'Office Impresso',
    'icon'  => 'Plug',
    'items' => [
        ['label' => 'Empresas Licenciadas', 'href' => '/officeimpresso/businessall',        'icon' => 'Building2',      'can' => 'superadmin'],
        ['label' => 'Computadores',         'href' => '/officeimpresso/computadores',       'icon' => 'Monitor'],
        ['label' => 'Licenças',             'href' => '/officeimpresso/licenca_computador', 'icon' => 'KeyRound'],
        ['label' => 'Clientes',             'href' => '/officeimpresso/client',             'icon' => 'UserCog',        'can' => 'superadmin'],
        ['label' => 'Log de Acesso',        'href' => '/officeimpresso/licenca_log',        'icon' => 'ClipboardList'],
        ['label' => 'Documentação',         'href' => '/officeimpresso/docs',               'icon' => 'BookOpen'],
    ],
];
