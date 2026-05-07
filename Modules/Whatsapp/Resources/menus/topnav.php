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
        ['label' => 'Conversas',      'href' => '/whatsapp/conversations', 'icon' => 'MessageSquare', 'can' => 'whatsapp.access'],
        ['label' => 'Templates',      'href' => '/whatsapp/templates',     'icon' => 'FileText',      'can' => 'whatsapp.templates.manage'],
        ['label' => 'Configurações',  'href' => '/whatsapp/settings',      'icon' => 'Settings',      'can' => 'whatsapp.settings.manage'],
    ],
];
