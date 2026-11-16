<?php

/*
|--------------------------------------------------------------------------
| TopNav declarativo — Superadmin
|--------------------------------------------------------------------------
| Lido pelo LegacyMenuAdapter::buildTopNavs() e exposto em
| `shell.topnavs.Superadmin` via Inertia.
|
| Permissão: `superadmin` (middleware Spatie já ativo nas rotas).
|
| Auditoria 2026-11-05 (eixo 5 §5): o painel `/superadmin` e as Páginas do site
| (`/superadmin/frontend-pages`) eram telas do grupo sem item aqui. Entraram.
| `/all-subscriptions` NÃO entra: é endpoint DataTables (JSON), não tela.
*/

return [
    'label' => 'Superadmin',
    'icon'  => 'ShieldCheck',
    'items' => [
        ['label' => 'Painel',       'href' => '/superadmin',          'icon' => 'LayoutDashboard', 'can' => 'superadmin'],
        ['label' => 'Usuário 360°', 'href' => '/superadmin/usuarios', 'icon' => 'UserSearch', 'can' => 'superadmin'],
        ['label' => 'Empresas',     'href' => '/superadmin/business', 'icon' => 'Building2',  'can' => 'superadmin'],
        ['label' => 'Assinaturas',  'href' => '/superadmin/superadmin-subscription', 'icon' => 'ReceiptText', 'can' => 'superadmin'],
        ['label' => 'Pacotes',      'href' => '/superadmin/packages', 'icon' => 'Package',    'can' => 'superadmin'],
        ['label' => 'Páginas do site', 'href' => '/superadmin/frontend-pages', 'icon' => 'FileText', 'can' => 'superadmin'],
        ['label' => 'Comunicador',  'href' => '/superadmin/communicator', 'icon' => 'MessagesSquare', 'can' => 'superadmin'],
        ['label' => 'Configurações','href' => '/superadmin/settings', 'icon' => 'Settings',   'can' => 'superadmin'],
        ['label' => 'Instalação',   'href' => '/superadmin/install',  'icon' => 'Wrench',     'can' => 'superadmin'],
    ],
];
