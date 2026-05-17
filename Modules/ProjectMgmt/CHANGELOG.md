# CHANGELOG — Modules/ProjectMgmt

> Append-only. Mais novo no topo. Datas YYYY-MM-DD.

## [Wave 18 RETRY — Saturação ADS+ProjectMgmt full] — 2026-05-16

### Adicionado
- **4 FormRequests novos** em `Http/Requests/` (D8.c — ratio agora 9/10 = 0.90):
  - `AddCommentRequest` — POST /board/{taskId}/comment (body 1-5000 + 50 mentions PMG-005)
  - `AddSubtaskRequest` — POST /board/{taskId}/subtask (title + estimate 1-240h + P0-P3 PMG-007)
  - `WatchTaskRequest` — POST /board/{taskId}/watch (20 user_ids max + notify_method PMG-006)
  - `BulkBacklogRequest` — POST /backlog/bulk (op in:5 + confirm:accepted destrutivos)
- **Pest `CrossTenantSaturationRetryTest`** (Tests/Feature/) — 7 testes:
  - Cross-tenant em mcp_jira_cycles biz=1 vs biz=99 (sprints isolation)
  - 5 FormRequest sanity (rules + messages PT-BR + Kanban/PMG-005-007 rules)
  - 1 autorize batch check todos novos FormRequests

### Não alterado (intencional — já saturado)
- D6.a Controllers já tinham `Inertia::defer` cobertura completa desde Wave 16/17 (Board/Backlog/MyWork/Burndown/Roadmap)
- D9.a Services já tinham `OtelHelper::spanBiz` cobertura desde Wave 17 (ProjectService + ProjectMgmtAuditService)
- BoardController::addComment/addSubtask/watch/unwatch + BacklogController::bulk mantém `Request $request` — novos FormRequests opt-in pro PR seguinte de type-hint upgrade

### Referências
- ADR 0093 Multi-tenant Tier 0 IRREVOGÁVEL
- ADR 0100 ProjectMgmt PMG-004/005/006/007 (comments + watchers + subtasks)
- ADR 0101 Tests biz=1 (nunca cliente)
- ADR 0143 FSM Pipeline (N/A pra Kanban free-flow ProjectMgmt)
- ADR 0155 Module Grade v3 (D6.a saturated / D8.c 9 FormRequests)
- ADR 0159 Wave 18 internal_governance_active level

## [Wave 18 Saturação] — 2026-05-16

### Adicionado
- **2 FormRequests** em `Http/Requests/` (D8.c — ratio 5/10 = 0.50):
  - `UpdateTaskStatusRequest` — PATCH /api/projectmgmt/board/tasks/{id}/status (Kanban drag-drop, status in:todo,doing,blocked,done)
  - `UpdateTaskRequest` — PATCH genérico de task (priority in:P0-P3, estimate 1-240h, owner/title/desc)
- **Pest `CrossTenantSaturationTest`** (Tests/Feature/) — 7 testes:
  - Cross-tenant em mcp_jira_epics biz=1 vs biz=99 isolation
  - 6 FormRequest sanity check (Kanban states + priority range + PT-BR messages + autorize todos)

### Alterado
- `module.json` — adicionado bloco `governance.fsm_n_a: true` + razão documentada (Kanban free-flow ≠ FSM tabular ADR 0143 que aplica a Sells/Repair com cancel cascade)
- `config/governance/module_clients.yaml` ProjectMgmt: `backlog_hipotese` (3pts) → `internal_governance_active` (15pts) — Wagner usa Board/Backlog/MyWork diário pra gerir 25+ cycles Jira-style; time MCP futuro depende (ADR 0159)

### Não alterado (intencional)
- BoardController/BacklogController/MyWorkController/BurndownController/RoadmapController já tinham `Inertia::defer` cobertura desde Wave 16/17 (D6.a saturated)
- ProjectService + ProjectMgmtAuditService já tinham `OtelHelper::spanBiz` cobertura desde Wave 17 (D9.a saturated)
- BoardController::updateStatus mantém assinatura `Request $request` — `UpdateTaskStatusRequest` opt-in pro próximo PR de type-hint upgrade

### Referências
- ADR 0070 Jira-style task management (MD-first → DB primary)
- ADR 0093 Multi-tenant Tier 0
- ADR 0101 Tests biz=1 (nunca cliente)
- ADR 0143 FSM Pipeline (N/A pra ProjectMgmt — Kanban free-flow)
- ADR 0155 Module Grade v3 (D6.a/D8.c)
- ADR 0159 Wave 18 internal_governance_active level
