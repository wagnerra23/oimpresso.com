---
page: /project-mgmt/my-work
component: resources/js/Pages/ProjectMgmt/MyWork/Index.tsx
related_prototype: n/a (herda PT-04 Dashboard; segue o Padrão de Tela)
owner: wagner
status: draft
last_validated: "2026-07-11"
parent_module: ProjectMgmt
related_us: [US-TR-204]
related_adrs: [114, 101, 93, 70, 39]
tier: B
charter_version: 1
---

# Page Charter — /project-mgmt/my-work (DRAFT)

> **Status:** draft criado em 2026-07-11 no lote de cobertura de charters. Wagner aprova **Non-Goals + Anti-hooks** ANTES de virar `status: live`.
>
> Backend: `Modules/ProjectMgmt/Http/Controllers/MyWorkController@index` (rota `project-mgmt.my-work.index`, permissão `copiloto.mcp.usage.all`). Cockpit do operador (ADR 0039): KPIs + minhas tasks ativas + Inbox de notificações.

---

## Mission
Ser a home page do operador do time: reúne num só cockpit as tasks ativas do próprio usuário (agrupadas por cycle) e a Inbox de notificações (`mcp_inbox_notifications` — mention/assigned/review/status/comment/due/blocked). Foco em fluxo teclado-first: navegar cards (J/K), avançar status (E), marcar notif lida (R), alternar foco entre painéis (Tab) — o operador toca o trabalho do dia sem sair da tela.

---

## Goals — Features (faz)
- KPIs (`KpiGrid`/`KpiCard`): doing, em revisão, bloqueadas, atrasadas, inbox não-lidas.
- Painel "My Work": tasks ativas (todo/doing/review/blocked) do owner, agrupadas por cycle com header (label, ativo, dias restantes, goal), usando `TaskCard`.
- Painel "Inbox": lista de notificações com ícone/label por tipo, marca de não-lida, toggle "mostrar lidas", "marcar todas".
- Atalhos de teclado: J/K navegar, E avançar status (`nextStatus`), R marcar lida, Shift+R marcar todas, Tab alternar foco; foco persistido em `localStorage`.
- Update otimista de status (`PATCH /my-work/{taskId}/status`) e de leitura (`POST /my-work/inbox/{id}/read`), com rollback em falha.
- Clicar numa notif com `task_id` navega ao Board com foco na task (`/project-mgmt/board?focus=...`).
- Auto-refresh a cada 30s e no `window.focus`; carga via `Inertia::defer` de `my_work`/`inbox`/`inbox_stats`/`kpis`.

---

## Non-Goals — Features (NÃO faz)
- ❌ Não é multi-tenant por `business_id` — a Inbox e as tasks são do usuário interno (`mcp_*`), gated por `copiloto.mcp.usage.all`, não por escopo de negócio. (inferência pendente de Wagner)
- ❌ Não abre o Detail Sheet aqui — clicar numa notif leva ao Board. (inferência pendente de Wagner)
- ❌ Não mostra tasks `done`/`cancelled` no painel My Work (filtradas). (inferência pendente de Wagner)
- ❌ Não edita campos da task além de avançar status (título/owner/prazo é MCP/Board). (inferência pendente de Wagner)

---

## UX targets
- p95 < 1500ms (admin) ; cabe em 1280px (ROTA LIVRE) ; AppShellV2 (breadcrumb Project Mgmt / My Work) ; layout 2 colunas (2fr work / 1fr inbox).

---

## Automation hooks (faz)
- Auto-reload a cada 30s + no `window.focus` (`only: ['my_work','inbox','inbox_stats','kpis']`).
- Update otimista de status/leitura com reconciliação por partial reload.
- Toggle `show_read` faz partial reload que pula `my_work` (não depende do filtro).

---

## Anti-hooks (NÃO faz automaticamente)
- ❌ Não muta status/leitura em GET; toda escrita via PATCH/POST com CSRF.
- ❌ Atalhos de teclado ignorados quando o foco está em input/textarea/contentEditable.
- ❌ Não marca notif como lida sem interação (só ao clicar/abrir ou tecla R).

---

## Pendências antes de `status: live`
- [ ] Wagner aprova Non-Goals + Anti-hooks
- [ ] Smoke visual 1280/1440 (screenshot) — conferir 2 colunas em 1280px
- [ ] Validar atalhos J/K/E/R/Tab/Shift+R num smoke interativo real.
