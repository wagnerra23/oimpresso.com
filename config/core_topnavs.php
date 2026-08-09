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
        'items' => [
            // Fusão 2026-06-16 (hub único): abas próprias da Forja (/forja/*) +
            // telas TeamMcp absorvidas (/team-mcp/*). Como o topnav antigo do TeamMcp
            // (Resources/menus/topnav.php) foi removido, este é o ÚNICO que casa
            // /team-mcp/* no useAutoModuleNav — então a nav é a mesma em todo o hub.
            // badge=3 ESTÁTICO (sementes FORJA); contador vivo via `triagemCount` na aba.
            // ── 3 GRUPOS (2026-08-08) ───────────────────────────────────────
            // A ORDEM aqui espelha a dos grupos do `FORJA_TABS` (ForjaHub):
            // Trabalho · Esteira · Histórico. Este config alimenta o SHELL
            // (`AppShellV2`), aquele alimenta a FAIXA do hub — são duas superfícies
            // distintas, e a lápide §5 2026-08-06 é justamente sobre elas divergirem
            // em silêncio (foi assim que o Gantt abriu sem faixa). O agrupamento
            // VISUAL mora só no hub; aqui a lista segue chapada de propósito, porque
            // o shell não desenha grupo — mas a ordem tem que bater.

            // — Trabalho: o fluxo do issue —
            // Aprovações: superfície do funil de admissão (ADR 0368), a fila de
            // `mcp_tasks.status = pending_approval`. Primeiro porque é o que está
            // parado esperando decisão. SEM badge estático: contagem é prop deferida.
            ['label' => 'Aprovações',  'href' => '/forja/aprovacoes',     'icon' => 'Gavel',         'can' => 'jana.mcp.usage.all'],
            ['label' => 'Triagem',     'href' => '/forja',                'icon' => 'Inbox',         'can' => 'jana.mcp.usage.all', 'badge' => 3],
            ['label' => 'Backlog',     'href' => '/forja/backlog',        'icon' => 'List',          'can' => 'jana.mcp.usage.all'],
            ['label' => 'Quadro',      'href' => '/forja/quadro',         'icon' => 'KanbanSquare',  'can' => 'jana.mcp.usage.all'],
            // Roadmap (Gantt) — aba adicionada 2026-08-06 ([W]: "quero que registre").
            // A tela chegou da Jana no #5310 (ADR 0366 §D-C item 3) com o ghost já
            // registrado no DataController da Forja, MAS abriu sem faixa nenhuma em
            // produção: são DUAS superfícies de navegação e o ghost não alimenta esta.
            // O `can` é o da rota real (`RoadmapGanttController@index`), não o genérico
            // das abas vizinhas — aba que aparece e dá 403 é pior que aba ausente.
            ['label' => 'Roadmap (Gantt)', 'href' => '/forja/roadmap-gantt', 'icon' => 'CalendarRange', 'can' => 'jana.mcp.tasks.read'],
            ['label' => 'Tarefas',     'href' => '/team-mcp/tasks',       'icon' => 'ClipboardList', 'can' => 'jana.mcp.usage.all'],

            // — Esteira: a operação da máquina —
            // Handoffs antes do MCP: dado vivo antes da vitrine do contrato.
            ['label' => 'Handoffs',    'href' => '/forja/handoffs',       'icon' => 'Workflow',      'can' => 'jana.mcp.usage.all'],
            ['label' => 'MCP',         'href' => '/forja/mcp',            'icon' => 'ShieldCheck',   'can' => 'jana.mcp.usage.all'],
            ['label' => 'Equipe',      'href' => '/team-mcp/team',        'icon' => 'Users',         'can' => 'jana.mcp.usage.all'],
            ['label' => 'Saúde',       'href' => '/team-mcp/scorecard',   'icon' => 'Activity',      'can' => 'jana.mcp.usage.all'],

            // — Histórico: o registro —
            ['label' => 'Changelog',   'href' => '/forja/changelog',      'icon' => 'GitBranch',     'can' => 'jana.mcp.usage.all'],
            ['label' => 'CC Sessions', 'href' => '/team-mcp/cc-sessions', 'icon' => 'MessageSquare', 'can' => 'jana.cc.read.team'],
        ],
    ],
];
