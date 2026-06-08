---
page: /atendimento/channels
component: resources/js/Pages/Atendimento/Channels/Index.tsx
owner: wagner
status: live
last_validated: 2026-05-16
parent_module: Whatsapp
parent_adr: memory/decisions/0096-modulo-whatsapp-meta-cloud-api-direto.md
related_adrs: [0093, 0094, 0117, 0135]
tier: A
charter_version: 1
---

# Page Charter — `/atendimento/channels`

> Define invariantes da tela de gestão de canais omnichannel (lista + criar +
> pareamento QR). Mudanças que violem este charter exigem PR + bump charter.

## Mission

Tela onde gerente cadastra/parea/desativa os canais do business (Whatsapp
Baileys / Meta Cloud / Z-API hoje · IG/FB/Email preview-only). É a porta
de entrada do operacional Atendimento — sem canal pareado, Inbox vazia.

## Goals

- Listar todos os canais do `business_id` atual (multi-tenant Tier 0)
- Permitir criar canal novo apontando driver + display_identifier (E.164)
- Mostrar status real-time do driver (`driver_health`: ok/degraded/down)
- Link "Abrir QR" pra pareamento Baileys (rota Show.tsx)
- Ação "Desativar" soft (mantém histórico, esconde da Inbox)

## Non-Goals

- ❌ NÃO expõe configuração de webhook bruto (vai pro `/admin/whatsapp/settings`)
- ❌ NÃO faz pareamento dentro do Index — sempre redireciona pra Show.tsx
- ❌ NÃO mostra mensagens / conversações (responsabilidade da Inbox)
- ❌ NÃO permite deletar canal (apenas soft-disable — preserva auditoria)

## UX targets

- Switch página ≤ 100ms (`Inertia::defer` em props caras)
- Empty state honesto ("Nenhum canal cadastrado — comece criando o primeiro")
- Botão primário "Novo canal" sempre visível no topo
- Badge driver_health com cor semântica (verde/amarelo/vermelho)

## Automation hooks

- DataController hook: `whatsapp.channels.count_active` (sidebar)
- Cron `whatsapp:channels-reconcile` (5min) atualiza `driver_health`
- Cron `whatsapp:auth-state-drift-check` (daily) detecta canal "morto"

## Anti-hooks (não fazer)

- ⛔ Polling client-side `setInterval` direto — usar Centrifugo channel events
- ⛔ Mutar canal alheio (`business_id != session`) — global scope obrigatório
- ⛔ Hardcode driver list — vem de `ChannelDriverFactory::availableDrivers()`
