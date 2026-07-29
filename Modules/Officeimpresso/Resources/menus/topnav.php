<?php

/**
 * TopNav declarativo do Officeimpresso (ADR 0011 MemCofre).
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
        // `can` alinhado ao gate real dos controllers (abort_unless). Antes,
        // Computadores/Licenças/Log não declaravam `can` nenhum e apareciam pra
        // qualquer usuário no shell Inertia. Superadmin continua vendo tudo via
        // bypass do Gate::before.
        ['label' => 'Empresas Licenciadas', 'href' => '/officeimpresso/businessall',        'icon' => 'Building2',      'can' => 'officeimpresso.access'],
        ['label' => 'Computadores',         'href' => '/officeimpresso/computadores',       'icon' => 'Monitor',        'can' => 'officeimpresso.access'],
        ['label' => 'Licenças',             'href' => '/officeimpresso/licenca_computador', 'icon' => 'KeyRound',       'can' => 'officeimpresso.access'],
        ['label' => 'Clientes',             'href' => '/officeimpresso/client',             'icon' => 'UserCog',        'can' => 'officeimpresso.clientes.liberar'],
        ['label' => 'Log de Acesso',        'href' => '/officeimpresso/licenca_log',        'icon' => 'ClipboardList',  'can' => 'officeimpresso.access'],
    ],
];
