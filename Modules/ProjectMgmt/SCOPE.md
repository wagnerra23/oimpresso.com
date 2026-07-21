---
module: ProjectMgmt
will_rename_to: Project
will_rename_at_phase: 3.9
depends_on_phase: 3.8 (DELETE Project legado UltimatePOS)
purpose: "Gerenciamento Jira-style do time interno: Kanban, Backlog, Roadmap, My Work, Inbox, Triage, Burndown sobre `mcp_jira_projects/epics/cycles/tasks`."
contains:
  - "BoardController — Kanban view"
  - "BacklogController — backlog priorizado"
  - "RoadmapController — roadmap quarterly"
  - "MyWorkController — tasks do owner logado"
  - "TriageController — tasks órfãs (sem owner/priority/backlog); paridade tool MCP `triage`"
  - "InboxController — caixa de entrada per-user (mcp_inbox_notifications); paridade tool MCP `my-inbox`"
  - "BurndownController — burndown chart por cycle"
  - "ActivityController — atividade recente"
  - "SearchController — busca cross-task fulltext"
  - "DataController + InstallController (boilerplate)"
  # Absorvido em Fase 3.7 PR-1 (2026-05-06):
  - "Admin/ProjectsController — gerencia mcp_jira_projects (key=COPI/ADS/FIN/etc); URL /ads/admin/projects mantida"
not_contains:
  - "UltimatePOS Project legado (TimeLog, Invoice, ClientProjects) → Modules/Project (DELETE em Fase 3.8)"
  - "Skills governance → Modules/ADS"
  - "MCP server admin → Modules/TeamMcp"
  - "Knowledge browsing → Modules/KB"
  - "Chat IA → Modules/Jana"
trust_required: L2
owner: wagner
permission_prefix: projectmgmt.*
charter_adr: 0080
related_adrs:
  - 0070-jira-style-task-management-current-md-removed
  - 0079-constituicao-oimpresso-7-camadas-governanca
  - 0080-trust-tiers-operacional-audit-findings
url_prefixes:
  - /projectmgmt/*
  - (futuro) /project/* — após Fase 3.9 (rename pra Project quando legado for deletado)
db_tables_owned:
  - mcp_jira_projects
  - mcp_epics
  - mcp_cycles
  - mcp_cycle_goals
  - mcp_tasks
  - mcp_task_attachments
  - mcp_task_comments
  - mcp_task_dependencies
  - mcp_task_events
  - mcp_task_memory_links
  - mcp_task_watchers
  - mcp_components
  - mcp_views
  - mcp_inbox_notifications
  - mcp_issue_templates
drift_alerts: []
  # Fase 3.7 PR-1 (2026-05-06): Admin/ProjectsController absorvido do ADS.
  # URL /ads/admin/projects mantida — só namespace mudou.
---

# Modules/ProjectMgmt — Jira-style task management (futuro: Project)

## Missão

Gerenciamento de **trabalho do time interno** estilo Jira: Project → Epic → Cycle → Story → Subtask + Components cross-cut + Custom fields + Saved views + Inbox + Bidirectional git sync (ADR 0070).

Renomeação ProjectMgmt → Project prevista pra Fase 3.9 do ADR 0079, **após** delete do Project legado UltimatePOS (Fase 3.8).

## Quando este módulo é tocado

| Trigger | Quem | Ação |
|---|---|---|
| Wagner abre `/projectmgmt/board` | L2 | Kanban view de cycle ativo |
| Wagner cria task | L2 | INSERT em mcp_tasks (com cycle_id, owner) |
| Wagner abre My Work | L2 | filtra owned tasks status=doing/todo/blocked |
| Time abre Triage | L2 | tasks novas sem owner/priority |
| Cycle close | L1/L2 | `cycles-close --rollover` move incompletas |

## Quando NÃO é tocado

- ❌ UltimatePOS Project (clientes + timesheet) → Modules/Project legado (em DELETE Fase 3.8)
- ❌ Skills governance → Modules/ADS
- ❌ Tokens / scopes / audit → Modules/TeamMcp

## Drift resolvido (Fase 3.7 PR-1, 2026-05-06)

Admin/ProjectsController absorvido do ADS. URL `/ads/admin/projects` mantida.

## Renomeação Project pendente (Fase 3.9)

Bloqueada por Fase 3.8 (delete Project legado). Sequência:

1. **Fase 3.8** — auditar Modules/Project legado, extrair info útil (queries SQL: invoices/timesheets de clientes), preservar onde fizer sentido (Financeiro? Notas?), `git rm -rf Modules/Project/`
2. **Fase 3.9** — `git mv Modules/ProjectMgmt Modules/Project` + namespace + URLs + permissions

---

- **v1.0.0** (2026-05-05) — SCOPE.md inicial. 1 controller pendente de migração + rename Project pendente Fase 3.8/3.9.
- **v1.1.0** (2026-05-06) — Fase 3.7 PR-1: Admin/ProjectsController absorvido. drift_alerts vazio. Rename Project pendente Fase 3.8/3.9.
