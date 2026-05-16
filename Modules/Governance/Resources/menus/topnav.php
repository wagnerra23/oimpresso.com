<?php

/**
 * TopNav declarativo do Governance — pattern oimpresso (ADR 0011).
 *
 * Aparece sob grupo SIDEBAR_GROUPS = "GOVERNANÇA" no AppShellV2.
 * Constituição Art. 8 + Art. 9 operacional.
 *
 * NOTA: skill criar-modulo lembra que LegacyMenuAdapter pode não resolver
 * traduções (NFSe sempre hardcodou PT-BR). Mantemos i18n keys porque
 * TeamMcp usa esse pattern e funciona — se quebrar, hardcodar.
 */

return [
    'label' => 'governance::governance.module_label',
    'icon'  => 'ShieldCheck',
    'items' => [
        ['label' => 'governance::governance.menu.dashboard', 'href' => '/governance',         'icon' => 'LayoutDashboard', 'can' => 'governance.dashboard.view'],
        ['label' => 'governance::governance.menu.policies',  'href' => '/governance/policies', 'icon' => 'Settings',        'can' => 'governance.policies.edit'],
        ['label' => 'governance::governance.menu.audit',     'href' => '/governance/audit',    'icon' => 'Search',          'can' => 'governance.audit.view'],
        ['label' => 'governance::governance.menu.drift',     'href' => '/governance/drift',    'icon' => 'AlertTriangle',   'can' => 'governance.dashboard.view'],
        ['label' => 'Module Grades',                          'href' => '/governance/module-grades', 'icon' => 'Gauge',     'can' => 'governance.dashboard.view'],
    ],
];
