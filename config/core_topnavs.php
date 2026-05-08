<?php

/**
 * Topnavs de módulos CORE (UltimatePOS legado, app/Http/Controllers/).
 *
 * Módulos nWidart (Modules/<Nome>/) declaram topnav em
 * Resources/menus/topnav.php (LegacyMenuAdapter::buildTopNavs varre).
 *
 * Módulos core (Sells, Contacts, Products, Expenses, etc) não estão em
 * Modules/, então este arquivo serve como fonte equivalente.
 *
 * Shape: igual aos topnav.php dos módulos nWidart.
 *   '<NomeModuleKey>' => [
 *       'label' => 'Label visível',
 *       'icon'  => 'IconLucideName',
 *       'items' => [
 *           ['label' => 'Item', 'href' => '/rota', 'icon' => 'Icon', 'can' => 'permissao.spatie'],
 *           ...
 *       ],
 *   ]
 *
 * useAutoModuleNav() detecta automático por match de root da URL.
 *
 * Refs:
 *   - ADR 0107 (gap topnav módulo identificado em sells-create-visual-comparison)
 *   - LegacyMenuAdapter::buildTopNavs() — lê este arquivo + módulos nWidart
 *   - resources/js/Hooks/usePageProps.ts useAutoModuleNav
 */

return [
    'Sells' => [
        'label' => 'Vendas',
        'icon'  => 'ShoppingCart',
        'items' => [
            [
                'label' => 'Adicionar venda',
                'href'  => '/sells/create',
                'icon'  => 'Plus',
                'can'   => 'sell.create',
            ],
            [
                'label' => 'POS rápido',
                'href'  => '/pos/create',
                'icon'  => 'CreditCard',
                'can'   => 'sell.create',
            ],
            [
                'label' => 'Lista de vendas',
                'href'  => '/sells',
                'icon'  => 'List',
                'can'   => 'sell.view_own_sell_only',
            ],
            [
                'label' => 'Cotações',
                'href'  => '/sells?status=quotation',
                'icon'  => 'FileText',
                'can'   => 'sell.create',
            ],
            [
                'label' => 'Rascunhos',
                'href'  => '/sells?status=draft',
                'icon'  => 'FileEdit',
                'can'   => 'sell.create',
            ],
        ],
    ],
];
