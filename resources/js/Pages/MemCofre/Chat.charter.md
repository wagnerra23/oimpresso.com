---
page: /memcofre/chat
component: resources/js/Pages/MemCofre/Chat.tsx
related_prototype: n/a (tela de chat conversacional bespoke — não segue um dos 5 Padrões de Tela)
owner: wagner
status: draft
last_validated: "2026-07-11"
parent_module: MemCofre
related_us: [US-DOCVAULT-005]
related_adrs: [114, 101, 93]
tier: B
charter_version: 1
---

# Page Charter — /memcofre/chat (DRAFT)

> **Status:** draft criado em 2026-07-11 no lote de cobertura de charters. Wagner aprova **Non-Goals + Anti-hooks** ANTES de virar `status: live`.
>
> Backend: `Modules/SRS/Http/Controllers/ChatController@index` (rota `memcofre.chat`) + `@ask` (POST `memcofre.chat.ask`) + `@newSession` (POST `memcofre.chat.new`), prefixo `/memcofre`, stack admin UltimatePOS + `throttle:60,1`. Módulo `Modules/SRS` ("Cofre de Memórias") — ferramenta interna Wagner de uso raro, em deprecação segundo o BRIEFING. Tela implementada de verdade (persiste `DocChatMessage`, usa `ChatAssistant`).
>
> Classificação: **SILENCIOSO** — o `<form>` presente é só o compositor de mensagem, não um formulário de dados; a tela é uma interface de chat bespoke (não é PT-01..05).

---

## Mission
Perguntar ao conhecimento consolidado no Cofre (specs, arquiteturas, ADRs, changelogs) e receber resposta — em modo offline (busca local) ou IA quando `memcofre.ai.enabled`. O usuário limita o escopo a um módulo, mantém histórico por sessão e revisita conversas recentes.

---

## Goals — Features (faz)
- Chat com histórico persistido por `session_id`/`user_id`/`business_id` (`DocChatMessage`), render otimista da mensagem do usuário.
- Seletor de escopo por módulo (limita a busca) e badge de estado (IA ativa vs modo offline).
- Sidebar de conversas recentes (até 10 sessões, com preview e contagem) + "Nova conversa" (gera `session_id` novo).
- Envio via `fetch` POST `/memcofre/chat/ask` (Enter envia, Shift+Enter quebra linha); resposta mostra fontes e modo (IA/offline).

---

## Non-Goals — Features (NÃO faz)
- ❌ Não edita nem apaga mensagens/sessões do histórico. _Inferência pendente de Wagner._
- ❌ Não muta documentação/requisitos — é só consulta (read) sobre o conhecimento. _Inferência pendente de Wagner._
- ❌ Não expõe conversa entre businesses (histórico escopado por `business_id` + `user_id`).
- ❌ Não usa IA quando `memcofre.ai.enabled` está desligado — cai pro modo offline.

---

## UX targets
- p95 < 1500ms (admin) na tela ; a chamada `ask` pode exceder quando bate LLM (aceitável, com indicador de "digitando") ; cabe em 1280px (ROTA LIVRE) ; AppShellV2 (breadcrumb "Cofre › Chat").

---

## Automation hooks (faz)
- `ChatAssistant::ask` gera a resposta (offline ou LLM) e grava pergunta + resposta com `tokens_used`.
- `throttle:60,1` no backend limita o custo de loop no chat (defense-in-depth, tool interna).

---

## Anti-hooks (NÃO faz automaticamente)
- ❌ Não chama LLM sem a flag `memcofre.ai.enabled` ligada.
- ❌ Não faz polling nem streaming contínuo — 1 request por pergunta.
- ❌ Não envia dados pra fora do escopo do business/usuário.

---

## Pendências antes de `status: live`
- [ ] Wagner aprova Non-Goals + Anti-hooks
- [ ] Smoke visual 1280/1440 (screenshot) — estados IA ativa vs offline
- [ ] **Bug latente:** `newSession()` redireciona pra `/docs/chat?session=...` (prefixo stale) enquanto a rota real é `/memcofre/chat`; nenhum prefixo `/docs` existe em `routes.php`. Alinhar antes de live.
