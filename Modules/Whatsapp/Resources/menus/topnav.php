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
        // US-WA-067 + PR #889 cleanup 2026-05-15: Caixa Unificada V4 absorveu
        // Templates HSM, Templates Jana e Canais via topnav direita (dropdown
        // "Templates" + botão "Canais"). Inbox legacy mantido como fallback
        // até paridade funcional completa (inventário §2 cutover V4).
        ['label' => 'Caixa unificada', 'href' => '/atendimento/caixa-unificada',          'icon' => 'Inbox',     'can' => 'whatsapp.access'],
        ['label' => 'Inbox (legacy)',  'href' => '/atendimento/inbox',                    'icon' => 'Archive',   'can' => 'whatsapp.access'],
    ],
];
