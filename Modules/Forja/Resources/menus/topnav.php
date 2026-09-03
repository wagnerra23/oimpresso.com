<?php

/**
 * TopNav declarativo do Forja.
 *
 * Lido pelo LegacyMenuAdapter::buildTopNavs() e exposto em
 * `shell.topnavs.Forja` via Inertia.
 *
 * Permissions herdadas do Copiloto (nao renomeadas — mesmo padrao TeamMcp).
 *
 * ONDA 11 (2026-09-02) — os 8 itens antigos apontavam TODOS pra /project-mgmt/*,
 * e as 7 telas por tras deles foram revogadas (ADR 0367 D1 + PARIDADE §11). Este
 * arquivo era a superficie de navegacao VIVA que ainda as alcancava: o §11 listava
 * rotas, testes e SCOPE, mas nao ele.
 *
 * A lista abaixo espelha os 6 destinos que a Onda 2 fixou no header do cockpit.
 * ⚠️ FONTE CANONICA da ordem e dos rotulos = `FORJA_TABS` em
 * Modules/Forja/Resources/js/Pages/team-mcp/Forja/_components/ForjaHub.tsx.
 * Nao ha como derivar PHP de TS aqui, entao esta e uma SEGUNDA declaracao: se
 * divergir, quem manda e o ForjaHub — conferir os dois ao mexer em qualquer um.
 */

return [
    'label' => 'Forja',
    'icon'  => 'KanbanSquare',
    'items' => [
        // Grupo "Trabalho"
        ['label' => 'Aprovacoes', 'href' => '/forja/aprovacoes',  'icon' => 'Gavel',       'can' => 'jana.mcp.usage.all'],
        ['label' => 'Trabalho',   'href' => '/forja/trabalho',    'icon' => 'ListChecks',  'can' => 'jana.mcp.usage.all'],
        // Grupo "Esteira"
        ['label' => 'Saude',      'href' => '/team-mcp/scorecard', 'icon' => 'Activity',   'can' => 'jana.mcp.usage.all'],
        ['label' => 'MCP',        'href' => '/forja/mcp',         'icon' => 'ShieldCheck', 'can' => 'jana.mcp.usage.all'],
        // Grupo "Historico"
        ['label' => 'Changelog',  'href' => '/forja/changelog',   'icon' => 'History',     'can' => 'jana.mcp.usage.all'],
        ['label' => 'Integrador', 'href' => '/forja/integrador',  'icon' => 'Plug',        'can' => 'jana.mcp.usage.all'],
    ],
];
