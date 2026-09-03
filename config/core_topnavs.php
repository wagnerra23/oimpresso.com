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
    // mora em Modules/Forja (absorção, não módulo novo).
    // Ref: memory/requisitos/TeamMcp/forja-cockpit-visual-comparison.md
    'Forja' => [
        'label' => 'Forja',
        'icon'  => 'Hammer',
        // 2026-09-02 · PARIDADE §11 Onda 2 — 9 → 6, a lista do protótipo (forja-page.jsx),
        // na MESMA ordem de FORJA_TABS (ForjaHub.tsx): UC-FORJA-14 cruza as duas.
        // Saíram do topo (rotas vivas): Triagem, Handoffs, Equipe, CC Sessions.
        'items' => [
            ['label' => 'Aprovações', 'href' => '/forja/aprovacoes',   'icon' => 'Gavel',       'can' => 'jana.mcp.usage.all'],
            ['label' => 'Trabalho',   'href' => '/forja/trabalho',     'icon' => 'ListChecks',  'can' => 'jana.mcp.usage.all'],
            // Onda 7: era /team-mcp/scorecard (desvio provisório). O Scorecard segue vivo
            // como destino do drill "ver →" dentro da view — não mais como destino da aba.
            ['label' => 'Saúde',      'href' => '/forja/saude',        'icon' => 'Activity',    'can' => 'jana.mcp.usage.all'],
            ['label' => 'MCP',        'href' => '/forja/mcp',          'icon' => 'ShieldCheck', 'can' => 'jana.mcp.usage.all'],
            ['label' => 'Changelog',  'href' => '/forja/changelog',    'icon' => 'GitBranch',   'can' => 'jana.mcp.usage.all'],
            ['label' => 'Integrador', 'href' => '/forja/integrador',   'icon' => 'Plug',        'can' => 'jana.mcp.usage.all'],
        ],
    ],
];
