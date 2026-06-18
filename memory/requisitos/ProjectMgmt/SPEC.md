---
module: ProjectMgmt
owner: wagner
version: "2.0.0"
last_updated: "2026-05-08"
project: PMG
default_component: UI
na_justified:
  D9.b: "ProjectMgmt sem jobs assíncronos por design (operações síncronas Kanban) — failed_jobs N/A."
---

# ProjectMgmt — SPEC

> **Status**: Fase 1 + Fase 2 entregues 2026-05-08 — 7 PMG-* mergeadas em prod.
> **Owner**: Wagner [W]
> **ADR mãe redesign**: [0100](../../decisions/0100-projectmgmt-ui-redesign.md)
> **Charter da Board**: [CHARTER-board.md](CHARTER-board.md)
> **CAPTERRA-FICHA**: [CAPTERRA-FICHA.md](CAPTERRA-FICHA.md) (24 capacidades)
> **INVENTARIO**: [INVENTARIO.md](INVENTARIO.md) (✅🟡❌ por capacidade)
> **Goal**: Linear-tier UX (≥70% fluidez Linear) em redesign incremental do `Modules/ProjectMgmt`.

## Visão

Module Jira-style já em prod desde 2026-05-04 (PRs #91/#92). Redesign UI em **4 fases capterra-driven** com gate humano entre cada uma:

- **Fase 1** — Fundamentos UX moderna (drag-drop completo + Cmd+K + tests Pest base)
- **Fase 2** — Detail Sheet + interações (sheet + @mentions + watchers + subtasks)
- **Fase 3** — Workflow + atalhos (atalhos avançados + cycle close UI + sprint planning)
- **Fase 4** — Real-time + persistence (Centrifugo presence + saved views backend)

## User stories

### Fase 1 — Fundamentos UX (P0) ✅ DONE

#### PMG-001 · Drag-drop completo (optimistic-lock 409)

> owner: wagner · priority: p0 · estimate: 6-8h · status: done · type: feature
> blocked_by: —

✅ **concluída 2026-05-07** ([PR #211](https://github.com/wagnerra23/oimpresso.com/pull/211))

- [x] BoardColumn droppable + TaskCard draggable funcionais (já existiam)
- [x] BoardController::updateStatus aceita `expected_updated_at` opcional → 409 Conflict com `current` state
- [x] serializeTask inclui `updated_at` no payload
- [x] Frontend trata 409: revert otimismo + banner amarelo "Atualizado por outro usuário" + refetch silencioso
- [x] Tests Pest: R-PMG-005 conflict + happy path com expected_updated_at correto

**Implementado em:** [`Modules/ProjectMgmt/Http/Controllers/BoardController.php`](../../../Modules/ProjectMgmt/Http/Controllers/BoardController.php) + [`resources/js/Pages/ProjectMgmt/Board/Index.tsx`](../../../resources/js/Pages/ProjectMgmt/Board/Index.tsx)

#### PMG-002 · Cmd+K Search Global

> owner: wagner · priority: p0 · estimate: 3h · status: done · type: feature
> blocked_by: —

✅ **concluída 2026-05-07** ([PR #209](https://github.com/wagnerra23/oimpresso.com/pull/209))

- [x] `Modules/ProjectMgmt/Http/Controllers/SearchController.php` — `GET /project-mgmt/search?q=` busca cross-resource (mcp_tasks/epics/cycles/projects), permission `copiloto.mcp.usage.all`, LIKE simples, agrupa por tipo, limita 10/5/5/5
- [x] `resources/js/Components/CommandPalette.tsx` — `CommandDialog` shadcn (cmdk lib) + fetch debounced 220ms + grupos com prioridade dot + status badges
- [x] Atalho global Cmd/Ctrl+K em `AppShellV2` toggle palette (listener com cleanup)
- [x] 4 cenários Pest cobrindo permission/empty/shape/match

**Implementado em:** [`Modules/ProjectMgmt/Http/Controllers/SearchController.php`](../../../Modules/ProjectMgmt/Http/Controllers/SearchController.php) + [`resources/js/Components/CommandPalette.tsx`](../../../resources/js/Components/CommandPalette.tsx)

#### PMG-003 · Tests Pest base

> owner: wagner · priority: p0 · estimate: 2h · status: done · type: chore
> blocked_by: —

✅ **concluída 2026-05-07** ([PR #207](https://github.com/wagnerra23/oimpresso.com/pull/207))

- [x] `Modules/ProjectMgmt/Tests/Feature/BoardControllerTest.php` — 6 cenários iniciais (403 GET/PATCH, 200 happy, 422, 404, audit log)
- [x] Diretório `Modules/ProjectMgmt/Tests/Feature` registrado em `phpunit.xml`
- [x] Padrão Repair/Whatsapp + helpers (`pmgBootstrapUser`, `pmgGivePerm`, `pmgEnsureProject`, `pmgCreateTask`)

**Implementado em:** [`Modules/ProjectMgmt/Tests/Feature/BoardControllerTest.php`](../../../Modules/ProjectMgmt/Tests/Feature/BoardControllerTest.php)

### Fase 2 — Detail Sheet + interações (P1) ✅ DONE

#### PMG-004 · Detail Sheet completo (foundation Fase 2)

> owner: wagner · priority: p1 · estimate: 4-6h · status: done · type: feature
> blocked_by: PMG-003

✅ **concluída 2026-05-08** ([PR #220](https://github.com/wagnerra23/oimpresso.com/pull/220))

- [x] `BoardController::show($taskId)` — `GET /project-mgmt/board/{taskId}/detail` retorna `{task, comments, events, subtasks, dependencies}`. Eager load comments (≤100), events (≤50), subtasks (parent_task_id), dependencies + target map
- [x] `resources/js/Pages/ProjectMgmt/Board/DetailSheet.tsx` — Sheet shadcn slide-in à direita, w-2xl, overflow-y-auto. Header com display_id + priority dot + status badge + title + meta. 4 tabs state-driven (sem Tabs primitive nova): Description / Comments / Activity / Subtasks. Counts inline.
- [x] Click card → URL `?task=ID` via `window.history.replaceState` (preserveState/Scroll implícito)
- [x] 3 cenários Pest (403, 404, happy)

#### PMG-005 · @mentions em comments (form add inline)

> owner: wagner · priority: p1 · estimate: 3h · status: done · type: feature
> blocked_by: PMG-004

✅ **concluída 2026-05-08** ([PR #222](https://github.com/wagnerra23/oimpresso.com/pull/222))

- [x] **Backend foundation já existia** em `TaskCrudService::comment()` (regex `/@([a-z][a-z0-9_-]+)/i` + `McpInboxNotification::notify()` dispatch)
- [x] `BoardController::addComment` — `POST /project-mgmt/board/{taskId}/comment`, validação body required/min:1/max:5000
- [x] `BoardController::suggestUsers` — `GET /project-mgmt/board/users/suggest?q=`, autocomplete users com permission `copiloto.mcp.usage.all`, LIKE em username/first_name/last_name, limit 10
- [x] `resources/js/Components/MentionInput.tsx` — textarea com trigger '@' + autocomplete debounced 180ms + ↑↓ navegar + Enter/Tab completar + Esc fechar + Cmd+Enter enviar
- [x] DetailSheet tab Comments com form inline + Button Send + handlePostComment otimista
- [x] 5 cenários Pest

#### PMG-006 · Watchers UI (Follow/Unfollow)

> owner: wagner · priority: p1 · estimate: 2h · status: done · type: feature
> blocked_by: PMG-004

✅ **concluída 2026-05-08** ([PR #224](https://github.com/wagnerra23/oimpresso.com/pull/224))

- [x] `Modules/Jana/Entities/Mcp/McpTaskWatcher.php` — Model novo (id, task_id, user_id, timestamps + relation user belongsTo). Tabela `mcp_task_watchers` já existia desde Migration 2026_05_04_180011
- [x] `BoardController::watch` (POST) + `BoardController::unwatch` (DELETE) — idempotentes (firstOrCreate / delete)
- [x] `BoardController::show` extended — payload agora inclui `watchers[]` + `is_watching:bool`
- [x] DetailSheet tab Watchers com card Seguir/Parar de seguir + lista de followers
- [x] 5 cenários Pest

#### PMG-007 · Subtasks UI (create + toggle status)

> owner: wagner · priority: p1 · estimate: 3h · status: done · type: feature
> blocked_by: PMG-004

✅ **concluída 2026-05-08** ([PR #226](https://github.com/wagnerra23/oimpresso.com/pull/226))

- [x] `BoardController::addSubtask` — `POST /project-mgmt/board/{taskId}/subtask`, valida title required min:1 max:255, chama `TaskCrudService::create()` com `parent_task_id` + project key + cycle/epic herdados
- [x] DetailSheet tab Subtasks com checkboxes clicáveis (toggla status todo↔done via PATCH otimista) + form add inline (Enter envia, Plus button)
- [x] Done = riscado + opacity-60. Loading spinner durante toggle
- [x] 4 cenários Pest

### Fase 3 — Workflow + atalhos (P1) 🔲 TODO

#### PMG-008 · Atalhos keyboard avançados (overlay help + chord)

> owner: wagner · priority: p1 · estimate: 3h · status: todo · type: feature
> blocked_by: —

- [ ] Atalho `?` abre overlay com lista de shortcuts (J/K/E/A/C/Cmd+K/?)
- [ ] Atalho `c` cria task na coluna ativa (foco no input)
- [ ] Atalho `e` editar task selecionada (abre Detail Sheet em modo edit description) — depende PMG-004
- [ ] Garantir preventDefault de combos browser (Cmd+K conflict com URL bar Chrome)
- [ ] Tests Pest (~não aplicável; teste manual)

#### PMG-009 · Cycle close UI (retro markdown + rollover)

> owner: wagner · priority: p1 · estimate: 3h · status: todo · type: feature
> blocked_by: —

- [ ] Sheet/Page com tabs Incompletas / Retro / Confirm
- [ ] Lista incompletas + checkbox rollover individual
- [ ] Textarea retro markdown (salva em `mcp_cycles.retro` JSON)
- [ ] Botão Confirm fecha cycle + move incompletas marcadas pro próximo
- [ ] Reusa tool MCP `cycles-close --rollover` existente
- [ ] Tests Pest +3 cenários

#### PMG-010 · Sprint planning Modal ("Add to cycle" do Backlog)

> owner: wagner · priority: p1 · estimate: 2h · status: todo · type: feature
> blocked_by: —

- [ ] Modal abre do Backlog com multi-select tasks
- [ ] Botão "Add to cycle" + dropdown cycle ativo/planning
- [ ] Endpoint `POST /project-mgmt/cycle/{id}/add-tasks` body `{task_ids: []}`
- [ ] Tests Pest +3 cenários

### Fase 4 — Real-time + persistence (P1) 🔲 TODO

#### PMG-011 · Centrifugo presence (avatar stack TopBar)

> owner: wagner · priority: p1 · estimate: 3h · status: todo · type: feature
> blocked_by: —

- [ ] Hook `usePresence(canal)` em `resources/js/Hooks/`
- [ ] Canal `project-mgmt:board:{cycle_id}`
- [ ] Avatar stack no TopBar do Board mostra outros users conectados
- [ ] Teardown em unmount (leave channel)
- [ ] Tests E2E Pest 1 cenário (mock Centrifugo connect)

#### PMG-012 · Saved views backend (mover localStorage → mcp_views)

> owner: wagner · priority: p1 · estimate: 3h · status: todo · type: feature
> blocked_by: —

- [ ] Endpoints CRUD `/project-mgmt/views` (POST create, GET list, PATCH update, DELETE remove)
- [ ] UI 'Save view' + 'My views' + 'Shared' no FilterBar do Board
- [ ] Migration: nada (tabela `mcp_views` já existe)
- [ ] Tests Pest +4 cenários

### Fase 5 — Diferenciação (P2/P3) — backlog não-comprometido

> Detalhes em [INVENTARIO.md § Fase 5](INVENTARIO.md). Só entram se Fase 1-4 mostrarem ROI.

- PMG-013 Triage view dedicada (P2)
- PMG-014 Activity feed filtros + permalinks (P2)
- PMG-015 Burndown multi-cycle + scope_creep (P2)
- PMG-016 Dependencies graph (P2)
- PMG-017 Time tracking interno (P2)
- PMG-018 Workload view (P2)
- PMG-019 Custom fields per project (P2)
- PMG-020 Templates de epic/cycle (P2)
- PMG-021 Automation rules (P2)
- PMG-022 Mobile responsive (P3)
- PMG-023 Dark mode toggle (P3)
- PMG-024 Roadmap timeline drag (P3)
- PMG-025 Public share link (P3)

---

## Onda 2 — Triage + Inbox (US-TR-301..308 · SPEC-UI-FASE7)

**Implementado em:** `Modules/ProjectMgmt/Http/Controllers/TriageController.php` · `resources/js/Pages/ProjectMgmt/Triage/Index.tsx` · verificado@98cae0a (2026-06-18)

> Superfícies humanas das tools MCP `triage` e `my-inbox`. Telas: `resources/js/Pages/ProjectMgmt/{Triage,Inbox}/Index.tsx`.
> **PR #1940 — code-complete, segue DRAFT** aguardando gate visual do Wagner (ADR 0107/0114; Chrome MCP off).
> Fonte funcional: [`TaskRegistry/SPEC-UI-FASE7.md`](../TaskRegistry/SPEC-UI-FASE7.md). RUNBOOK: [`RUNBOOK-index.md`](RUNBOOK-index.md). Visual: [`projectmgmt-index-visual-comparison.md`](projectmgmt-index-visual-comparison.md) (status draft).

### US-TR-301 · Triage — lista de tasks órfãs

> owner: wagner · priority: p1 · estimate: codável (fator 10x) · status: review · type: feature

Como membro do time, vejo uma tela **Triage** (`/project-mgmt/triage`) com todas as tasks órfãs (sem owner OU sem prioridade OU em backlog). A lista = MESMO conjunto que a tool MCP `triage` (scope `McpTask::triage()`, exclui done/cancelled). Vazio → empty state **"Nada pra triar"** (sem emoji — AP). Implementado em [`Triage/Index.tsx`](../../../resources/js/Pages/ProjectMgmt/Triage/Index.tsx) + [`TriageController`](../../../Modules/ProjectMgmt/Http/Controllers/TriageController.php).

### US-TR-302 · Triage — atribuir owner + prioridade inline

**Implementado em:** `Modules/ProjectMgmt/Http/Controllers/TriageController.php` · `resources/js/Pages/ProjectMgmt/Triage/Index.tsx` · verificado@98cae0a (2026-06-18)

> owner: wagner · priority: p1 · estimate: codável · status: review · type: feature

Na Triage, atribuo **owner + prioridade inline** sem abrir a task: select inline → `PATCH /triage/{taskId}/assign` (reusa `TaskCrudService::update`, mesma via da tool `tasks-update`) → UI otimista + rollback em erro; gera `mcp_task_events` + notifica o novo owner.

### US-TR-303 · Triage — mover cycle/epic

**Implementado em:** `Modules/ProjectMgmt/Http/Controllers/TriageController.php` · `resources/js/Pages/ProjectMgmt/Triage/Index.tsx` · verificado@98cae0a (2026-06-18)

> owner: wagner · priority: p2 · estimate: codável · status: review · type: feature

Na Triage, movo a task pra um **cycle/epic** opcionalmente (dropdowns na mesma linha); persiste; a task **some da lista** quando deixa de ser órfã (`still_triage=false`).

### US-TR-304 · Inbox — lista de não-lidas

**Implementado em:** `Modules/ProjectMgmt/Http/Controllers/InboxController.php` · `resources/js/Pages/ProjectMgmt/Inbox/Index.tsx` · verificado@98cae0a (2026-06-18)

> owner: wagner · priority: p1 · estimate: codável · status: review · type: feature

Como membro, vejo uma tela **Inbox** (`/project-mgmt/inbox`) com minhas notificações: lê `mcp_inbox_notifications WHERE user_id=me` (não-lidas por default), **agrupado por tipo**. Paridade com a tool MCP `my-inbox`. Implementado em [`Inbox/Index.tsx`](../../../resources/js/Pages/ProjectMgmt/Inbox/Index.tsx) + [`InboxController`](../../../Modules/ProjectMgmt/Http/Controllers/InboxController.php).

### US-TR-305 · Inbox — marcar lido (individual + todas)

**Implementado em:** `Modules/ProjectMgmt/Http/Controllers/InboxController.php` · `resources/js/Pages/ProjectMgmt/Inbox/Index.tsx` · verificado@98cae0a (2026-06-18)

> owner: wagner · priority: p1 · estimate: codável · status: review · type: feature

No Inbox, **marco como lido** individual (`PATCH /inbox/{id}/read`) e "marcar todas" (`PATCH /inbox/read-all`), otimista com rollback. Escopo `user_id` (Tier 0). Badge realtime via Centrifugo ([ADR 0058](../../decisions/0058-reverb-substituido-por-centrifugo-frankenphp.md)) fica pra fase seguinte (polling 30s cobre agora).

### US-TR-306 · Inbox — deep-link pra task/DetailSheet

**Implementado em:** `Modules/ProjectMgmt/Http/Controllers/InboxController.php` · `resources/js/Pages/ProjectMgmt/Inbox/Index.tsx` · verificado@98cae0a (2026-06-18)

> owner: wagner · priority: p1 · estimate: codável · status: review · type: feature

No Inbox, clico (ou Enter) numa notificação e vou direto pra **task** no Board com o `DetailSheet` aberto (`/project-mgmt/board?task=ID`), marcando lido no caminho.

### US-TR-307 · Operador não-técnico usa sem treino

**Implementado em:** `Modules/ProjectMgmt/Http/Controllers/BoardController.php` · `Modules/ProjectMgmt/Http/Controllers/TriageController.php` · `Modules/ProjectMgmt/Http/Controllers/InboxController.php` · `resources/js/Pages/ProjectMgmt/Board/Index.tsx` · `resources/js/Pages/ProjectMgmt/Triage/Index.tsx` · `resources/js/Pages/ProjectMgmt/Inbox/Index.tsx` · verificado@98cae0a (2026-06-18)

> owner: wagner · priority: p2 · estimate: codável · status: review · type: feature

Como operador **não-técnico**, uso Board/Backlog/Triage/Inbox sem treino: labels PT-BR claros, empty states **sem emoji**, foco-teclado (J/K + Enter + ⌘K palette global), toque-friendly ≥360px. A revisar por `design:accessibility-review` no gate visual.

### US-TR-308 · Chips de ADRs/SPECs relacionados (memory-linked)

> owner: wagner · priority: p2 · estimate: codável · status: todo · type: feature

Vejo no card os **ADRs/SPECs relacionados** (diferencial memory-linked): `mcp_task_memory_links` como chips no card/DetailSheet. Reusa o que o DetailSheet do Board já faz. **Não** entregue nesta Onda 2 (vive no Board) — registrado como gap.

---

## Regras de negócio (Gherkin)

### R-PMG-001 · Permission gate `copiloto.mcp.usage.all`

```gherkin
Dado que um usuário NÃO tem permission `copiloto.mcp.usage.all`
Quando ele acessa qualquer endpoint do BoardController/SearchController
Então recebe 403 Unauthorized
```

**Implementado em:** Middleware `can:copiloto.mcp.usage.all` no constructor dos Controllers.
**Testado em:** 6+ cenários `BoardControllerTest::*sem permission*`.

### R-PMG-002 · Multi-tenant não aplicável (governance)

```gherkin
Dado que mcp_* são tabelas de governance (não business)
Quando endpoints retornam dados de mcp_tasks/comments/etc
Então não aplica filter business_id (mcp_* não tem essa coluna)
```

**Justificativa:** ADR 0070 § "tasks são governance, não scoped por business_id".

### R-PMG-005 · Drag-drop concorrente preserva integridade (PMG-001)

```gherkin
Dado que dois usuários têm `/project-mgmt/board` aberto
Quando ambos arrastam o mesmo card simultaneamente para colunas diferentes
Então o segundo PATCH (com expected_updated_at obsoleto) retorna 409 Conflict com `current` state
E o frontend do segundo usuário reverte otimismo + mostra banner + refeta silencioso
```

**Implementado em:** [`BoardController::updateStatus`](../../../Modules/ProjectMgmt/Http/Controllers/BoardController.php) + `Board/Index.tsx`.
**Testado em:** `BoardControllerTest::R-PMG-005: PATCH com expected_updated_at obsoleto retorna 409`.

---

## Status

- **Última atualização**: 2026-05-08 — Fase 2 completa (PRs #220 #222 #224 #226 mergeadas)
- **Owner produto**: [W]
- **Cobertura Pest atual**: 27 testes em `Modules/ProjectMgmt/Tests/Feature/BoardControllerTest.php` + `SearchControllerTest.php`
- **Próximo passo**: Fase 3 (PMG-008/009/010) ~6-8h ou pausar pra Wagner validar Fase 2 em prod

## Métricas (a coletar pós-Fase 2)

- Wagner usa Cmd+K ≥5×/dia per user ativo (telemetria `palette.opened`)
- Wagner usa drag-drop ≥10×/dia (telemetria `board.task.moved`)
- ≥80% das sessões de dev usam atalho J/K (telemetria `hotkey.fired`)
- Notification dispatch via @mention dispara ≥3 vezes em prod (validação backend)
- ≥3 watchers reais cadastrados em tasks ativas
- ≥5 subtasks criadas em tasks reais

## Referências

- [ADR 0070](../../decisions/0070-jira-style-task-management-current-md-removed.md) — Jira-style task management (escopo do módulo)
- [ADR 0099](../../decisions/0099-project-legacy-discovery-pre-deletion.md) — Discovery legacy `Modules/Project` queue-for-delete
- [ADR 0100](../../decisions/0100-projectmgmt-ui-redesign.md) — ProjectMgmt UI Redesign 4 fases
- [CHARTER-board.md](CHARTER-board.md) — anatomia + personas + fluxos + anti-padrões
- [INVENTARIO.md](INVENTARIO.md) — gap analysis ✅🟡❌
- [CAPTERRA-FICHA.md](CAPTERRA-FICHA.md) — 24 capacidades P0-P3
- [`memory/requisitos/TaskRegistry/SPEC.md`](../TaskRegistry/SPEC.md) — SPEC funcional histórico (US-TR-NNN; renaming pendente Fase 3.9)
- PRs cadeia: [#197](https://github.com/wagnerra23/oimpresso.com/pull/197) [#202](https://github.com/wagnerra23/oimpresso.com/pull/202) [#205](https://github.com/wagnerra23/oimpresso.com/pull/205) [#207](https://github.com/wagnerra23/oimpresso.com/pull/207) [#209](https://github.com/wagnerra23/oimpresso.com/pull/209) [#211](https://github.com/wagnerra23/oimpresso.com/pull/211) [#220](https://github.com/wagnerra23/oimpresso.com/pull/220) [#222](https://github.com/wagnerra23/oimpresso.com/pull/222) [#224](https://github.com/wagnerra23/oimpresso.com/pull/224) [#226](https://github.com/wagnerra23/oimpresso.com/pull/226)
