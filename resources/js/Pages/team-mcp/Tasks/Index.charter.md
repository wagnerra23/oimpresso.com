---
page: /team-mcp/tasks
component: resources/js/Pages/team-mcp/Tasks/Index.tsx
owner: wagner
status: draft
last_validated: "2026-06-16"
parent_module: TeamMcp
related_adrs:
  - "0070-jira-style-task-management-current-md-removed"
  - "0081-identity-mesh-mcp-actors"
  - "0093-multi-tenant-isolation-tier-0"
  - "0094-constituicao-v2-7-camadas-8-principios"
  - "0104-processo-mwart-canonico-unico-caminho"
  - "0114-prototipo-ui-cowork-loop-formalizado"
related_ficha: memory/requisitos/TeamMcp/tasks-visual-comparison.md
tier: A
charter_version: 1
---

# Page Charter — `/team-mcp/tasks` (DRAFT)

> Criado no PR **Forja PR-1** (re-skin DS v6, 2026-06-16). Persona: Wagner [W] + time MCP (Felipe/Maiara/Eliana/Luiz), desktop, superadmin `copiloto.mcp.usage.all`. Backend: `Modules/TeamMcp/Http/Controllers/TasksAdminController.php` (Inertia::defer). Referência visual aprovada: [tasks-visual-comparison.md](../../../../memory/requisitos/TeamMcp/tasks-visual-comparison.md).

## Mission

Painel **read + drag** de governança de tasks MCP (Jira-style, [ADR 0070](../../../../memory/decisions/0070-jira-style-task-management-current-md-removed.md)): backlog denso agrupável + quadro kanban, na gramática Forja sob DS v6. Projeção fiel de `mcp_tasks` — **sem dado fantasma**. Operação primária: enxergar/triar o backlog e mover status (drag/bulk), com drawer de issue (situação + atividade real + vínculos + subtasks).

## Goals — Features (faz)

- **Aba Backlog:** lista densa agrupável por Onda(sprint)/Fase(status)/Papel(owner)/Prioridade/Módulo, grupos colapsáveis, densidade compacta/normal.
- **Aba Quadro:** kanban todo/doing/review/done, drag→`PATCH /tasks/{id}/status` otimista (reconcilia no reload).
- **Drawer de issue (560px):** Visão (descrição/meta/vínculos `blocked_by`), Atividade (`mcp_task_events` real), Subtasks. Abre via URL `?task=ID`.
- **Teclado:** J/K navegar · Enter abre · X marca · / busca · Esc fecha. ⌘K = command palette **global** (AppShellV2).
- **Seleção em lote** → `BulkActionBar` (mover p/ Fazendo/Revisão/Concluído via PATCH).
- **KPIs** (total/p0/doing/blocked + total_h) no PageHeader + totalbar de rodapé.
- **Filtros server-side** module/owner/sprint (Inertia::defer). **Polling 10s** + on-focus reload.
- **Selo de proveniência** agente vs humano (`mcp_actors` type=ai_agent — transversal §3).
- **Persistência** `localStorage oimpresso.teammcp.tasks.*` (groupBy/tab/density/search/collapsed).

## Non-Goals — Features (NÃO faz)

- ❌ Criar/editar task na tela (criação via `mcp:tasks:sync` / `tasks-create` MCP).
- ❌ Comentários/watchers no drawer (PR futura — backend já tem `mcp_task_comments`).
- ❌ Inventar fases F0..F4 — usa os 6 status canônicos (ADR 0070).
- ❌ Mostrar atividade/descrição/links além do que o backend expõe (sem fantasma).
- ❌ Tocar AppShellV2 / sidebar / PageHeader canon.
- ❌ Renomear permissões `copiloto.mcp.*` (task futura).

## UX targets

- DS v6: tokens semânticos (roxo 295 `primary`), status Stripe-**dot** (sem bg-fill), **sem cor crua**, **sem `rounded-xl+`**, ramp `--fs-*`.
- Drawer **560px** (issue ≠ cadastro 760px [ADR 0185](../../../../memory/decisions/0185-drawer-760-canon-entidades-cadastrais.md) — exceção registrada no visual-comparison).
- Loading skeleton enquanto `Inertia::defer` resolve. Empty states (vazio / busca-sem-resultado).
- `tabular-nums` em IDs/contagens/horas. Locators `data-testid` (NÚCLEO #7 anti-quebra-silenciosa).

## Anti-hooks (NÃO faz automaticamente)

- ❌ NÃO escreve nada além de `PATCH` status (bulk = N× PATCH). `show()` é **read-only**.
- ❌ NÃO loga/expõe token raw nem PII (drawer só status/meta/eventos estruturais).
- ❌ NÃO usa `<select>`/`<checkbox>`/`<radio>` nativo fora do row-check (usa `Components/ui`).
- ❌ NÃO re-monta command palette (⌘K já é global no Shell).

## Restrições Tier 0

- Permissão `copiloto.mcp.usage.all` no construtor (todas as ações, incl. `show`).
- `mcp_tasks`/`mcp_task_events` são **repo-wide cross-tenant INTENCIONAL** ([ADR 0070](../../../../memory/decisions/0070-jira-style-task-management-current-md-removed.md)) — governança da plataforma, sem `business_id` by design. (Não é dado de negócio.)
- `mcp_task_events` append-only (read no `show()`).

## Métricas de sucesso (validação Wagner)

- ✅ Backlog agrupa por 5 dimensões; grupos colapsam e persistem entre reloads.
- ✅ Quadro: drag move status (otimista) e reconcilia no reload; registra `mcp_task_events`.
- ✅ Enter na linha selecionada abre drawer com **atividade real**.
- ✅ Selo `Bot`/`User` distingue agente de humano corretamente.
- ✅ `ui:lint`/eslint + `conformance-gate` + `foundation-guard` verdes (sem cor crua / sem rounded-xl).
