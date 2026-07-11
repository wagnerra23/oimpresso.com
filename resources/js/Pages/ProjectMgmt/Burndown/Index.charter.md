---
page: /project-mgmt/burndown
component: resources/js/Pages/ProjectMgmt/Burndown/Index.tsx
related_prototype: n/a (herda PT-04 Dashboard; segue o Padrão de Tela)
owner: wagner
status: draft
last_validated: "2026-07-11"
parent_module: ProjectMgmt
related_us: [US-TR-206]
related_adrs: [114, 101, 93, 70]
tier: B
charter_version: 1
---

# Page Charter — /project-mgmt/burndown (DRAFT)

> **Status:** draft criado em 2026-07-11 no lote de cobertura de charters. Wagner aprova **Non-Goals + Anti-hooks** ANTES de virar `status: live`.
>
> Backend: `Modules/ProjectMgmt/Http/Controllers/BurndownController@index` (rota `project-mgmt.burndown.index`, permissão `copiloto.mcp.usage.all`). Dashboard de burndown do cycle: KPIs + gráfico SVG ideal vs real.

---

## Mission
Mostrar o progresso de queima (burndown) do cycle ativo (ou de um cycle escolhido) do projeto: um gráfico SVG com a linha "ideal" (decréscimo linear até 0 no `end_date`) contra a linha "real" (tasks abertas por dia), mais KPIs de ritmo e previsão. É o instrumento de acompanhamento de sprint — responde "estamos no pace pra terminar dentro dos dias restantes?".

---

## Goals — Features (faz)
- KPIs (`KpiGrid`/`KpiCard`): total, concluídas, restantes, pace/dia (janela 7d) e previsão em dias (com tom success/danger conforme cabe ou excede os dias restantes).
- Gráfico burndown SVG bespoke (`BurndownChart`): polyline ideal (tracejada) + polyline real (azul) + pontos, eixos com ticks, labels de data amostradas; responsivo com `overflow-x-auto`.
- Header com metadados do cycle (`key`, nome, `start_date → end_date`, dias restantes) e o goal do cycle em card destacado.
- Seletor de cycle (dropdown) quando há mais de um — troca via partial reload.
- Estado vazio honesto quando não há cycle ativo (aponta `cycles-create` via MCP).
- Carga via `Inertia::defer` de `cycles`/`cycle`/`series`/`kpis` (query histórica memoizada por `(projectId, cycleId)` no controller).

---

## Non-Goals — Features (NÃO faz)
- ❌ Não é multi-tenant por `business_id` — opera sobre `mcp_cycles`/`mcp_tasks`/`mcp_task_events` (PM interno do time), gated por `copiloto.mcp.usage.all`. (inferência pendente de Wagner)
- ❌ Não cria/edita cycle aqui (isso é `cycles-create`/`cycles-close` via MCP). (inferência pendente de Wagner)
- ❌ Não reconstrói histórico perfeito: tasks movidas pra `done` sem evento contam no KPI `done` mas não no histórico do gráfico — ruído pré-existente aceito. (inferência pendente de Wagner)
- ❌ Não plota cycle com menos de 2 pontos (mostra aviso "cycle muito curto"). (inferência pendente de Wagner)

---

## UX targets
- p95 < 1500ms (admin) ; cabe em 1280px (ROTA LIVRE) ; AppShellV2 (breadcrumb Project Mgmt / Burndown).

---

## Automation hooks (faz)
- Série reconstruída de `mcp_task_events` (`event_type=status_changed`, `to_value=done`).
- Previsão (`forecast_days`) derivada do pace dos últimos 7 dias.
- `Inertia::defer` desbloqueia render inicial; troca de cycle faz partial reload (`only: ['project','cycle','series','kpis','filters']`).

---

## Anti-hooks (NÃO faz automaticamente)
- ❌ Não faz polling — o gráfico só atualiza ao recarregar ou trocar de cycle.
- ❌ Não escreve nada (tela read-only); nenhuma mutação em GET.
- ❌ Não fecha/rola o cycle sozinho ao atingir 0 restantes.

---

## Pendências antes de `status: live`
- [ ] Wagner aprova Non-Goals + Anti-hooks
- [ ] Smoke visual 1280/1440 (screenshot) — conferir gráfico SVG em 1280px
- [ ] Validar leitura do KPI "previsão" com cycle real (pace zero mostra "—").
