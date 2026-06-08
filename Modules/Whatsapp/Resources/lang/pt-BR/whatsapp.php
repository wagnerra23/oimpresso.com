<?php

return [
    'module_name' => 'Whatsapp',
    'conversations' => 'Conversas',
    'templates' => 'Templates',
    'settings' => 'Configurações',

    'driver' => [
        'zapi' => 'Z-API (recomendado para começar — onboarding 5 min)',
        'meta_cloud' => 'Meta Cloud API (oficial — 1-3 dias verificação)',
    ],

    'risk_warning' => [
        'zapi' => 'Provedor não-oficial (Whatsapp Web). Existe risco de bloqueio Meta. Configure Meta Cloud como fallback obrigatório.',
    ],

    'lgpd_acknowledgment' => 'Estou ciente que Z-API é provedor não-oficial baseado em Whatsapp Web e que existe risco de bloqueio Meta. Configurei Meta Cloud como fallback pra mitigar interrupção do meu serviço.',

    'driver_health' => [
        'healthy' => 'Conectado',
        'degraded' => 'Degradado — fallback ativo',
        'disconnected' => 'Desconectado',
        'banned' => 'Bloqueado pela Meta',
        'never_checked' => 'Aguardando primeiro teste',
    ],
];
