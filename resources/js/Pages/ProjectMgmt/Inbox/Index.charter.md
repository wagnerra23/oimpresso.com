---
page: /project-mgmt/inbox
component: resources/js/Pages/ProjectMgmt/Inbox/Index.tsx
related_prototype: "n/a (herda PT-07 Feed/Timeline; segue o DS)"
owner: wagner
status: draft
parent_module: ProjectMgmt
related_us: [US-TR-304, US-TR-305, US-TR-306, US-TR-307]
related_adrs: [70, 93, 58, 39]
related_spec: memory/requisitos/TaskRegistry/SPEC-UI-FASE7.md
stories: [US-TR-304, US-TR-305, US-TR-306]
tier: A
charter_version: 1
---

# Page Charter — /project-mgmt/inbox

> **Status:** DRAFT (Onda 2, SPEC-UI-FASE7). Superfície humana da tool MCP `my-inbox`.
> **PENDENTE:** smoke visual + aprovação SCREENSHOT do Wagner (ADR 0107/0114) antes de `status: live`.

---

## Mission

Caixa de entrada dedicada por-pessoa: mostrar as notificações do usuário autenticado (`mcp_inbox_notifications`) agrupadas por tipo, permitir marcar-lido (individual + todas) e deep-linkar pra task no Board. Paridade com a tool MCP `my-inbox` (mesma query base do `MyWorkController::buildInboxPayload`).

---

## Goals — Features (faz)

- AppShellV2 + `<PageHeader>` canon (título "Caixa de entrada" + subtitle com contadores)
- `<KpiGrid>` + `<KpiCard>` 2 contadores: Não-lidas / Últimos 30 dias
- Lista de notificações **agrupadas por tipo** (mention → assigned → review_requested → status_changed → commented → due_soon → blocked_resolved)
- **Marcar lido** (US-TR-305): individual (botão "marcar lida" por item) + **todas** (botão no header) → `PATCH /inbox/{id}/read` e `PATCH /inbox/read-all`, otimista com rollback
- **Deep-link** (US-TR-306): clicar item abre `/project-mgmt/board?task=ID` (DetailSheet) + marca lido no caminho
- Toggle **mostrar lidas / só não-lidas** (`?show_read=1`)
- Empty state: **"Caixa de entrada vazia"** (sem emoji — AP empty-state PT-BR limpo; "Nada na caixa." no modo show_read)
- Ícone + label PT-BR por tipo de notificação
- Atalhos canônicos (PT-01 + Board/MyWork): **J/K** navega item · **Enter** abre task no Board · **R** marca lida · **Shift+R** marca todas · **⌘K** palette global (dono do AppShellV2, PMG-002)
- Polling 30s + on-focus reload (badge/contador re-sincroniza)
- (futuro próximo) badge realtime via Centrifugo canal `inbox.{user_id}` — ADR 0058 (não nesta entrega)

---

## Non-Goals — Features (NÃO faz)

- ❌ Responder/comentar a notificação aqui (vai no DetailSheet do Board)
- ❌ Configurar preferências de notificação (defer)
- ❌ Notificações de outros usuários (Tier 0 — só do auth)
- ❌ Push/email (canal externo — fora de escopo)
- ❌ Realtime Centrifugo nesta entrega (polling cobre; canal documentado pra fase seguinte)
- ❌ Brain B / autonomia ADS

---

## UX Targets

- p95 first-paint < 1500ms (lista deferida via `Inertia::defer`)
- 0 erros JS console
- Marcar-lido reflete < 100ms (otimista) e reconcilia < 1s
- Deep-link abre Board com DetailSheet correto < 300ms após click
- Toque-friendly ≥ 360px

---

## UX Anti-patterns

- ❌ Mostrar notificação de outro usuário (Tier 0 — abort_unless user_id)
- ❌ Recarregar página inteira ao marcar lido (canon = partial reload `only:['inbox','inbox_stats']`)
- ❌ `sessionStorage`
- ❌ Divergir da resposta da tool `my-inbox`

---

## Multi-tenant (Tier 0 — ADR 0093)

`mcp_inbox_notifications` é **por-pessoa**: isolamento via `user_id`, **não** via `business_id` (ADR 0070 marca repo-wide). Toda leitura e escrita (markRead/markAllRead) escopa por `auth()->id()` — `abort_unless`/`where user_id` garante que ninguém marca/lê a notificação de outro. NÃO vaza entre usuários.

---

## Refs

- [SPEC-UI-FASE7](../../../../../memory/requisitos/TaskRegistry/SPEC-UI-FASE7.md) — US-TR-304..306
- [MyWorkController](../../../../../Modules/ProjectMgmt/Http/Controllers/MyWorkController.php) — inbox payload espelhado
- [McpInboxNotification](../../../../../Modules/Jana/Entities/Mcp/McpInboxNotification.php) — modelo + types
- [ADR 0070 Jira-style PM](../../../../../memory/decisions/0070-jira-style-task-management-current-md-removed.md)
- [ADR 0058 Centrifugo realtime](../../../../../memory/decisions/0058-reverb-substituido-por-centrifugo-frankenphp.md)
- [ADR UI-0013 Constituição UI v2](../../../../../memory/requisitos/_DesignSystem/adr/ui/0013-constituicao-ui-v2-camadas.md)
