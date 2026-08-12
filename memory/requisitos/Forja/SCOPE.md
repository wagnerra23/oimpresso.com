---
module: Forja
depends_on_phase: 3.8 (DELETE Project legado UltimatePOS)
purpose: "Cockpit de trabalho do time interno (Kanban, Backlog, Roadmap, My Work, Inbox, Triage, Burndown) e host da infraestrutura MCP da plataforma: identidade e emissão de token (mcp_actors), endpoints /api/mcp e /api/cc, Daily Brief, loop de handoff Cowork-Code, hub Equipe e Admin do MCP."
migracao_ui: "concluido — 0 Blade servido"
contains:
  - "BoardController — Kanban view"
  - "BacklogController — backlog priorizado"
  - "RoadmapController — roadmap quarterly (/project-mgmt/roadmap): epics agrupados por target_quarter"
  - "RoadmapGanttController — roadmap Gantt (/forja/roadmap-gantt): tasks no tempo via mcp_cycles+mcp_tasks, com reschedule de due_date por drag-drop. Recebido do Modules/Jana em 2026-08-05 (ADR 0366 §D-B + 0367 D4). CONVIVE com o quarter view acima — são duas leituras do mesmo backlog e nenhuma responde a pergunta da outra (o quarter não tem due_date/blocked_by, o Gantt não tem epic_id); a 0367 D7 diz que o quarter só sai quando o Gantt provar que substitui"
  - "MyWorkController — tasks do owner logado"
  - "TriageController — tasks órfãs (sem owner/priority/backlog); paridade tool MCP `triage`"
  - "TrabalhoController + TrabalhoService — lista única (/forja/trabalho): funde os TRÊS backlogs que respondiam a mesma pergunta com escopos diferentes (Pages/Forja/Backlog rica project=FORJA · _components/ForjaBacklog enxuta · team-mcp/Tasks todas). Base é a NATIVA (filtros/KPIs/memoização) + a projeção forja_* do cockpit + escopo sem recorte de projeto. US-FORJA-006; a remoção da implementação perdedora é decisão [W] e NÃO aconteceu nesta onda — as três convivem"
  - "AprovacoesController + ForjaAprovacoesService — mesa de aprovações (/forja/aprovacoes): fila de mcp_tasks em `pending_approval` (o que espera decisão de [W]) em ordem de espera, e a decisão admitir/parquear/recusar. Superfície da ADR 0368, que fechou a política e deixou o código pra PR próprio; estado+FSM+trava de recusa-sem-motivo já vieram em #5283/#5288. Escrita 100% via TaskCrudService — mesmo chokepoint da tool MCP `tasks-update`, sem 2º caminho"
  - "InboxController — caixa de entrada per-user (mcp_inbox_notifications); paridade tool MCP `my-inbox`"
  - "BurndownController — burndown chart por cycle"
  - "ActivityController — atividade recente"
  - "SearchController — busca cross-task fulltext"
  - "DataController + InstallController (boilerplate)"
  # Absorvido em Fase 3.7 PR-1 (2026-05-06):
  - "Admin/ProjectsController — gerencia mcp_jira_projects (key=COPI/ADS/FIN/etc); URL /ads/admin/projects mantida"
  # Admin do MCP — recebidos do TeamMcp em 2026-07-31 ([W] "ads vem para forja").
  # 2026-07-31 (parte 5/7): a ROTA das 9 entradas /ads/admin/{tools,team-scopes,
  # projects} saiu do ADS e veio pra Modules/Forja/Http/routes.php — a Forja e
  # agora o UNICO host (rota + controller no mesmo modulo), fechando o drift.
  # URL /ads/admin/* mantida (ADR 0087 — o frontend chama por string literal).
  # O residuo que a parte 5 anotava aqui ("os 3 ainda importam servicos do ADS")
  # foi fechado pela parte 3/7 — os servicos vieram junto; ver bloco abaixo.
  - "Admin/ToolsController — registry de tools MCP; URL /ads/admin/tools mantida"
  - "Admin/TeamScopesController — RBAC scopes por actor; URL /ads/admin/team-scopes mantida"
  # Registro do ADS, recebido em 2026-07-31 — os 3 consumidores vivos eram
  # controllers desta casa, então o serviço veio morar junto do consumidor.
  # URLs, nomes de rota, permissions `ads.*` e a chave `ads_module` NÃO mudaram.
  # MetricsQuery saiu no E5 do ADS (2026-07-31): agregava `mcp_dual_brain_decisions`,
  # que foi dropada — tool sem fonte só poderia falhar.
  - "Services/ToolRegistry + Contracts/Tool + Tools/{BoostToolAdapter,GitInspect,GitCommitWip,LogReader,RunTest,WriteFile} — catálogo de tools (Anthropic tool use); consumido por Admin/ToolsController e por KB Admin/GraphController"
  - "Services/UserScopeService — permissões (usuário x módulo) sobre `mcp_user_module_access`; é quem o WriteFileTool consulta ANTES de escrever — regra do servidor vence a regra local"
  - "Services/ProjectDecomposerService — decompõe Project em Parts via Sonnet; ainda importa DecisionLinksService e Ai/Agents/ProjectDecomposerAgent do ADS (resíduo transitório: sai com o núcleo do ADS)"
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
  - "As páginas React seguem em Modules/Forja/Resources/js/Pages/team-mcp/* — não migram; renomear URL é decisão separada"
  # Cockpit /forja — recebido do TeamMcp em 2026-07-31; URLs /forja/* inalteradas
  - "ForjaController + Forja{Backlog,Changelog,Mcp,Quadro}Service + PrChecksResolver — 6 abas do loop Cowork"
  - "⚠️ MOVIDO, NÃO FUNDIDO: as abas triagem/backlog/quadro/changelog sobrepõem Triage/Backlog/Board/Activity deste módulo. Fundir = deletar uma implementação = decisão [W], separada desta deprecação"
not_contains:
  - "UltimatePOS Project legado (TimeLog, Invoice, ClientProjects) → Modules/Project (DELETE em Fase 3.8)"
  # Destino era o ADS até a remoção dele em 2026-07-31 (ADR 0363); skills foram pra Jana (#5129).
  # A história fica NESTE comentário: o catalog-graph deriva a aresta de TODO `Modules/X` que
  # aparecer no VALOR do item, então citar o nome morto ali recriaria a aresta pro módulo morto.
  - "Skills governance → Modules/Jana (Services/SkillsService.php, #5129)"
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
  # host das 9 rotas do Admin do MCP (parte 5/7, 2026-07-31) — URL do ADS mantida (ADR 0087)
  - /ads/admin/tools/*
  - /ads/admin/team-scopes/*
  - /ads/admin/projects/*
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
  # Herdadas do ADS (módulo extinto) em 2026-07-31 (ADR 0363). Declaradas aqui porque o E5
  # dropou o núcleo dual-brain e estas 5 FICARAM — cada uma tem consumidor vivo
  # nesta casa. Sem dono declarado, a próxima varredura de deprecação as acharia
  # órfãs e dropava: é exatamente o padrão da errata C5 do DEPRECATION-PLAN do ADS.
  - mcp_projects           # Admin/ProjectsController + ProjectService
  - mcp_project_parts      # ProjectDecomposerService
  - mcp_decision_links     # DecisionLinksService (escreve a cada decompose)
  - mcp_tool_executions    # Admin/ToolsController (INSERT por execução de tool)
  - mcp_user_module_access # UserScopeService (RBAC usuário × módulo)
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
- ❌ Skills governance → **Modules/Jana** (`SkillsService`; era `Modules/ADS` até a remoção de 2026-07-31, [ADR 0363](../../decisions/0363-governance-incorpora-ads-nucleo-sem-receptor.md))
- ❌ Tokens / scopes / audit → Modules/Forja

## Drift resolvido (Fase 3.7 PR-1, 2026-05-06)

Admin/ProjectsController absorvido do ADS. URL `/ads/admin/projects` mantida.

## Renomeação Project pendente (Fase 3.9)

Bloqueada por Fase 3.8 (delete Project legado). Sequência:

1. **Fase 3.8** — auditar Modules/Project legado, extrair info útil (queries SQL: invoices/timesheets de clientes), preservar onde fizer sentido (Financeiro? Notas?), `git rm -rf Modules/Project/`
2. **Fase 3.9** — `git mv Modules/Forja Modules/Project` + namespace + URLs + permissions

---

- **v1.0.0** (2026-05-05) — SCOPE.md inicial. 1 controller pendente de migração + rename Project pendente Fase 3.8/3.9.
- **v1.1.0** (2026-05-06) — Fase 3.7 PR-1: Admin/ProjectsController absorvido. drift_alerts vazio. Rename Project pendente Fase 3.8/3.9.
