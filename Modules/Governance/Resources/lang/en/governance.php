<?php

return [
    'module_label'       => 'Governance',

    'menu' => [
        'dashboard' => 'Dashboard',
        'policies'  => 'Policies',
        'audit'     => 'Audit log',
        'drift'     => 'Drift alerts',
    ],

    'governance'         => 'Governance',
    'dashboard'          => 'Governance Dashboard',
    'pending_adrs'       => 'Pending ADRs',
    'active_policies'    => 'Active policies',
    'audit_highlights'   => 'Audit highlights',
    'drift_alerts'       => 'Drift alerts',
    'actors'             => 'Registered actors',
    'modules'            => 'Modules with SCOPE.md',
    'compliance_score'   => 'Constitution compliance',
    'actiongate'         => 'ActionGate',
    'mode_warn'          => 'Warn mode (does not block)',
    'mode_strict'        => 'Strict mode (blocks)',
    'mode_off'           => 'Off',

    'permissions' => [
        'dashboard_view' => 'View Governance dashboard',
        'policies_edit'  => 'Edit policies (mcp_governance_rules)',
        'audit_view'     => 'View audit log',
    ],
];
