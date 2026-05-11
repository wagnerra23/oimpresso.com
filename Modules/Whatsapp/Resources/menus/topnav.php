<?php

/**
 * TopNav declarativo do Whatsapp.
 *
 * Estrutura: Conversas (Inbox Cockpit) → Templates (HSM/locais) → Configurações (wizard 2 passos).
 *
 * @see memory/requisitos/Whatsapp/SPEC.md
 * @see memory/decisions/0096-modulo-whatsapp-meta-cloud-api-direto.md
 */

return [
    'label' => 'Whatsapp',
    'icon'  => 'MessageCircle',
    'items' => [
        // US-WA-067 — Inbox unificada. /whatsapp/conversations legacy permanece
        // ativa pra compat (Z-API/Meta Cloud que ainda usam WhatsappBusinessConfig)
        // mas escondida do topnav. Acessível direto via URL pra debug.
        ['label' => 'Inbox',          'href' => '/atendimento/inbox',      'icon' => 'Inbox',         'can' => 'whatsapp.access'],
        ['label' => 'Templates',      'href' => '/whatsapp/templates',     'icon' => 'FileText',      'can' => 'whatsapp.templates.manage'],
        ['label' => 'Canais',         'href' => '/atendimento/canais',     'icon' => 'Plug',          'can' => 'whatsapp.settings.manage'],
        ['label' => 'Configurações',  'href' => '/whatsapp/settings',      'icon' => 'Settings',      'can' => 'whatsapp.settings.manage'],
    ],
];
