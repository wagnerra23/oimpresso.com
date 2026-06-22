---
page: /project-mgmt/board
component: resources/js/Pages/ProjectMgmt/Board/Index.tsx
owner: wagner
status: live
last_validated: "2026-05-08"
parent_module: ProjectMgmt
related_adrs: [110, 70, 100, 39]
tier: A
charter_version: 1
---

# Page Charter — /project-mgmt/board

> **Status:** live (ADR 0070 PMG-001..PMG-007). Pattern fonte interno do **Cockpit V2** drawer — DetailSheet aqui é a referência canônica que SaleSheet adota.

---

## Mission

Kanban board Jira-style pra gerenciar tarefas/épicos do oimpresso por ciclos: navegação rápida via J/K, ações E/A pra mover status, drawer DetailSheet ao clicar card mostra description + comments + activity + subtasks + watchers.

---

## Goals — Features (faz)

- AppShellV2 + topnav inline
- `<PageHeader>` shared (h1 + subtitle + ações)
- `<KpiGrid>` + `<KpiCard>` shared 5 contadores (Total / Doing / Review / Blocked / P0)
- Filtros: cycle dropdown + epic + owner + texto busca (persistidos em localStorage)
- Atalhos teclado:
  - **J / K** — navegar cards (próximo/anterior)
  - **E** — avançar status (todo→doing→review→done)
  - **A** — voltar status
  - **/** — focar busca da lista
- Click card abre `<DetailSheet>` lateral direito (pattern fonte do SaleSheet)
- DetailSheet com 5 tabs state-driven: Description / Comments / Activity / Subtasks / Watchers
- @mentions input rico em Comments (PMG-005)
- Watchers UI section header (PMG-006)
- Subtasks create/complete (PMG-007)

---

## Non-Goals — Features (NÃO faz)

- ❌ Drag-and-drop entre colunas (atalhos E/A canon, drag está no backlog PMG-008)
- ❌ Múltiplos boards simultâneos (1 cycle por vez)
- ❌ Time tracking / pomodoro (vai em outra Page)
- ❌ Burndown chart (vai em /project-mgmt/reports — backlog)
- ❌ Sub-issue trees infinitos (1 nível parent_task_id)

---

## UX Targets

- p95 first-paint < 1500ms
- 0 erros JS console
- Atalhos respondem < 100ms (sem lag perceptível)
- DetailSheet abre < 300ms após click
- Estado UI persistido em localStorage prefix `oimpresso.board.*` (cycle, epic, owner, search)

---

## UX Anti-patterns

- ❌ Modal pra DetailSheet (canon = `<Sheet>` lateral)
- ❌ Tabs estáticos no DetailSheet sem state (canon = state-driven)
- ❌ Cor crua em badges status/priority (canon = semântico)
- ❌ `sessionStorage`

---

## Pattern fonte (source for SaleSheet pattern)

DetailSheet aqui foi a primeira implementação do drawer canon. SaleSheet (Sells) adota mesmo padrão:

- `<Sheet side="right" className="w-full sm:max-w-xl flex flex-col p-0 overflow-hidden">`
- SheetHeader com badges + título + customer line
- Scroll body com sections heading `text-[10px] uppercase tracking-widest`
- Footer ações sticky

---

## Tests anti-regressão

- [tests/Feature/Design/CockpitPatternConformanceTest.php](../../../../../tests/Feature/Design/CockpitPatternConformanceTest.php) — sistêmico (canon target)

---

## Refs

- [Design.md §16 Cockpit V2](../../../../../Design.md)
- [ADR 0110 Cockpit Pattern V2](../../../../../memory/decisions/0110-cockpit-pattern-v2-canon-list-detail.md)
- [ADR 0070 Jira-style PM](../../../../../memory/decisions/0070-jira-style-task-management-current-md-removed.md)
- [ADR 0100 PMG fase 2 specs](../../../../../memory/decisions/0100-projectmgmt-ui-redesign.md)
- [ADR 0039 Cockpit layout-mãe](../../../../../memory/decisions/0039-ui-chat-cockpit-padrao.md)
