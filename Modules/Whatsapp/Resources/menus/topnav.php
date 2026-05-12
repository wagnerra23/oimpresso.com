<?php

/**
 * TopNav declarativo do Whatsapp.
 *
 * Estrutura: Conversas (Inbox Cockpit) → Templates (HSM/locais) → Configurações (wizard 2 passos).
 *
 * @see memory/requisitos/Whatsapp/SPEC.md
 * @see memory/decisions/0096-modulo-whatsapp-meta-cloud-api-direto.md
 */

// Label "Atendimento" (não "Whatsapp") pra refletir arquitetura omnichannel
// ADR 0135 — hoje Whatsapp Baileys/Meta/Z-API; futuro Instagram DM, Messenger,
// Email, Mercado Livre tudo entra no mesmo módulo. Renomeado 2026-05-11
// (US-WA-082). Diretório `Modules/Whatsapp/` mantido (refator de namespace
// caro vs benefício — só labels visíveis ao usuário mudaram).
return [
    'label' => 'Atendimento',
    'icon'  => 'MessageCircle',
    'items' => [
        // US-WA-067 — Inbox unificada `/atendimento/inbox`. US-WA-091
        // (Wagner 2026-05-11): rotas `/whatsapp/conversations*` legacy
        // REMOVIDAS completamente — caminho único agora.
        // US-WA-070: "Configurações" /whatsapp/settings → "Templates Jana"
        // /atendimento/canais/jana-templates (drivers migraram pra Canais).
        ['label' => 'Inbox',           'href' => '/atendimento/inbox',                    'icon' => 'Inbox',     'can' => 'whatsapp.access'],
        ['label' => 'Templates HSM',   'href' => '/whatsapp/templates',                   'icon' => 'FileText',  'can' => 'whatsapp.templates.manage'],
        ['label' => 'Canais',          'href' => '/atendimento/canais',                   'icon' => 'Plug',      'can' => 'whatsapp.settings.manage'],
        ['label' => 'Templates Jana',  'href' => '/atendimento/canais/jana-templates',    'icon' => 'Bot',       'can' => 'whatsapp.settings.manage'],
    ],
];
