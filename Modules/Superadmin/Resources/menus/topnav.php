<?php

/*
|--------------------------------------------------------------------------
| TopNav declarativo — Superadmin
|--------------------------------------------------------------------------
| Lido pelo LegacyMenuAdapter::buildTopNavs() e exposto em
| `shell.topnavs.Superadmin` via Inertia.
|
| Permissão: `superadmin` (middleware Spatie já ativo nas rotas).
*/

return [
    'label' => 'Superadmin',
    'icon'  => 'ShieldCheck',
    'items' => [
        ['label' => 'Usuário 360°', 'href' => '/superadmin/usuarios', 'icon' => 'UserSearch', 'can' => 'superadmin'],
        ['label' => 'Empresas',     'href' => '/superadmin/business', 'icon' => 'Building2',  'can' => 'superadmin'],
        ['label' => 'Pacotes',      'href' => '/superadmin/packages', 'icon' => 'Package',    'can' => 'superadmin'],
        ['label' => 'Comunicador',  'href' => '/superadmin/communicator', 'icon' => 'MessagesSquare', 'can' => 'superadmin'],
        ['label' => 'Configurações','href' => '/superadmin/settings', 'icon' => 'Settings',   'can' => 'superadmin'],
        ['label' => 'Instalação',   'href' => '/superadmin/install',  'icon' => 'Wrench',     'can' => 'superadmin'],
    ],
];
