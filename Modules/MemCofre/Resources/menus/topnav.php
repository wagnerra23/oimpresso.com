<?php

/**
 * TopNav declarativo do MemCofre (ADR arq/0011).
 *
 * Espelho React do nav horizontal. Lido por LegacyMenuAdapter::buildTopNavs()
 * e exposto em `shell.topnavs.MemCofre` via Inertia.
 *
 * Fonte INDEPENDENTE da sidebar (que vem de DataController::modifyAdminMenu).
 *
 * Convenções:
 * - Ordem do array = ordem na barra horizontal (esquerda → direita)
 * - `can` opcional: se setado, Spatie filtra item por permissão do user
 * - `icon` usa nome Lucide (resources/js/Components/Icon.tsx)
 * - `href` é path relativo (/docs/...); backend detecta se é Inertia
 */

return [
    'label' => 'Cofre de Memórias',
    'icon'  => 'FolderOpen',
    'items' => [
        ['label' => 'Dashboard', 'href' => '/docs',         'icon' => 'LayoutDashboard', 'can' => 'memcofre.access'],
        ['label' => 'Ingest',    'href' => '/memcofre/ingest',  'icon' => 'Upload',          'can' => 'memcofre.access'],
        ['label' => 'Inbox',     'href' => '/memcofre/inbox',   'icon' => 'Inbox',           'can' => 'memcofre.access'],
        ['label' => 'Memória',   'href' => '/memcofre/memoria', 'icon' => 'BookOpen',        'can' => 'memcofre.access'],
        ['label' => 'Chat',      'href' => '/memcofre/chat',    'icon' => 'MessageCircle',   'can' => 'memcofre.access'],
    ],
];
