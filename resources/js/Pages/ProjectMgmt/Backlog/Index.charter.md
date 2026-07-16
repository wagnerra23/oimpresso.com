---
page: /project-mgmt/backlog
component: resources/js/Pages/ProjectMgmt/Backlog/Index.tsx
related_prototype: n/a (herda PT-01 Lista; segue o Padrão de Tela)
owner: wagner
status: draft
last_validated: "2026-07-11"
parent_module: ProjectMgmt
related_us: [US-TR-202]
related_adrs: [114, 101, 93, 70]
tier: B
charter_version: 1
---

# Page Charter — /project-mgmt/backlog (DRAFT)

> **Status:** draft criado em 2026-07-11 no lote de cobertura de charters. Wagner aprova **Non-Goals + Anti-hooks** ANTES de virar `status: live`.
>
> Backend: `Modules/ProjectMgmt/Http/Controllers/BacklogController@index` (rota `project-mgmt.backlog.index`, permissão `copiloto.mcp.usage.all`). Lista densa filtrável com bulk edit sobre as tabelas internas `mcp_*` do PM Jira-style do time.

---

## Mission
Dar ao operador do time uma visão tabular densa de TODAS as tasks do projeto (`mcp_tasks`), com filtros combináveis (status, prioridade, owner, epic, sprint, busca livre, ordenação) e edição em lote. É a superfície de gestão do backlog: diferente do Board (Kanban por status ativo), o Backlog mostra tudo — incluindo `done`/`cancelled` quando pedido — e permite selecionar N tasks e mudar status/prioridade/owner de uma vez.

---

## Goals — Features (faz)
- Tabela de tasks (`<table>`) com colunas ID, título, módulo, owner, prioridade, status, estimativa (h ou story points), prazo (com destaque de atraso).
- Filtros: status (incl. "Ativos" default e "Todos"), prioridade (P0–P3), owner, epic, sprint (só aparece se houver), ordenação (prioridade/recentes/prazo/título/id) e busca livre com debounce (350ms).
- Persistência dos filtros em `localStorage` (chaves `oimpresso.backlog.*`) — o operador reencontra sua visão.
- Seleção múltipla (checkbox por linha + selecionar todas) e barra de ação em lote fixa (sticky) para mudar status/prioridade/owner das selecionadas.
- KPIs no topo (total, ativas, P0 abertas, atrasadas, sem owner) via `KpiGrid`/`KpiCard`.
- Carga via `Inertia::defer` de `tasks`/`kpis`/`epics`/`owners`/`sprints` (props opcionais com default-guard pra não crashar no 1º paint).
- Bulk edit chama `POST /project-mgmt/backlog/bulk` (`BacklogController@bulk`) e recarrega parcialmente `tasks`/`kpis`.

---

## Non-Goals — Features (NÃO faz)
- ❌ Não é multi-tenant por `business_id` — opera sobre as tabelas internas `mcp_*` (PM do time oimpresso), gated por permissão `copiloto.mcp.usage.all`, não por escopo de negócio. (inferência pendente de Wagner)
- ❌ Não cria task nova aqui (criação é via tools MCP `tasks-create`). (inferência pendente de Wagner)
- ❌ Não abre o Detail Sheet da task nesta tela (o drawer de detalhe vive no Board). (inferência pendente de Wagner)
- ❌ Não pagina server-side além do limite fixo de 500 linhas; acima disso o operador filtra. (inferência pendente de Wagner)

---

## UX targets
- p95 < 1500ms (admin) ; cabe em 1280px (ROTA LIVRE) ; AppShellV2 (breadcrumb Project Mgmt / Backlog).

---

## Automation hooks (faz)
- Bulk update grava event audit em `mcp_task_events` (append-only) — `tasks-bulk-update`.
- `Inertia::defer` pula closures não-solicitadas em partial reload (`only: ['tasks','kpis','filters']`).
- Debounce de busca (350ms) evita re-fetch por tecla.

---

## Anti-hooks (NÃO faz automaticamente)
- ❌ Não faz polling/refresh automático (ao contrário de Activity/MyWork) — recarrega só sob ação do usuário.
- ❌ Não muta dados em GET; toda escrita passa por `POST /backlog/bulk` com CSRF.
- ❌ Não aplica bulk sem seleção (`selected.size === 0` → no-op).

---

## Pendências antes de `status: live`
- [ ] Wagner aprova Non-Goals + Anti-hooks
- [ ] Smoke visual 1280/1440 (screenshot)
- [ ] Confirmar posture de escopo: tabelas `mcp_*` são internas do time (não business_id) — validar que a permissão `copiloto.mcp.usage.all` é o gate correto.
