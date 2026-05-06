<?php

/**
 * TopNav declarativo do Governance — pattern oimpresso (ADR 0011).
 *
 * Aparece sob grupo SIDEBAR_GROUPS = "GOVERNANÇA" no AppShellV2.
 * Constituição Art. 8 + Art. 9 operacional.
 */

return [
    'label' => 'Governança',
    'icon'  => 'ShieldCheck',
    'items' => [
        ['label' => 'Painel',         'href' => '/governance',         'icon' => 'LayoutDashboard', 'can' => 'governance.dashboard.view'],
        ['label' => 'Policies',       'href' => '/governance/policies', 'icon' => 'Settings',        'can' => 'governance.policies.edit'],
        ['label' => 'Audit log',      'href' => '/governance/audit',    'icon' => 'Search',          'can' => 'governance.audit.view'],
        ['label' => 'Drift alerts',   'href' => '/governance/drift',    'icon' => 'AlertTriangle',   'can' => 'governance.dashboard.view'],
    ],
];
