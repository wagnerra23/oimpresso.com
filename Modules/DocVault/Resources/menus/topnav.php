<?php

/**
 * TopNav declarativo do DocVault (ADR arq/0011).
 *
 * Espelho React do nav horizontal. Lido por LegacyMenuAdapter::buildTopNavs()
 * e exposto em `shell.topnavs.DocVault` via Inertia.
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
    'label' => 'DocVault',
    'icon'  => 'FolderOpen',
    'items' => [
        ['label' => 'Dashboard', 'href' => '/docs',         'icon' => 'LayoutDashboard', 'can' => 'docvault.access'],
        ['label' => 'Ingest',    'href' => '/docs/ingest',  'icon' => 'Upload',          'can' => 'docvault.access'],
        ['label' => 'Inbox',     'href' => '/docs/inbox',   'icon' => 'Inbox',           'can' => 'docvault.access'],
        ['label' => 'Memória',   'href' => '/docs/memoria', 'icon' => 'BookOpen',        'can' => 'docvault.access'],
        ['label' => 'Chat',      'href' => '/docs/chat',    'icon' => 'MessageCircle',   'can' => 'docvault.access'],
    ],
];
