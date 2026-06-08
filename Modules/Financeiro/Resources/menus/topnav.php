<?php

/**
 * TopNav declarativo do Financeiro.
 *
 * Lido pelo LegacyMenuAdapter::buildTopNavs() e exposto em
 * `shell.topnavs.Financeiro` via Inertia. Renderizado como navbar
 * horizontal inline com o breadcrumb do AppShellV2 quando o usuario
 * esta numa tela /financeiro/* (auto-detect via useAutoModuleNav).
 *
 * Itens refletem as 7 sub-rotas atuais do Module Financeiro pos
 * remocao do submenu lateral (PR #565, Wagner 2026-05-11):
 * "remover submenu, abrir sempre financeiro unificado primeiro".
 *
 * Conciliacao NAO esta na lista — a rota /financeiro/extrato/{id}
 * requer um contaBancariaId, sem rota indice. Quando criarmos
 * /financeiro/conciliacao (indice listando contas), adicionar aqui.
 */

return [
    'label' => 'Financeiro',
    'icon'  => 'TrendingUp',
    'items' => [
        ['label' => 'Visão unificada',  'href' => '/financeiro/unificado',         'icon' => 'LayoutDashboard'],
        ['label' => 'Fluxo de caixa',   'href' => '/financeiro/fluxo',             'icon' => 'BarChart3'],
        ['label' => 'A receber',        'href' => '/financeiro/contas-receber',    'icon' => 'TrendingUp'],
        ['label' => 'A pagar',          'href' => '/financeiro/contas-pagar',      'icon' => 'TrendingDown'],
        ['label' => 'Boletos',          'href' => '/financeiro/boletos',           'icon' => 'ClipboardList'],
        ['label' => 'Contas bancárias', 'href' => '/financeiro/contas-bancarias',  'icon' => 'Building2'],
        ['label' => 'Categorias',       'href' => '/financeiro/categorias',        'icon' => 'List'],
        ['label' => 'Relatórios',       'href' => '/financeiro/relatorios',        'icon' => 'Activity'],
    ],
];
