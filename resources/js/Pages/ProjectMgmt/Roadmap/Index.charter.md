---
page: /project-mgmt/roadmap
component: resources/js/Pages/ProjectMgmt/Roadmap/Index.tsx
related_prototype: n/a (roadmap de colunas por quarter com cards de epic, bespoke — nao segue um dos 5 Padroes de Tela)
owner: wagner
status: draft
last_validated: "2026-07-11"
parent_module: ProjectMgmt
related_us: [US-TR-203]
related_adrs: [114, 101, 93, 70]
tier: B
charter_version: 1
---

# Page Charter — /project-mgmt/roadmap (DRAFT)

> **Status:** draft criado em 2026-07-11 no lote de cobertura de charters. Wagner aprova **Non-Goals + Anti-hooks** ANTES de virar `status: live`.
>
> Backend: `Modules/ProjectMgmt/Http/Controllers/RoadmapController@index` (rota `project-mgmt.roadmap.index`, permissão `copiloto.mcp.usage.all`). **Silenciosa:** o corpo dominante é um layout de colunas por quarter com cards de epic (roadmap bespoke); há `KpiGrid` de header, mas a tela não é um dos 5 Padrões de Tela — não há dnd (não é Kanban) nem tabela/grid de lista. Honestidade > cobertura.

---

## Mission
Dar a visão de planejamento por quarter: epics do projeto (`mcp_epics`) agrupados por `target_quarter`, cada um com progresso (done/total, %), owner e status. É o mapa de médio prazo — responde "o que está planejado, ativo e concluído em cada trimestre?". Epics sem quarter caem num bucket "Sem quarter".

---

## Goals — Features (faz)
- Colunas horizontais por quarter (scroll-x), cada coluna com contagem de epics.
- `EpicCard` por epic: key, status (planning/ativo/concluído/cancelado) com ícone, título, descrição, barra de progresso (%), owner, nº de ativas; borda esquerda colorida pelo `color` do epic.
- KPIs de header (`KpiGrid`/`KpiCard`): total epics, ativos, em planning, concluídos.
- Estado vazio honesto quando não há epic (aponta `epics-create` via MCP).
- Carga via `Inertia::defer` de `quarters`/`kpis` (payload memoizado por projeto no controller).

---

## Non-Goals — Features (NÃO faz)
- ❌ Não é multi-tenant por `business_id` — opera sobre `mcp_epics`/`mcp_tasks` (PM interno do time), gated por `copiloto.mcp.usage.all`. (inferência pendente de Wagner)
- ❌ Não edita epic aqui — mover `target_quarter` é `epics-update` via MCP (a própria tela diz isso no rodapé). (inferência pendente de Wagner)
- ❌ Não é Kanban: as colunas por quarter NÃO têm drag-and-drop. (inferência pendente de Wagner)
- ❌ Não segue um dos 5 Padrões de Tela: roadmap de colunas bespoke, deliberadamente silenciosa quanto a PT. (inferência pendente de Wagner)

---

## UX targets
- p95 < 1500ms (admin) ; cabe em 1280px (ROTA LIVRE) ; AppShellV2 (breadcrumb Project Mgmt / Roadmap) ; colunas com scroll-x quando excedem a largura.

---

## Automation hooks (faz)
- `Inertia::defer` desbloqueia render inicial (`quarters`/`kpis` deferidos).
- Progresso (%) e contagens derivados de agregação de `mcp_tasks` por epic no controller.

---

## Anti-hooks (NÃO faz automaticamente)
- ❌ Tela read-only: nenhuma escrita, nenhuma mutação em GET.
- ❌ Não faz polling/refresh automático.
- ❌ Não reordena/move epics por drag (edição é via MCP).

---

## Pendências antes de `status: live`
- [ ] Wagner aprova Non-Goals + Anti-hooks
- [ ] Wagner confirma o silêncio de PT (roadmap bespoke, não força um dos 5 Padrões)
- [ ] Smoke visual 1280/1440 (screenshot) — conferir scroll-x das colunas em 1280px
