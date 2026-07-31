---
module: Forja
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
  # Admin do MCP — recebidos do TeamMcp em 2026-07-31 ([W] "ads vem para forja").
  # Mesmo padrão do ProjectsController acima: controller da Forja, rota do ADS,
  # URL /ads/admin/* mantida. Importam serviços do ADS (ToolRegistry,
  # UserScopeService) — transitório: o ADS também está em deprecação.
  - "Admin/ToolsController — registry de tools MCP; URL /ads/admin/tools mantida"
  - "Admin/TeamScopesController — RBAC scopes por actor; URL /ads/admin/team-scopes mantida"
  # MCP endpoints — recebidos de Modules/Jana em 2026-07-30 ([W] "MCP vai para Forja")
  - "Mcp/SyncMemoryWebhookController — webhook GitHub → mcp_memory_documents; URL /api/mcp/sync-memory inalterada"
  - "Mcp/HealthController — health/version/cycle-active; URLs /api/mcp/* inalteradas"
  # Absorvido do Modules/Brief em 2026-07-30 (ADR 0091 — o módulo Brief deixou de existir):
  - "BriefFetchController — endpoint HTTP da tool MCP `brief-fetch` (POST /api/mcp/tools/brief-fetch); Daily Brief L7"
  # Identidade do MCP — recebida do TeamMcp em 2026-07-31 ([W] "MCP vai para Forja")
  - "Entities/McpActor + Services/{ActorResolver,McpActorRepository,McpTokenIssuer} — Identity Mesh (ADR 0081); tabela `mcp_actors` é cross-business POR DESIGN"
  - "Http/Requests/StoreActorRequest · Database/Seeders/McpActorsSeeder · comandos mcp:seed-actors e mcp:rotate-token"
  # Loop de handoff (ADR 0283) — recebido do TeamMcp em 2026-07-31
  - "Mcp/Tools/Handoff{Pending,Ack,Submit,Lever} — registradas pelo OimpressoMcpServer da Jana; URLs/nomes de tool inalterados"
  - "Entities/CoworkHandoff + HandoffIngestService + HandoffLeverService + GitMainResolver; `cowork_handoffs` é cross-tenant POR DESIGN (ADR 0093/0283)"
  # Ingest de sessões Claude Code — recebido do TeamMcp em 2026-07-31
  - "Mcp/CcIngestController + CcIngestService + CcIngestRequest — POST /api/cc/ingest, URL inalterada"
  - "Entities/McpIngestHeartbeat + IngestLivenessService — liveness do watcher; produtor é o watcher LOCAL de cada dev (decisão [W]: migrate, o watcher volta)"
  # Hub Equipe — recebido do TeamMcp em 2026-07-31; URLs /team-mcp/* inalteradas
  - "TeamController — time + tokens MCP + quota + export CSV"
  - "TasksAdminController — Kanban Jira-style (mcp_tasks/cycles/projects)"
  - "CcSessionsController — KB de sessões Claude Code do time"
  - "ScorecardController + ScorecardBuilderService — governance maturity per-actor"
  - "As páginas React seguem em resources/js/Pages/team-mcp/* — não migram; renomear URL é decisão separada"
  # Cockpit /forja — recebido do TeamMcp em 2026-07-31; URLs /forja/* inalteradas
  - "ForjaController + Forja{Backlog,Changelog,Mcp,Quadro}Service + PrChecksResolver — 6 abas do loop Cowork"
  - "⚠️ MOVIDO, NÃO FUNDIDO: as abas triagem/backlog/quadro/changelog sobrepõem Triage/Backlog/Board/Activity deste módulo. Fundir = deletar uma implementação = decisão [W], separada desta deprecação"
not_contains:
  - "UltimatePOS Project legado (TimeLog, Invoice, ClientProjects) → Modules/Project (DELETE em Fase 3.8)"
  - "Skills governance → Modules/ADS"
  - "Painel/tokens do MCP (TeamMcp) — em deprecação; endpoints /api/mcp JÁ são daqui"
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

# Modules/Forja — Jira-style task management (futuro: Project)

## Missão

Gerenciamento de **trabalho do time interno** estilo Jira: Project → Epic → Cycle → Story → Subtask + Components cross-cut + Custom fields + Saved views + Inbox + Bidirectional git sync (ADR 0070).

Renomeação Forja → Project prevista pra Fase 3.9 do ADR 0079, **após** delete do Project legado UltimatePOS (Fase 3.8).

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
2. **Fase 3.9** — `git mv Modules/Forja Modules/Project` + namespace + URLs + permissions

---

- **v1.0.0** (2026-05-05) — SCOPE.md inicial. 1 controller pendente de migração + rename Project pendente Fase 3.8/3.9.
- **v1.1.0** (2026-05-06) — Fase 3.7 PR-1: Admin/ProjectsController absorvido. drift_alerts vazio. Rename Project pendente Fase 3.8/3.9.
