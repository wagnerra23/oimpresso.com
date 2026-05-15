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
        // PR 2026-05-15 (Wagner): topnav módulo Atendimento esvaziado.
        // Caixa Unificada V4 usa `hideTopbar` no AppShellV2 (header próprio na
        // página torna a topbar redundante — modelo Cowork canônico).
        // Inbox legacy ainda acessível via URL /atendimento/inbox; entry no
        // sidebar principal lateral é gerenciada pela DataController do módulo.
    ],
];
