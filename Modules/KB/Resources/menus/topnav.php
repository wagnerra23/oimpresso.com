<?php

/**
 * TopNav declarativo do KB (Knowledge Base).
 *
 * Lido pelo LegacyMenuAdapter::buildTopNavs() e exposto em
 * `shell.topnavs.KB` via Inertia.
 *
 * Convenções:
 * - Ordem do array = ordem na barra horizontal (esquerda → direita)
 * - `can` opcional: se setado, Spatie filtra item por permissão do user
 * - `icon` usa nome Lucide
 * - `href` é path relativo (/kb/...)
 *
 * Permissão Spatie real continua sendo `copiloto.mcp.memory.manage` até o
 * PR de rename (dívida técnica registrada no DataController).
 */

return [
    'label' => 'kb::kb.module_label',
    'icon'  => 'BookOpen',
    'items' => [
        ['label' => 'kb::kb.menu.adrs',         'href' => '/kb?type=adr',        'icon' => 'FileText',  'can' => 'copiloto.mcp.memory.manage'],
        ['label' => 'kb::kb.menu.sessions',     'href' => '/kb?type=session',    'icon' => 'History',   'can' => 'copiloto.mcp.memory.manage'],
        ['label' => 'kb::kb.menu.runbooks',     'href' => '/kb?type=runbook',    'icon' => 'BookOpen',  'can' => 'copiloto.mcp.memory.manage'],
        ['label' => 'kb::kb.menu.comparativos', 'href' => '/kb?type=comparativo','icon' => 'GitCompare','can' => 'copiloto.mcp.memory.manage'],
    ],
];
