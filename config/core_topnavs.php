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
                'label' => 'Lista de vendas',
                'href'  => '/sells',
                'icon'  => 'List',
                'can'   => 'sell.view_own_sell_only',
            ],
            [
                'label' => 'POS',
                'href'  => '/pos/create',
                'icon'  => 'CreditCard',
                'can'   => 'sell.create',
            ],
            [
                'label' => 'Orçamentos',
                'href'  => '/sells/quotations',
                'icon'  => 'FileText',
                'can'   => 'quotation.view_own',
            ],
        ],
    ],

    // MWART migracao-blade-react: topnav módulo Compras (ADR 0141 piloto).
    // Match useAutoModuleNav() ocorre em qualquer item cujo URL root bate com o atual,
    // então listar todas as sub-rotas garante topnav presente em todas as 5 telas.
    'Purchase' => [
        'label' => 'Compras',
        'icon'  => 'ShoppingCart',
        'items' => [
            [
                'label' => 'Lista de compras',
                'href'  => '/purchases',
                'icon'  => 'List',
                'can'   => 'purchase.view',
            ],
            [
                'label' => 'Nova compra',
                'href'  => '/purchases/create',
                'icon'  => 'Plus',
                'can'   => 'purchase.create',
            ],
            [
                'label' => 'Devoluções',
                'href'  => '/purchase-return',
                'icon'  => 'Undo2',
                'can'   => 'purchase.update',
            ],
            [
                'label' => 'Pedidos de compra',
                'href'  => '/purchase-order',
                'icon'  => 'ShoppingBag',
                'can'   => 'purchase.create',
            ],
            [
                'label' => 'Requisições',
                'href'  => '/purchase-requisition',
                'icon'  => 'FileEdit',
                'can'   => 'purchase.create',
            ],
        ],
    ],

    // Forja — cockpit do cowork loop (Onda Forja PR-A). Raiz /forja é segmento
    // PRÓPRIO de propósito: useAutoModuleNav() casa o topnav pelo 1º segmento da
    // URL, então /team-mcp/* (hub Equipe) e /forja/* não se sobrepõem. Controller
    // mora em Modules/TeamMcp (absorção, não módulo novo).
    // Ref: memory/requisitos/TeamMcp/forja-cockpit-visual-comparison.md
    'Forja' => [
        'label' => 'Forja',
        'icon'  => 'Hammer',
        'items' => [
            // badge=3 = nº de propostas-semente da Triagem (FORJA-150/151/152). ESTÁTICO:
            // core_topnavs.php é config carregada no boot, não tem dado por-request — o
            // contador VIVO da fila chega via prop deferida `triagemCount` na própria aba.
            // Quando o shell suportar badge dinâmico no topnav, trocar por esse contador.
            ['label' => 'Triagem',   'href' => '/forja',           'icon' => 'Inbox',        'can' => 'copiloto.mcp.usage.all', 'badge' => 3],
            ['label' => 'Backlog',   'href' => '/forja/backlog',   'icon' => 'List',         'can' => 'copiloto.mcp.usage.all'],
            ['label' => 'Quadro',    'href' => '/forja/quadro',    'icon' => 'LayoutKanban', 'can' => 'copiloto.mcp.usage.all'],
            ['label' => 'Changelog', 'href' => '/forja/changelog', 'icon' => 'History',      'can' => 'copiloto.mcp.usage.all'],
            ['label' => 'MCP',       'href' => '/forja/mcp',       'icon' => 'Plug',         'can' => 'copiloto.mcp.usage.all'],
            ['label' => 'Saúde',     'href' => '/forja/saude',     'icon' => 'Activity',     'can' => 'copiloto.mcp.usage.all'],
        ],
    ],
];
