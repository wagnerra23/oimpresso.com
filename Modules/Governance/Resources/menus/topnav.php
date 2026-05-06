<?php

/**
 * Topnav declarativo do Governance — pattern oimpresso (ADR 0011).
 *
 * Aparece sob grupo SIDEBAR_GROUPS = "GOVERNANÇA" (ver resources/js/Components/cockpit/Sidebar.tsx).
 */

return [
    [
        'label' => 'Governança',
        'route' => 'governance.admin.dashboard',
        'icon'  => 'shield-check',
        'permission' => 'governance.dashboard.view',
        'order' => 1,
    ],
];
