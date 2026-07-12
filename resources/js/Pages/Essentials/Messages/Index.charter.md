---
page: /essentials/messages
component: resources/js/Pages/Essentials/Messages/Index.tsx
related_prototype: n/a (mural de chat bespoke — bolhas de mensagem + composer com polling; não segue um dos 5 Padrões de Tela)
owner: wagner
status: draft
last_validated: "2026-07-11"
parent_module: Essentials
related_adrs: [114, 101, 93]
tier: B
charter_version: 1
---

# Page Charter — /essentials/messages (DRAFT)

> **Status:** draft criado em 2026-07-11 no lote de cobertura de charters. Wagner aprova **Non-Goals + Anti-hooks** ANTES de virar `status: live`.
>
> Backend: `Modules/Essentials/Http/Controllers/EssentialsMessageController@index` + `@store` + `@getNewMessages` (resource `messages` only index/destroy). Mural de mensagens internas do business em formato de chat, com polling de novas mensagens.

---

## Mission
Oferecer um mural de mensagens internas do business, apresentado como chat cronológico. O usuário lê mensagens da equipe (bolhas alinhadas: próprias à direita), escreve novas (opcionalmente marcadas por loja/localidade) e remove as próprias. Novas mensagens aparecem por polling sem recarregar a página.

---

## Goals — Features (faz)
- Lista cronológica de mensagens em bolhas, com iniciais do remetente, timestamp humanizado e badge de localidade.
- Composer com textarea (Enter envia, Shift+Enter quebra linha) e seletor opcional de loja; envia via `POST /essentials/messages` (throttle 60/min).
- Polling de novas mensagens via `GET /essentials/get-new-messages?last_chat_time=...` no intervalo `refreshInterval` vindo do backend, deduplicando por id.
- Auto-scroll para a última mensagem a cada atualização.
- Remoção da própria mensagem com confirmação (`DELETE /essentials/messages/{id}`).
- Gating por permissão (`can.view` / `can.create`): oculta lista/composer conforme Spatie `essentials.view_message` / `essentials.create_message`.
- Segurança: renderiza mensagem como TEXTO (React escapa; `<br>` legado vira quebra de linha) — nunca `dangerouslySetInnerHTML`.

---

## Non-Goals — Features (NÃO faz)
- ❌ NÃO mostra mensagens de outro business — `scopedMessagesQuery($businessId)` (multi-tenant Tier 0); store grava com `business_id` da sessão.
- ❌ NÃO é chat 1:1/DM — é mural coletivo do business (opcionalmente por loja).
- ❌ NÃO edita mensagem já enviada.
- ❌ NÃO remove mensagem de terceiro (só própria, checado no backend).
- ❌ NÃO anexa arquivos/imagens à mensagem.

---

## UX targets
- p95 < 1500ms (admin) / < 800ms (produção) ; cabe em 1280px (ROTA LIVRE) ; AppShellV2 quando aplicável

---

## Automation hooks (faz)
- Polling periódico de novas mensagens (intervalo do backend, mínimo 5s).
- Notificação `NewMessageNotification` no store, com anti-spam: gravação DB só se passou >10min da última mensagem da mesma localidade.
- Listas `messages` e `locations` carregadas via `Inertia::defer`.

---

## Anti-hooks (NÃO faz automaticamente)
- ❌ NÃO envia notificação DB a cada mensagem (respeita janela anti-spam de 10min).
- ❌ NÃO usa WebSocket/push — atualização é polling client-side.
- ❌ NÃO muta dados em GET (o `get-new-messages` é somente leitura).

---

## Pendências antes de `status: live`
- [ ] Wagner aprova Non-Goals + Anti-hooks
- [ ] Smoke visual 1280/1440 (screenshot)
- [ ] Confirmar intervalo de polling adequado à carga (config `chat_refresh_interval`)
