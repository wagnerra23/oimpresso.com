---
id: requisitos-project-mgmt-capterra-inventario
title: "CAPTERRA-INVENTARIO — ProjectMgmt"
slug: capterra-inventario-projectmgmt
type: inventario
status: aceito
generated_at: 2026-05-09
generated_by: audit-constituicao
source_ficha: CAPTERRA-FICHA.md
source_spec: SPEC.md
---

# CAPTERRA-INVENTÁRIO — ProjectMgmt

> Cruzamento entre [CAPTERRA-FICHA.md](CAPTERRA-FICHA.md) (27 capacidades baseline + score P0-P3), [SPEC.md](SPEC.md) (PMG-001..PMG-025) e código real em [`Modules/ProjectMgmt/`](../../../Modules/ProjectMgmt/) + [`resources/js/Pages/ProjectMgmt/`](../../../resources/js/Pages/ProjectMgmt/).
> ADR de governança: [0089](../../decisions/0089-capterra-driven-module-evolution.md). ADR mãe redesign: [0100](../../decisions/0100-projectmgmt-ui-redesign.md).
> Próxima reauditoria sugerida: após Fase 3 (PMG-008/009/010 — atalhos avançados + cycle close + sprint planning).

---

## Resumo executivo

| Bucket | Quantidade | % |
|---|---|---|
| ✅ APROVADO | 7 | 26% |
| 🟡 PARCIAL  | 7 | 26% |
| ❌ AUSENTE  | 13 | 48% |
| **Total** | **27** | 100% |

**Por score (P0-P3):**

| Score | ✅ | 🟡 | ❌ | Total |
|---|---|---|---|---|
| P0 (bloqueador)            | 4 | 2 | 0 | 6 |
| P1 (mercado tem)           | 3 | 3 | 5 | 11 |
| P2 (diferenciação)         | 0 | 1 | 5 | 6 |
| P3 (opcional)              | 0 | 1 | 3 | 4 |

**Diagnóstico:** Fase 1 (drag-drop atomic + Cmd+K + Pest base) e Fase 2 (Detail Sheet + @mentions + watchers + subtasks) entregues — fundação sólida (6 telas em prod, 7 controllers, 15 tabelas `mcp_*`, 52 cenários Pest). P0 100% coberto (4 ✅ + 2 🟡 — gaps são endurecimento de testes/URL-state, não funcionalidade ausente). Maior gap está em **P1 workflow** (Cycle close UI, Sprint planning, Saved views backend, Triage page, Centrifugo presence) — 5 itens AUSENTE casam exatamente com Fase 3 + Fase 4 da SPEC (PMG-008..PMG-012). P2/P3 são diferenciação mercado (graph dependencies, custom fields, workload, time tracking, templates, automation, dark mode wiring, mobile, roadmap drag, public share) — backlog não-comprometido (PMG-013..PMG-025).

---

## ✅ APROVADO (7)

| Capacidade | Score | Evidência |
|---|---|---|
| **Kanban drag-drop completo (droppable + 409 conflict)** | P0 | [`BoardColumn.tsx`](../../../resources/js/Components/board/BoardColumn.tsx) com `onDragOver`/`onDrop` atomic; [`BoardController::updateStatus`](../../../Modules/ProjectMgmt/Http/Controllers/BoardController.php) aceita `expected_updated_at` → 409 Conflict com `current` state; revert otimismo + banner amarelo em [`Board/Index.tsx`](../../../resources/js/Pages/ProjectMgmt/Board/Index.tsx#L196). PR #211 + R-PMG-005 testado em [`BoardControllerTest.php`](../../../Modules/ProjectMgmt/Tests/Feature/BoardControllerTest.php). |
| **Backlog priorização visual + bulk operations** | P0 | [`BacklogController::bulk`](../../../Modules/ProjectMgmt/Http/Controllers/BacklogController.php#L119) com permission check + bulk_op_id correlacionando audit em `mcp_task_events` (via [`TaskCrudService::bulkUpdate`](../../../Modules/Jana/Services/TaskRegistry/TaskCrudService.php#L250)). 7 dimensões filtros (status/priority/owner/epic/cycle/sprint/q). |
| **My Work + Inbox unread badges** | P0 | [`MyWorkController`](../../../Modules/ProjectMgmt/Http/Controllers/MyWorkController.php) agrupa tasks por Cycle (ativo destacado) + carrega `mcp_inbox_notifications` com unread/total_30d KPIs + endpoints `markRead`/`markAllRead`/`bumpStatus`. Page [`MyWork/Index.tsx`](../../../resources/js/Pages/ProjectMgmt/MyWork/Index.tsx) renderiza inbox + buckets cycle. |
| **Search global Cmd+K (command palette)** | P0 | [`CommandPalette.tsx`](../../../resources/js/Components/CommandPalette.tsx) (cmdk via shadcn) com fetch debounced 220ms + grupos Tasks/Epics/Cycles/Projects; [`SearchController`](../../../Modules/ProjectMgmt/Http/Controllers/SearchController.php) com permission gate + LIKE multi-resource; atalho global Cmd/Ctrl+K em `AppShellV2`. 4 cenários Pest em [`SearchControllerTest.php`](../../../Modules/ProjectMgmt/Tests/Feature/SearchControllerTest.php). |
| **Comments com @mentions (autocomplete + Notification)** | P1 | [`MentionInput.tsx`](../../../resources/js/Components/MentionInput.tsx) com trigger `@` + autocomplete debounced 180ms + ↑↓ + Enter/Tab/Esc/Cmd+Enter; [`BoardController::addComment`](../../../Modules/ProjectMgmt/Http/Controllers/BoardController.php#L365) + `suggestUsers`; parser regex `/@([a-z][a-z0-9_-]+)/i` + dispatch `McpInboxNotification::notify()` em [`TaskCrudService::comment()`](../../../Modules/Jana/Services/TaskRegistry/TaskCrudService.php). PR #222. |
| **Watchers UI (follow/unfollow task)** | P1 | [`McpTaskWatcher`](../../../Modules/Jana/Entities/Mcp/McpTaskWatcher.php) Model + tabela `mcp_task_watchers`; [`BoardController::watch`/`unwatch`](../../../Modules/ProjectMgmt/Http/Controllers/BoardController.php#L438) idempotentes (firstOrCreate/delete); tab Watchers em [`DetailSheet.tsx`](../../../resources/js/Pages/ProjectMgmt/Board/DetailSheet.tsx) com lista + Seguir/Parar de seguir. PR #224. |
| **Subtasks (1 nível + create + toggle status)** | P1 | [`BoardController::addSubtask`](../../../Modules/ProjectMgmt/Http/Controllers/BoardController.php#L502) reusa `TaskCrudService::create()` com `parent_task_id`; tab Subtasks em [`DetailSheet.tsx`](../../../resources/js/Pages/ProjectMgmt/Board/DetailSheet.tsx) com checkboxes (toggla todo↔done via PATCH otimista) + form add inline. PR #226. |

---

## 🟡 PARCIAL (7)

| Capacidade | Score | Evidência | Gap |
|---|---|---|---|
| **Multi-tenant + Permissions Spatie cobertas por testes Pest** | P0 | Todos controllers checam `can:copiloto.mcp.usage.all`; UI esconde botões; [`BoardControllerTest.php`](../../../Modules/ProjectMgmt/Tests/Feature/BoardControllerTest.php) cobre 403 GET/PATCH; SearchControllerTest cobre permission gate. R-PMG-002 documenta que `mcp_*` são governance (sem business_id). | Falta `Modules/ProjectMgmt/Tests/Feature/PermissionsTest.php` dedicado com matriz cross-controller (Backlog/MyWork/Roadmap/Activity/Burndown sem permission → 403); falta cenário cross-tenant explícito (mesmo que governance, permission gate é Tier 0). |
| **Filters URL state-driven (compartilhável + back button)** | P0 | [`Board/Index.tsx`](../../../resources/js/Pages/ProjectMgmt/Board/Index.tsx#L86) usa `router.get` com query params + localStorage como cache (`oimpresso.board.cycle/epic/owner/search`); `aplicar()` reescreve URL. | Sem teste E2E cobrindo back/forward preservando filters; localStorage e URL podem divergir; sem aplicação consistente em Backlog/MyWork/Roadmap (cada page reimplementa o pattern). Falta lib compartilhada (ex: `useUrlState()`). |
| **Atalhos keyboard (J/K/E/A + /)** | P1 | [`Board/Index.tsx`](../../../resources/js/Pages/ProjectMgmt/Board/Index.tsx#L278) implementa J/K (next/prev card), E (advance status), A (back status), / (focus search). Doc do header lista todos. | Falta `?` overlay help, `c` create, `Esc` close sheet (apenas Sheet shadcn nativo), shortcut hub global (não só Board); `e` documentado como "abre Detail Sheet em modo edit" — não implementado. PMG-008 pendente. |
| **Activity feed timeline (filtros + permalinks)** | P1 | [`ActivityController`](../../../Modules/ProjectMgmt/Http/Controllers/ActivityController.php) com filtros type/author/task/days (1-90); KPIs last_24h/last_7d/created/completed; limit 300. Page [`Activity/Index.tsx`](../../../resources/js/Pages/ProjectMgmt/Activity/Index.tsx) renderiza timeline. | Sem permalink clicável pra task referenciada (só `task_id` mostrado, sem link `/project-mgmt/board?task=ID`); sem lazy load se >100 (limit hardcoded 300); sem range custom (slider days fixo 1-90). |
| **Burndown chart** | P1 | [`BurndownController`](../../../Modules/ProjectMgmt/Http/Controllers/BurndownController.php) calcula linha ideal vs real reconstruindo de `mcp_task_events`; pace_per_day + forecast_days; cycle selector via dropdown. | Sem comparação multi-cycle (só 1 cycle por vez); sem projection line explícita (só forecast_days numérico); scope_creep highlight ausente; sem cycles selector multi-select. |
| **Dependencies graph (blocks / blocked_by)** | P2 | Tabela `mcp_task_dependencies` populada; [`BoardController::show`](../../../Modules/ProjectMgmt/Http/Controllers/BoardController.php#L246) carrega `dependencies[]` + `dependency_targets` map; [`DetailSheet.tsx`](../../../resources/js/Pages/ProjectMgmt/Board/DetailSheet.tsx) renderiza section "Dependências" com display_id + status + title. | Sem grafo visual (D3/SVG); sem validação no PATCH status (tarefa pode ir pra `done` mesmo com bloqueador `blocks` não-resolvido); sem direção bidirecional UI (`blocked_by` vs `blocks`). |
| **Dark mode + theme persisted** | P3 | [`Hooks/useTheme.ts`](../../../resources/js/Hooks/useTheme.ts) + [`Components/ThemeToggle.tsx`](../../../resources/js/Components/ThemeToggle.tsx) existem; Tailwind 4 com classes `dark:` em Components. | `ThemeToggle` NÃO está montado em `AppShellV2` (busca via grep zero matches em Layouts/AppShellV2.tsx); usuário não consegue ativar; sem persistência efetivada (hook existe sem caller). |

---

## ❌ AUSENTE (13)

| Capacidade | Score | Evidência |
|---|---|---|
| **Cycle close UI (rollover + retro markdown)** | P1 | Não encontrado em `Modules/ProjectMgmt/Http/Controllers/*` ou `resources/js/Pages/ProjectMgmt/`. Tool MCP `cycles-close --rollover` existe (CLI), mas sem UI Sheet/Page. PMG-009 todo. |
| **Sprint/Cycle planning UI ("Add to cycle" do Backlog)** | P1 | Não encontrado endpoint `POST /project-mgmt/cycle/{id}/add-tasks`; Backlog não tem modal Add to cycle. PMG-010 todo. |
| **Centrifugo presence — quem está vendo a tela** | P1 | grep `Centrifugo|presence|usePresence` em `Modules/ProjectMgmt/` + Pages: 0 matches. Infra Centrifugo provisionada (ADR 0058) mas zero integração nas pages do módulo. PMG-011 todo. |
| **Saved views backend (não só localStorage)** | P1 | Tabela `mcp_views` declarada na FICHA mas não populada/usada; sem endpoints CRUD `/project-mgmt/views`; filters atuais ficam em localStorage `oimpresso.board.*` (per-browser). PMG-012 todo. |
| **Triage view (tasks novas sem owner/priority)** | P1 | Sem rota `/project-mgmt/triage` em [`routes.php`](../../../Modules/ProjectMgmt/Http/routes.php); sem `TriageController`; tool MCP `triage` existe mas sem page UI. PMG-013 backlog. |
| **Custom fields per project** | P2 | Sem migration `mcp_custom_fields`; sem UI cadastro. Apenas `mcp_components` existe (categorização limitada). PMG-019 backlog. |
| **Workload view (capacidade do time)** | P2 | Sem rota `/project-mgmt/workload`; sem agregação capacity per owner/cycle. PMG-018 backlog. |
| **Time tracking interno (horas trabalhadas)** | P2 | Sem migration `mcp_time_logs` (a ficha distingue de `pjt_project_time_logs` legacy); sem Start/Stop UI; só `estimate_h` existe em `mcp_tasks`. PMG-017 backlog. |
| **Templates de epic/cycle (clone)** | P2 | Sem flag `is_template` em `mcp_cycles`/`mcp_epics`; sem endpoint POST `/from-template/{id}`. PMG-020 backlog. |
| **Automation rules (when X then Y)** | P2 | Sem migration `mcp_automation_rules`; sem engine PHP. PMG-021 backlog. |
| **Mobile responsive otimizado** | P3 | [`Board/Index.tsx`](../../../resources/js/Pages/ProjectMgmt/Board/Index.tsx#L485) usa grid fixo `repeat(N, 1fr)` sem breakpoints `sm:`/`md:`; sem touch drag-drop; sem audit Lighthouse mobile. PMG-022 backlog. |
| **Roadmap timeline drag-and-drop** | P3 | [`RoadmapController`](../../../Modules/ProjectMgmt/Http/Controllers/RoadmapController.php) renderiza grouping por quarter (read-only); sem endpoint PATCH `target_quarter`; sem drag horizontal em [`Roadmap/Index.tsx`](../../../resources/js/Pages/ProjectMgmt/Roadmap/Index.tsx). PMG-024 backlog. |
| **Public share link (read-only)** | P3 | Sem endpoint `/p/{token}`; sem UI revoke; sem LGPD review. PMG-025 backlog. |

---

## Gaps priorizados (top 10)

Ordenação: P0 endurecimento → P1 workflow Fase 3/4 → P2/P3 diferenciação. Refletem exatamente PMG-008..PMG-025 em [SPEC.md](SPEC.md).

| # | Score | Capacidade (gap) | US sugerida |
|---|---|---|---|
| 1 | **P0** | PermissionsTest cross-controller dedicado (Backlog/MyWork/Roadmap/Activity/Burndown sem perm → 403) | PMG-026 (nova) |
| 2 | **P0** | URL state lib compartilhada (`useUrlState()`) + teste E2E back/forward | PMG-027 (nova) |
| 3 | **P1** | Atalhos completos (`?` overlay help, `c` create, `e` edit) | PMG-008 (existente) |
| 4 | **P1** | Cycle close UI (Sheet retro markdown + rollover) | PMG-009 (existente) |
| 5 | **P1** | Sprint planning ("Add to cycle" do Backlog) | PMG-010 (existente) |
| 6 | **P1** | Centrifugo presence (avatar stack TopBar) | PMG-011 (existente) |
| 7 | **P1** | Saved views backend (mcp_views CRUD + sharing) | PMG-012 (existente) |
| 8 | **P1** | Triage page dedicada `/project-mgmt/triage` | PMG-013 (existente) |
| 9 | **P1** | Activity permalinks pra task + lazy load | PMG-014 (existente) |
| 10 | **P1** | Burndown multi-cycle + projection + scope_creep | PMG-015 (existente) |

Backlog não-priorizado P2/P3 (já listado em SPEC § "Fase 5 — Diferenciação"): PMG-016 (Dependencies graph), PMG-017 (Time tracking), PMG-018 (Workload), PMG-019 (Custom fields), PMG-020 (Templates), PMG-021 (Automation), PMG-022 (Mobile), PMG-023 (Dark mode toggle wiring), PMG-024 (Roadmap drag), PMG-025 (Public share).

---

## Próximos passos (sugestão de batch — Wagner aprova)

> Skill `comparativo-do-modulo` (ADR 0089) NÃO cria tasks sem confirmação humana. Lista abaixo é proposta — Wagner usa `tasks-create` no MCP pra materializar.

**Batch Fase-3 endurecimento P0 (2 tasks novas):**

- `tasks-create` PMG-026 — `chore(test): PermissionsTest cross-controller ProjectMgmt`
  - priority: p0 · estimate: 2h · type: chore · cycle: current
  - acceptance: matriz Backlog/MyWork/Roadmap/Activity/Burndown sem `copiloto.mcp.usage.all` → 403; suite verde em CI
- `tasks-create` PMG-027 — `feat(ui): useUrlState hook compartilhado + teste E2E back/forward`
  - priority: p0 · estimate: 4h · type: feature · cycle: next
  - acceptance: hook em `resources/js/Hooks/useUrlState.ts` com sync URL+localStorage; aplicado em Board+Backlog+MyWork; teste Pest cobrindo navegação back/forward

**Batch Fase-3 workflow P1 (3 tasks já existem em SPEC — só agendar):**

- PMG-008 (atalhos avançados) → `tasks-update PMG-008 cycle:current`
- PMG-009 (cycle close UI) → `tasks-update PMG-009 cycle:current`
- PMG-010 (sprint planning) → `tasks-update PMG-010 cycle:next`

**Batch Fase-4 real-time + persistência P1 (2 tasks já existem):**

- PMG-011 (Centrifugo presence) → `tasks-update PMG-011 cycle:next`
- PMG-012 (saved views backend) → `tasks-update PMG-012 cycle:next`

**Batch refinamento P1 (3 tasks já existem):**

- PMG-013 (Triage view) — quick win se Wagner abre weekly triage
- PMG-014 (Activity permalinks) — efeito UX imediato
- PMG-015 (Burndown multi-cycle) — efeito retro mensal

**P2/P3 (10 tasks já listadas em SPEC):** mantém em backlog não-comprometido conforme SPEC § "Fase 5 — Diferenciação". Re-priorizar após Fase 3+4 batido.

---

## Métricas a coletar pós-Fase 3

(Conforme FICHA § Métricas de adoção)

- Adoção time interno: ≥5 usuários distintos abrem `/project-mgmt/board` semanalmente
- Latência drag-drop status change: <300ms p95
- % de tasks com TimeLog interno: ≥40% (sinal real do Time tracking — bloqueado por PMG-017)
- Taxa de tasks completadas via Cmd+K vs mouse: >20% (telemetria `palette.opened` × `board.task.moved`)

---

## Histórico de revisão

- `2026-05-09` — [audit-constituicao] — geração via skill `comparativo-do-modulo`. Cruza FICHA (27 capacidades), SPEC (PMG-001..PMG-025) e código real em `Modules/ProjectMgmt/` (7 controllers, 6 pages Inertia, 2 test files com 52 cenários Pest). Resultado: 7 ✅ + 7 🟡 + 13 ❌. P0 100% coberto (4 ✅ + 2 🟡 — gaps de endurecimento, não funcionalidade). Top gaps casam Fase 3/4 da SPEC.
