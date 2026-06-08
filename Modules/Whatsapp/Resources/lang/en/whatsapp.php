<?php

return [
    'module_name' => 'Whatsapp',
    'conversations' => 'Conversations',
    'templates' => 'Templates',
    'settings' => 'Settings',

    'driver' => [
        'zapi' => 'Z-API (recommended quick start — 5 min onboarding)',
        'meta_cloud' => 'Meta Cloud API (official — 1-3 days verification)',
    ],

    'risk_warning' => [
        'zapi' => 'Unofficial provider (Whatsapp Web). Risk of Meta block exists. Meta Cloud configured as mandatory fallback.',
    ],

    'lgpd_acknowledgment' => 'I am aware Z-API is an unofficial provider based on Whatsapp Web and that there is risk of Meta blocking. I have set up Meta Cloud as fallback to mitigate service interruption.',

    'driver_health' => [
        'healthy' => 'Connected',
        'degraded' => 'Degraded — fallback active',
        'disconnected' => 'Disconnected',
        'banned' => 'Blocked by Meta',
        'never_checked' => 'Awaiting first check',
    ],
];
