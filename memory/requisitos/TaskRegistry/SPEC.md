---
module: TaskRegistry
version: "1.0"
last_updated: "2026-06-13"
owner: wagner
status: ativo
project: TR
default_component: BE
---

# TaskRegistry — SPEC

> **Status**: ADR 0070 entregue 2026-05-04 — Jira-style task management vivo
> **Owner**: Wagner [W]
> **ADR canônico**: [0070](../../decisions/0070-jira-style-task-management-current-md-removed.md) (supersede 0069)
> **Goal**: source-of-truth único pra tasks/cycles, hierarquia profissional, AI-native

## Visão

Sistema de gerenciamento de tasks profissional ("Jira-like") nativo no MCP server oimpresso. Hierarquia completa Project → Epic → Cycle → Story → Subtask + Components transversais.

- **Source-of-truth absoluto**: tabelas `mcp_*` no MCP server. CURRENT.md/TASKS.md REMOVIDOS.
- **SPECs canônicos por módulo** em `memory/requisitos/<Mod>/SPEC.md` (US-XXX-NNN parseadas → mcp_tasks via webhook).
- **Acesso programático**: 24+ tools MCP (humanos + agentes IA).
- **UI futura**: `/copiloto/admin/board` Kanban+Backlog+Roadmap+Triage+Inbox (Fase 7 — sessão futura).

## Hierarquia (ADR 0070)

```
Project (mcp_projects)              ex: COPI, NFSE, FIN
  ├── Epic (mcp_epics)              agrupamento por iniciativa/tema
  ├── Component (mcp_components)    cross-cut (Frontend/Backend/Infra/Mem)
  ├── Cycle (mcp_cycles)            sprint 2 semanas com goal outcome-oriented
  │   └── CycleGoal (mcp_cycle_goals)  métricas trackáveis
  └── Task (mcp_tasks)
        ├── Subtask (mcp_tasks parent_task_id)
        ├── Comments (mcp_task_comments)
        ├── Events (mcp_task_events)            audit append-only
        ├── Dependencies (mcp_task_dependencies) blocks/relates/duplicates/clones
        ├── Watchers (mcp_task_watchers)
        ├── Attachments (mcp_task_attachments)
        ├── Memory Links (mcp_task_memory_links) D1 — task ↔ ADR/SPEC/session
        └── Git Links (mcp_git_links)            commit/PR ↔ task bidirecional
```

## Format canônico de US no SPEC.md

Cada SPEC pode ter frontmatter YAML opcional no topo (defaults globais):

```yaml
---
project: COPI
default_epic: COPI-EP-001
default_component: BE
default_cycle: CYCLE-01
---
```

Cada US:

```markdown
### US-NFSE-001 · Pesquisa fiscal Tubarão

> owner: eliana · sprint: A · priority: p0 · estimate: 8h · status: todo · type: bug
> epic: COPI-EP-001 · cycle: CYCLE-01 · component: BE
> story_points: 5 · due: 2026-05-09 · labels: lgpd,perf
> blocked_by: US-NFSE-000
> identifier: NFSE-42

- [ ] Confirmar SN-NFSe vs ABRASF
- [ ] Cadastrar conta provider
- [ ] Documentar resultado em PESQUISA_TUBARAO.md
```

Campos suportados pelo parser (todos opcionais exceto título no heading):

| Campo            | Valores aceitos                                                    | Default      |
|------------------|--------------------------------------------------------------------|--------------|
| `owner`          | username (auto-lowercase)                                          | null         |
| `sprint`         | string livre                                                       | null         |
| `priority`       | `p0` `p1` `p2` `p3`                                                | `p2`         |
| `status`         | `backlog` `todo` `doing` `review` `done` `blocked` `cancelled`     | `todo`       |
| `type`           | `story` `task` `bug` `spike` `chore` `epic-stub`                   | `story`      |
| `estimate`       | número + unidade hora (8h, 0.5h)                                   | null         |
| `story_points`   | número                                                             | null         |
| `due` / `prazo`  | data parseável (Carbon)                                            | null         |
| `labels` / `tags`| CSV (lgpd,perf)                                                    | null         |
| `epic`           | epic key (ex: COPI-EP-001) — resolvido para epic_id                | herda global |
| `cycle`          | cycle key (ex: CYCLE-01) — resolvido para cycle_id                 | herda global |
| `component`      | component key (ex: BE) — resolvido para component_id               | herda global |
| `identifier`     | Linear-style (ex: NFSE-42) — força identifier humano               | auto-aloca   |
| `blocked_by`     | `US-XXX-NNN, US-YYY-MMM`                                           | null         |
| **outras**       | qualquer chave não-canônica vai pra `custom_fields` JSON           | —            |

## Fluxo padrão de operação

| Operação                         | Tool MCP                                              | Quando                              |
|----------------------------------|-------------------------------------------------------|-------------------------------------|
| Estado vivo do cycle             | `cycles-active`                                       | "O que tá rolando agora?"           |
| Minhas tasks ativas              | `my-work`                                             | Início do dia                       |
| Caixa de entrada                 | `my-inbox`                                            | @mentions / assignments / reviews   |
| Triagem                          | `triage`                                              | Tasks novas sem owner/prio          |
| Goals do cycle                   | `cycle-goals-track`                                   | Status semanal das métricas         |
| Backlog filtrável                | `tasks-list module:X status:Y owner:Z`                | Pegar próxima task                  |
| Detalhe + timeline               | `tasks-detail task_id:COPI-123`                       | Ler histórico/comments/deps         |
| Criar nova task                  | `tasks-create module:X title:"..."`                   | Trabalho novo                       |
| Atualizar status                 | `tasks-update <ID> status:doing`                      | Daily progress                      |
| Comentar                         | `tasks-comment <ID> comment:"..."`                    | Discussão thread                    |
| Atribuir + watchers              | `tasks-assign <ID> owner:wagner watchers:[1,2]`       | Quando puxa do triage               |
| Mover entre cycles               | `tasks-move <ID> cycle_id:N`                          | Replanejamento                      |
| Linkar dependências              | `tasks-link <ID> blocks:OTHER_ID`                     | Modelar bloqueios                   |
| Bulk update                      | `tasks-bulk-update task_ids:[...] fields:{...}`       | Ajustar 10 tasks de uma vez         |
| Fechar cycle (com rollover)      | `cycles-close --rollover-to=CYCLE-02`                 | Sex final do cycle                  |
| Burndown                         | `dashboard-burndown cycle:current`                    | Acompanhar velocity                 |
| Velocity                         | `dashboard-velocity project:X`                        | Capacity planning próximo cycle     |

## Diferenciais oimpresso-only (D1, D2)

Linear/Jira NÃO têm out-of-box:

- **D1 — Memory-linked tasks**: `mcp_task_memory_links` liga task ↔ ADRs/SPECs/sessions/comparativos. Tool `tasks-link-memory` (manual) e `tasks-suggest-memory` (AI auto-sugere via Copiloto LLM).
- **D2 — AI-native tools**: `tasks-summarize` (resume thread), `tasks-suggest-priority` (analisa contexto + due_date + deps), `tasks-suggest-blockers` (varre dependency graph). Usa Copiloto LLM já em prod.
- **D3 — Multi-tenant business_id**: cada cliente B2B (futuro) tem board próprio (global scope tipo UltimatePOS).
- **D4 — Cross-system audit**: `mcp_task_events` une com `mcp_audit_log` MCP. Qualquer agente IA que mexer fica auditado.
- **D5 — MCP-first**: agentes IA criam/movem/comentam via tools antes de UI existir.

## User stories — ADR 0070 entrega

### US-TR-101 · Schema Jira-style (15 migrations + 8 entities)

> owner: wagner · sprint: 2026-W18 · priority: p0 · estimate: 3h · status: done · type: chore
> epic: TR-EP-002 · component: BE · labels: schema,adr-0070

15 migrations criam: mcp_projects, mcp_epics, mcp_cycles, mcp_cycle_goals, mcp_components, mcp_workflows, mcp_issue_templates, mcp_views, mcp_inbox_notifications, mcp_task_dependencies, mcp_task_watchers, mcp_task_attachments, mcp_task_memory_links, mcp_git_links + ALTER mcp_tasks (identifier, project_id, epic_id, cycle_id, component_id, parent_task_id, type, story_points, estimate_unit, due_date, started_at, completed_at, labels, custom_fields).

8 Entities criadas: McpProject, McpEpic, McpCycle, McpCycleGoal, McpComponent, McpInboxNotification, McpTaskDependency + McpTask estendido com novos casts/scopes (active/open/triage/overdue) e helper getDisplayIdAttribute (Linear-style ID).

### US-TR-102 · Parser SPEC.md estendido

> owner: wagner · sprint: 2026-W18 · priority: p0 · estimate: 1h · status: done · type: task

TaskParserService aceita frontmatter YAML global + por-US (epic, cycle, component, story_points, due, labels, type, identifier). Resolve keys → IDs via cache em-memória. Backwards-compatible com formato antigo.

### US-TR-103 · CRUD service estendido

> owner: wagner · sprint: 2026-W18 · priority: p0 · estimate: 1.5h · status: done · type: task

TaskCrudService.update com whitelist expandida (15 campos). Métodos novos: `move`, `link`, `assign`, `bulkUpdate`. Side-effects automáticos: started_at quando status→doing, completed_at quando status→done. @mentions detectadas → mcp_inbox_notifications.

### US-TR-104 · 7 tools MCP novas + alias deprecated

> owner: wagner · sprint: 2026-W18 · priority: p0 · estimate: 2h · status: done · type: task

Novas tools: `cycles-active`, `my-work`, `my-inbox`, `triage`, `cycle-goals-track`, `cycles-close`. Alias deprecated: `tasks-current` (redireciona pra cycles-active).

### US-TR-105 · McpDefaultsSeeder + Backfill command

> owner: wagner · sprint: 2026-W18 · priority: p0 · estimate: 2h · status: done · type: chore

`McpDefaultsSeeder` cria 18 projects canônicos + 1 workflow global default. `mcp:tasks:backfill-from-markdown` popula Cycle 01 ativo + 3 goals + ~70 tasks (Cycle 01 done/active + on-deck Cycle 02 + backlog por módulo) baseado em snapshot do CURRENT.md/TASKS.md (data hardcoded no PHP — não depende dos arquivos após exclusão).

### US-TR-106 · Pest test schema

> owner: wagner · sprint: 2026-W18 · priority: p1 · estimate: 0.5h · status: done · type: test

Suite `JiraStyleSchemaTest.php` valida CRUD básico, allocateNextIdentifier sequencial atômico, scopes (active/open/triage), inbox notify, task dependencies, lifecycle automático (started_at/completed_at).

## User stories — Cycle 02+ (Fase 7 UI Web)

### US-TR-201 · Page /copiloto/admin/board (Kanban)

> owner: wagner · priority: p1 · estimate: 4d · status: backlog · type: story
> epic: TR-EP-003 · component: FE · labels: ui,kanban

Kanban view com drag-drop entre colunas (workflow customizável por projeto). Filtro por epic/component/owner/cycle. Bulk select pra mover N tasks. Real-time presence via Centrifugo (ADR 0058) quando 2+ devs editando o mesmo board.

### US-TR-202 · Backlog view (lista filtrável + bulk edit)

> owner: wagner · priority: p1 · estimate: 2d · status: backlog · type: story
> epic: TR-EP-003 · component: FE

Tabela DataTable com filtros profissionais, bulk edit (10 tasks selecionadas → mover/atribuir/labels). Saved views como tabs (`triage`, `my-work`, custom).

### US-TR-203 · Roadmap view (epics em quarters)

> owner: wagner · priority: p2 · estimate: 2d · status: backlog · type: story
> epic: TR-EP-003 · component: FE

Visão temporal: epics agrupados por target_quarter, com cycles dentro. Útil pra planejamento estratégico cross-cycle.

### US-TR-204 · My Work + Inbox homepage

> owner: wagner · priority: p1 · estimate: 1.5d · status: backlog · type: story
> epic: TR-EP-003 · component: FE

Homepage por user logado: "Minhas tasks ativas" + "Inbox unread" + "Próximos due_date" + "Bloqueadores meus". Renderiza my-work + my-inbox tools.

### US-TR-205 · Activity feed timeline

> owner: wagner · priority: p2 · estimate: 1d · status: backlog · type: story

Stream cronológico de mcp_task_events com filtros (tipo, autor, projeto). Shape Linear-style.

### US-TR-206 · Burndown chart

> owner: wagner · priority: p2 · estimate: 1d · status: backlog · type: story
> labels: dashboard,metrics

Chart line do cycle ativo: total story_points vs done over time. Comparado com ideal burndown.

## User stories — Bidirectional Git Sync (Fase 6 entregue)

### US-TR-301 · Webhook GitHub commit→task auto ✅

> owner: wagner · sprint: 2026-W18 · priority: p1 · estimate: 1.5d · status: done · type: feature
> epic: TR-EP-004 · component: BE · labels: webhook,git

`GitTaskLinkerService` detecta padrão `(refs|fixes|closes|resolves)\s+([A-Z]{2,8}-\d+)` em commit messages e branch names. Cria `mcp_git_links` row idempotente + comment auto na task. Status auto:
- `fixes/closes/resolves` em push pra main → status=done + completed_at
- `fixes/closes/resolves` em branch feature → status=review
- `refs` apenas → cria link sem mudança de status
- PR opened (qualquer branch) → status=review
- PR merged em main/master → status=done

`SyncMemoryWebhookController` roteado por `X-GitHub-Event` header: `push` (default + sync memory) ou `pull_request` (só PR handler). Idempotência: mesmo commit_sha+task+action = no-op no replay.

Pest: `GitTaskLinkerServiceTest` cobre 9 cenários (parsing regex, idempotência, push em main vs branch, PR opened/merged).

### US-TR-302 · tasks-suggest-* (D2 AI-native)

> owner: wagner · priority: p2 · estimate: 1d · status: backlog · type: feature
> labels: ai,copiloto

Tools `tasks-summarize-comments` (resume thread > 10 comments), `tasks-suggest-priority` (analisa due/deps/labels), `tasks-suggest-memory` (varre mcp_memory_documents por similaridade). Usa LaravelAiSdkDriver com gpt-4o-mini.

### US-TR-303 · Fase 3.8 — Deletar Modules/Project legacy (UltimatePOS Project queue-for-delete)

> owner: wagner · priority: p1 · estimate: 3h · status: todo · type: chore
> labels: cleanup,legacy,blocker-3.9
> blocked_by: —
> blocks: rename `Modules/ProjectMgmt → Modules/Project` (Fase 3.9 do ADR 0079)

Wagner 2026-05-07: "acho que não tem nada no project muito simples acho que só o cliente mesmo" — confirmado que provavelmente não há dado de valor pra extrair antes do delete (UltimatePOS Project legacy gerencia projetos de cliente; ROTA LIVRE provavelmente nem usa).

**Acceptance criteria:**

- [ ] Auditoria rápida em prod: query `SELECT count(*) FROM pjt_projects WHERE business_id=4` (ROTA LIVRE) — confirma uso real
- [ ] Se >0 projetos com dado real: extrair (TimeLogs/Invoices/ClientProjects) — decidir destino (Financeiro Receivable? notas?)
- [ ] Se =0 ou só dummy: pular extração
- [ ] Migration nova `drop_pjt_tables.php` com `down()` que recria estrutura (rollback safety)
- [ ] `git rm -rf Modules/Project/`
- [ ] Remover registro em `modules_statuses.json` + qualquer reference em `phpunit.xml`
- [ ] Remover routes/topnav reference se houver
- [ ] PR com 1 commit `chore(project): delete legacy UltimatePOS Project module (Fase 3.8)`
- [ ] Validar prod: rota `/project/*` retorna 404 (esperado)

**Insumo preservado** (ver PR #197 — discovery pré-deletion preservado com disclaimer):

- `memory/requisitos/Project/CAPTERRA-FICHA.md` — 24 capacidades inventariadas
- `memory/requisitos/Project/CHARTER-board.md` — referência UX
- `memory/requisitos/Project/SPEC.md` — 15 US documentadas
- `memory/decisions/0099-project-mwart-migration.md` (status `pivotado`) — discovery pré-deletion

**Refs:** ADR 0079 Fase 3.8 · ADR 0099 (legacy discovery) · [`Modules/ProjectMgmt/SCOPE.md`](../../../Modules/ProjectMgmt/SCOPE.md) · [PR #197](https://github.com/wagnerra23/oimpresso.com/pull/197)

## Migração e fluxos críticos

### Como rodar pela 1ª vez (Wagner)

```bash
# 1. Pull do branch
git pull

# 2. Aplicar migrations (15 novas + 1 alter)
php artisan migrate

# 3. Atualizar permissions (4 novas: tasks.write, cycles.manage, projects.manage, inbox.read)
php artisan db:seed --class=Modules\\Jana\\Database\\Seeders\\McpScopesSeeder

# 4. Criar 18 projects canônicos + workflow default global
php artisan db:seed --class=Modules\\Jana\\Database\\Seeders\\McpDefaultsSeeder

# 5. Backfill 1× — Cycle 01 + 3 goals + ~70 tasks (Cycle 01 done/active + on-deck Cycle 02 + backlog por módulo)
php artisan mcp:tasks:backfill-from-markdown
# Ou pra ver antes: php artisan mcp:tasks:backfill-from-markdown --dry-run

# 6. Sincronizar SPECs canônicos (US-XXX-NNN dos memory/requisitos/<Mod>/SPEC.md)
php artisan mcp:tasks:sync

# 7. Atribuir permissões novas a Admin#1
php artisan tinker
> Spatie\Permission\Models\Role::findByName('Admin#1')->givePermissionTo([
>   'copiloto.mcp.tasks.write',
>   'copiloto.mcp.cycles.manage',
>   'copiloto.mcp.projects.manage',
> ]);

# 8. Validar via tools MCP
php artisan tinker
> app(\Modules\Jana\Mcp\Tools\CyclesActiveTool::class)->handle(...)
```

### Validação pós-migração

```bash
# Smoke tests Pest
php artisan test --filter=JiraStyleSchemaTest

# Smoke tools via curl
curl -s -X POST https://mcp.oimpresso.com/api/mcp \
  -H "Authorization: Bearer mcp_<TOKEN>" \
  -H "Content-Type: application/json" \
  -H "Accept: application/json, text/event-stream" \
  -d '{"jsonrpc":"2.0","id":1,"method":"tools/call","params":{"name":"cycles-active","arguments":{"project":"COPI"}}}'
```

## Aceitação ADR 0070

- ✅ CURRENT.md REMOVIDO do repo
- ✅ TASKS.md REMOVIDO do repo
- ✅ memory/cycles/README.md REMOVIDO (cycles fechados são entidades)
- ✅ ADR 0070 commitada com supersedes:[0069]
- ✅ ADR 0069 marcada superseded_by:[0070]
- ✅ CLAUDE.md atualizada (§2 §3 §6 §10) — refs Jira-style
- ✅ TEAM.md atualizada (§inicial e §5)
- ✅ Skills atualizadas: continuar, sync-mem, memory-sync, oimpresso-mcp-first, oimpresso-stack, oimpresso-team-onboarding, publication-policy
- ✅ Hooks atualizados: memory-pending.ps1, mcp-first-warning.ps1
- ✅ settings.json SessionStart atualizado
- ✅ .mcp.json comentário atualizado
- ✅ .github/CODEOWNERS sem CURRENT.md
- ✅ Schema 14 migrations + ALTER mcp_tasks aplicáveis (Wagner roda)
- ✅ 8 Entities + scopes (Project/Epic/Cycle/Goal/Component/InboxNotification/TaskDependency + McpTask estendido)
- ✅ TaskParserService aceita frontmatter global + 9 campos novos por-US
- ✅ TaskCrudService whitelist expandida + métodos move/link/assign/bulkUpdate
- ✅ 6 tools MCP novas + alias deprecated tasks-current
- ✅ 4 permissions novas + atualização tasks.read
- ✅ McpDefaultsSeeder com 18 projects + 1 workflow default
- ✅ BackfillTasksFromMarkdownCommand idempotente com Cycle 01 + 70+ tasks hardcoded
- ✅ Pest smoke test JiraStyleSchemaTest

- ✅ Fase 6 — webhook bidirectional (GitTaskLinkerService + SyncMemoryWebhookController estendido + 9 Pest tests)

Pendente próxima sessão (Fase 7 UI + D2 AI):

- 🔲 Tools D2 AI-native: tasks-summarize, tasks-suggest-priority, tasks-suggest-memory (US-TR-302)
- 🔲 UI Web /copiloto/admin/board: Kanban + Backlog + Roadmap + Triage + Inbox (US-TR-201..206)
- 🔲 SPECs por módulo migrados pra usar frontmatter YAML novo (gradual, conforme Wagner edita)
- 🔲 Configurar webhook GitHub Settings → adicionar pull_request event ao Sync Memory webhook existente

## Referências

- ADR 0070 — Jira-style task management (canônico)
- ADR 0069 — TaskRegistry MCP tools canônico (superseded por 0070)
- ADR 0064 — Modularização split TeamMcp + KB + Superadmin 360
- ADR 0053 — MCP server governança como produto
- ADR 0055 — Self-host equivalent Anthropic Team plan
- Linear docs (2026): https://linear.app/docs
- Jira Cloud docs (2026): https://support.atlassian.com/jira-software-cloud/
- Plane.so docs (2026): https://docs.plane.so
