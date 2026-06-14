---
modulo: ProjectMgmt
status: Fase 1+2 done (2026-05-08) · Fase 3+4 backlog
last_pr: PR #211 (PMG-001 optimistic-lock)
nota_atual: 32/100 (Crítico) — D1 10/30 · D2 8/20 · D3 6/15
owner: Wagner [W]
piloto: time interno oimpresso (uso próprio)
adr_mae: ADR 0100 (UI redesign) · ADR 0070 (Jira-style tasks)
charter: CHARTER-board.md
spec: SPEC.md + SPEC-COMPLEMENTO.md
capterra: CAPTERRA-FICHA.md (24 capacidades) · CAPTERRA-INVENTARIO.md
updated: 2026-05-16
---

# ProjectMgmt — Briefing executivo (1 página)

## Pra que serve

Gestão Jira-style de projetos, épicos, sprints/cycles e tasks **internas do time oimpresso** — substitui Trello/Linear/Jira pra alinhar Wagner + Felipe + Maiara + Eliana + Luiz. Estado vivo das tasks fica em `mcp_tasks` (governado por tools MCP — ADR 0070), e este módulo é a **interface visual** sobre essas tabelas: Kanban, Backlog, Roadmap, My Work, Inbox, Burndown, Activity.

## Estado consolidado

| Dimensão | Nota | Evidência |
|---|---|---|
| D1 Cobertura funcional | 10/30 | Kanban + drag-drop + Detail Sheet + Cmd+K + @mentions + watchers + subtasks em prod. Faltam: time tracking, sprint planning UI, saved views backend, presence real-time. |
| D2 UX/UI | 8/20 | Linear-tier ainda não atingido (~50% gap). Otimismo+409 implementado; faltam atalhos avançados, cycle close UI. |
| D3 Testes Pest | 6/15 | 2 tests pré-existentes (BoardControllerTest com ~38 cenários PMG-003→007, SearchControllerTest). +3 novos esta wave (multi-tenant, smoke routes, scaffold). |
| D4 Multi-tenant | — | `business_id` scope aplicado via `session('user.business_id')` nos Controllers. Anti-vazamento validado em Pest. |
| D5 Docs canon | — | SPEC.md + CHARTER-board.md + CAPTERRA-FICHA.md + INVENTARIO.md + BRIEFING.md (este). |

## O que tem vivo em prod (Fase 1+2 done)

- **Board (Kanban)** — colunas drag-drop, 5 KPIs (total/doing/blocked/p0/sprint), 6 filtros, optimistic-lock 409 ([PMG-001](SPEC.md))
- **Cmd+K Global Search** — cross-resource (tasks/epics/cycles/projects), debounced 220ms ([PMG-002](SPEC.md))
- **Detail Sheet** — drawer com task/comments/events/subtasks/dependencies/watchers ([PMG-004](SPEC.md))
- **Comments + @mentions** — body + `mcp_task_comment` + suggest users ([PMG-005](SPEC.md))
- **Watchers (Follow/Unfollow)** — toggle idempotente + counts ([PMG-006](SPEC.md))
- **Subtasks UI** — create inline com `parent_task_id` ([PMG-007](SPEC.md))
- **My Work + Inbox** — mark read/all + bump status ([US-TR-204](SPEC.md))
- **Backlog + Roadmap + Activity + Burndown** — listings + bulk + chart ([US-TR-202..206](SPEC.md))

## Backlog priorizado (Fase 3+4)

- **P0** — Tests Pest mais profundos (esta wave): multi-tenant cross-business, smoke routes, scaffold (subir nota D3 6 → 12)
- **P1** — Sprint planning UI (criar/fechar cycle com goals trackables)
- **P1** — Atalhos avançados (j/k navegação, c criar, e editar inline)
- **P2** — Saved views backend (presets de filtros persistidos)
- **P2** — Real-time presence (Centrifugo CT 100 — quem está vendo a task agora)
- **P3** — Time tracking (estimate vs actual por task, burndown enriched)

## Diferenciais vs concorrentes (CAPTERRA)

Ver [CAPTERRA-FICHA.md](CAPTERRA-FICHA.md) — comparado com Linear, Jira, ClickUp, Asana, Monday. Forças: integração nativa com Jana IA + memória persistente + multi-tenant Tier 0 + governança ADR. Gaps: UX fluidez (~50% Linear), real-time, saved views.

## Riscos / pegadinhas

- ⛔ **NUNCA `withoutGlobalScopes` em McpTask/McpProject** sem comentário `// SUPERADMIN: <razão>` — multi-tenant Tier 0 IRREVOGÁVEL (ADR 0093)
- ⛔ **Tabelas `mcp_*` são canônicas pro time** — UPDATE direto via tinker em prod = drift catalogado
- ⛔ **Stack middlewares completa obrigatória** em Http/routes.php — sem `SetSessionData` o `session('user.business_id')` é null → vazamento
- ⚠️ **Schema migration** — `mcp_projects` + `mcp_tasks` vivem em Modules/Jana (Jana) — ProjectMgmt é UI sobre essas tabelas, não dono do schema
- ⚠️ **Permission canônica** — `copiloto.mcp.usage.all` (herdada do Copiloto, igual ao TeamMcp anterior) — não criar permission própria do ProjectMgmt

## Wave 18 Saturação (2026-05-16)

**Meta:** 97/100 module-grade (D5 +12, D8 +3, D1 +5).

| Δ | Entrega |
|---|---|
| D5 +12 (3→15) | Yaml `module_clients.yaml` ProjectMgmt: `backlog_hipotese` → `internal_governance_active` (ADR 0159 — Wagner usa Board/Backlog/MyWork diário pra gerir 25+ cycles; time MCP futuro depende) |
| D8 +1→3 | 2 FormRequests novos: `UpdateTaskStatusRequest` (Kanban drag-drop status in:todo,doing,blocked,done) + `UpdateTaskRequest` (priority P0-P3 + estimate 1-240h) — ratio 5/10=0.5 |
| D1 +5 | Pest `CrossTenantSaturationTest`: epics biz=1 vs biz=99 isolation + 6 FormRequest sanity check (PT-BR messages + Kanban states + priority range) |
| module.json | `governance.fsm_n_a: true` justificado (ProjectMgmt usa Kanban free-flow, NÃO FSM tabular — ADR 0143 aplica a Sells/Repair com cancel cascade) |

Services spans já cobertos pré-Wave 18 (ProjectService.list/create/update/find_detail/calculate_kpis + ProjectMgmtAuditService.log via `OtelHelper::spanBiz`).

## Wave 18 RETRY (2026-05-16)

**Meta:** subir D8 ratio 5→9 FormRequests + completar coverage PMG-005/006/007 + Bulk.

| Δ | Entrega |
|---|---|
| D8 +4 FormRequests | `AddCommentRequest` (body 1-5000 + 50 mentions PMG-005) + `AddSubtaskRequest` (title + estimate 1-240h + P0-P3 PMG-007) + `WatchTaskRequest` (20 user_ids + notify_method PMG-006) + `BulkBacklogRequest` (op in:5 + confirm:accepted destrutivos) — ratio agora 9/10 = 0.90 |
| D1 +1 test file | `CrossTenantSaturationRetryTest` — 7 testes (1 cross-tenant em mcp_jira_cycles + 6 FormRequest sanity PMG-005/006/007 + Bulk) |
| Pest local | 6/6 passed (22 assertions, 2.12s) + 1 skipped por env minimal (mcp_jira_projects/cycles ausente local) |
| D6/D9 | Já saturados pré-Wave 18 (Controllers Inertia::defer + Services OtelHelper::spanBiz cobertura completa) |
