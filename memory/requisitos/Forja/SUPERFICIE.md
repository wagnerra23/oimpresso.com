---
name: "SUPERFÍCIE — Forja"
description: "Índice GERADO dos artefatos do módulo Forja reconhecidos pelo classificador, agrupados por papel. NÃO editar à mão."
type: reference
authority: generated
lifecycle: ativo
module: Forja
---

# 🗺️ Superfície de código — Forja

> ⚙️ **Gerado por máquina** (`scripts/governance/module-surface.mjs`). NÃO edite à mão — a próxima geração sobrescreve.
> Regenerar: `node scripts/governance/module-surface.mjs Forja --write`. Validar frescor: `--check` (exit 1 se a árvore mudou e isto não foi regenerado).
>
> **O que isto é:** os artefatos reconhecidos pelo classificador dentro de `Modules/Forja/**` + `resources/js/Pages/Forja/**`, separados por papel — inclusive telas e seus componentes sem confundir um com o outro. **O que NÃO é:** manifesto de todo byte da pasta, cobertura/nota/status por tela (donos: `screen-coverage-map.mjs` + `casos-gate`) nem âncoras cross-cutting (bridge em `app/`, FSM) — essas vivem narradas no [BRIEFING](BRIEFING.md), não aqui.

**Total mapeado:** 180 arquivos em 14 papéis.

## Controllers — 23

- [ActivityController.php](../../../Modules/Forja/Http/Controllers/ActivityController.php)
- [ProjectsController.php](../../../Modules/Forja/Http/Controllers/Admin/ProjectsController.php)
- [TeamScopesController.php](../../../Modules/Forja/Http/Controllers/Admin/TeamScopesController.php)
- [ToolsController.php](../../../Modules/Forja/Http/Controllers/Admin/ToolsController.php)
- [BacklogController.php](../../../Modules/Forja/Http/Controllers/BacklogController.php)
- [BoardController.php](../../../Modules/Forja/Http/Controllers/BoardController.php)
- [BriefFetchController.php](../../../Modules/Forja/Http/Controllers/BriefFetchController.php)
- [BurndownController.php](../../../Modules/Forja/Http/Controllers/BurndownController.php)
- [CcSessionsController.php](../../../Modules/Forja/Http/Controllers/CcSessionsController.php)
- [DataController.php](../../../Modules/Forja/Http/Controllers/DataController.php)
- [ForjaController.php](../../../Modules/Forja/Http/Controllers/ForjaController.php)
- [InboxController.php](../../../Modules/Forja/Http/Controllers/InboxController.php)
- [InstallController.php](../../../Modules/Forja/Http/Controllers/InstallController.php)
- [CcIngestController.php](../../../Modules/Forja/Http/Controllers/Mcp/CcIngestController.php)
- [HealthController.php](../../../Modules/Forja/Http/Controllers/Mcp/HealthController.php)
- [SyncMemoryWebhookController.php](../../../Modules/Forja/Http/Controllers/Mcp/SyncMemoryWebhookController.php)
- [MyWorkController.php](../../../Modules/Forja/Http/Controllers/MyWorkController.php)
- [RoadmapController.php](../../../Modules/Forja/Http/Controllers/RoadmapController.php)
- [ScorecardController.php](../../../Modules/Forja/Http/Controllers/ScorecardController.php)
- [SearchController.php](../../../Modules/Forja/Http/Controllers/SearchController.php)
- [TasksAdminController.php](../../../Modules/Forja/Http/Controllers/TasksAdminController.php)
- [TeamController.php](../../../Modules/Forja/Http/Controllers/TeamController.php)
- [TriageController.php](../../../Modules/Forja/Http/Controllers/TriageController.php)

## Requests (validação) — 23

- [AddCommentRequest.php](../../../Modules/Forja/Http/Requests/AddCommentRequest.php)
- [AddSubtaskRequest.php](../../../Modules/Forja/Http/Requests/AddSubtaskRequest.php)
- [BriefFetchToolRequest.php](../../../Modules/Forja/Http/Requests/BriefFetchToolRequest.php)
- [BulkBacklogRequest.php](../../../Modules/Forja/Http/Requests/BulkBacklogRequest.php)
- [CcIngestRequest.php](../../../Modules/Forja/Http/Requests/CcIngestRequest.php)
- [CompareBriefRequest.php](../../../Modules/Forja/Http/Requests/CompareBriefRequest.php)
- [ExportBriefMarkdownRequest.php](../../../Modules/Forja/Http/Requests/ExportBriefMarkdownRequest.php)
- [ExportUsageCsvRequest.php](../../../Modules/Forja/Http/Requests/ExportUsageCsvRequest.php)
- [FetchBriefHistoryRequest.php](../../../Modules/Forja/Http/Requests/FetchBriefHistoryRequest.php)
- [ForceRefreshBriefRequest.php](../../../Modules/Forja/Http/Requests/ForceRefreshBriefRequest.php)
- [GenerateBriefRequest.php](../../../Modules/Forja/Http/Requests/GenerateBriefRequest.php)
- [InvalidateBriefRequest.php](../../../Modules/Forja/Http/Requests/InvalidateBriefRequest.php)
- [IssueActorTokenRequest.php](../../../Modules/Forja/Http/Requests/IssueActorTokenRequest.php)
- [MarkBriefValidRequest.php](../../../Modules/Forja/Http/Requests/MarkBriefValidRequest.php)
- [PurgeBriefHistoryRequest.php](../../../Modules/Forja/Http/Requests/PurgeBriefHistoryRequest.php)
- [StoreActorRequest.php](../../../Modules/Forja/Http/Requests/StoreActorRequest.php)
- [StoreProjectRequest.php](../../../Modules/Forja/Http/Requests/StoreProjectRequest.php)
- [StoreTaskRequest.php](../../../Modules/Forja/Http/Requests/StoreTaskRequest.php)
- [UpdateProjectRequest.php](../../../Modules/Forja/Http/Requests/UpdateProjectRequest.php)
- [UpdateQuotaRequest.php](../../../Modules/Forja/Http/Requests/UpdateQuotaRequest.php)
- [UpdateTaskRequest.php](../../../Modules/Forja/Http/Requests/UpdateTaskRequest.php)
- [UpdateTaskStatusRequest.php](../../../Modules/Forja/Http/Requests/UpdateTaskStatusRequest.php)
- [WatchTaskRequest.php](../../../Modules/Forja/Http/Requests/WatchTaskRequest.php)

## Services — 26

- [ActorResolver.php](../../../Modules/Forja/Services/ActorResolver.php)
- [BriefGeneratorService.php](../../../Modules/Forja/Services/BriefGeneratorService.php)
- [BriefValidator.php](../../../Modules/Forja/Services/BriefValidator.php)
- [CcIngestService.php](../../../Modules/Forja/Services/CcIngestService.php)
- [DecisionLinksService.php](../../../Modules/Forja/Services/DecisionLinksService.php)
- [ForjaAuditService.php](../../../Modules/Forja/Services/ForjaAuditService.php)
- [ForjaBacklogService.php](../../../Modules/Forja/Services/ForjaBacklogService.php)
- [ForjaChangelogService.php](../../../Modules/Forja/Services/ForjaChangelogService.php)
- [ForjaMcpService.php](../../../Modules/Forja/Services/ForjaMcpService.php)
- [ForjaQuadroService.php](../../../Modules/Forja/Services/ForjaQuadroService.php)
- [GitMainResolver.php](../../../Modules/Forja/Services/GitMainResolver.php)
- [HandoffIngestService.php](../../../Modules/Forja/Services/HandoffIngestService.php)
- [HandoffLeverService.php](../../../Modules/Forja/Services/HandoffLeverService.php)
- [IngestLivenessService.php](../../../Modules/Forja/Services/IngestLivenessService.php)
- [LeaseBriefSectionService.php](../../../Modules/Forja/Services/LeaseBriefSectionService.php)
- [McpActorRepository.php](../../../Modules/Forja/Services/McpActorRepository.php)
- [McpTokenIssuer.php](../../../Modules/Forja/Services/McpTokenIssuer.php)
- [PrChecksResolver.php](../../../Modules/Forja/Services/PrChecksResolver.php)
- [ProjectDecomposerService.php](../../../Modules/Forja/Services/ProjectDecomposerService.php)
- [ProjectService.php](../../../Modules/Forja/Services/ProjectService.php)
- [ScorecardBuilderService.php](../../../Modules/Forja/Services/ScorecardBuilderService.php)
- [TeamUsageAggregator.php](../../../Modules/Forja/Services/TeamUsageAggregator.php)
- [ToolRegistry.php](../../../Modules/Forja/Services/ToolRegistry.php)
- [UsageCsvExporter.php](../../../Modules/Forja/Services/UsageCsvExporter.php)
- [UserScopeService.php](../../../Modules/Forja/Services/UserScopeService.php)
- [ValidationResult.php](../../../Modules/Forja/Services/ValidationResult.php)

## Models / Entities — 3

- [CoworkHandoff.php](../../../Modules/Forja/Entities/CoworkHandoff.php)
- [McpActor.php](../../../Modules/Forja/Entities/McpActor.php)
- [McpIngestHeartbeat.php](../../../Modules/Forja/Entities/McpIngestHeartbeat.php)

## Console / Commands — 8

- [BriefHealthCommand.php](../../../Modules/Forja/Console/Commands/BriefHealthCommand.php)
- [ForjaHealthCommand.php](../../../Modules/Forja/Console/Commands/ForjaHealthCommand.php)
- [GenerateBriefCommand.php](../../../Modules/Forja/Console/Commands/GenerateBriefCommand.php)
- [HandoffIngestCommand.php](../../../Modules/Forja/Console/Commands/HandoffIngestCommand.php)
- [HandoffStaleAlertCommand.php](../../../Modules/Forja/Console/Commands/HandoffStaleAlertCommand.php)
- [RotateTokenCommand.php](../../../Modules/Forja/Console/Commands/RotateTokenCommand.php)
- [SeedActorsCommand.php](../../../Modules/Forja/Console/Commands/SeedActorsCommand.php)
- [SkillTierReviewCommand.php](../../../Modules/Forja/Console/Commands/SkillTierReviewCommand.php)

## Providers — 1

- [ForjaServiceProvider.php](../../../Modules/Forja/Providers/ForjaServiceProvider.php)

## Migrations (schema) — 5

- [2026_05_05_240001_create_mcp_actors_and_link_tokens.php](../../../Modules/Forja/Database/Migrations/2026_05_05_240001_create_mcp_actors_and_link_tokens.php)
- [2026_05_05_240002_seed_initial_actors.php](../../../Modules/Forja/Database/Migrations/2026_05_05_240002_seed_initial_actors.php)
- [2026_05_07_140000_update_actor_display_name_maiara.php](../../../Modules/Forja/Database/Migrations/2026_05_07_140000_update_actor_display_name_maiara.php)
- [2026_06_15_100000_create_mcp_ingest_heartbeat_table.php](../../../Modules/Forja/Database/Migrations/2026_06_15_100000_create_mcp_ingest_heartbeat_table.php)
- [2026_06_17_120000_create_cowork_handoffs_table.php](../../../Modules/Forja/Database/Migrations/2026_06_17_120000_create_cowork_handoffs_table.php)

## Seeders — 2

- [ForjaDemoTicketsSeeder.php](../../../Modules/Forja/Database/Seeders/ForjaDemoTicketsSeeder.php)
- [McpActorsSeeder.php](../../../Modules/Forja/Database/Seeders/McpActorsSeeder.php)

## Config — 4

- [brief-retention.php](../../../Modules/Forja/Config/brief-retention.php)
- [config.php](../../../Modules/Forja/Config/config.php)
- [retention-mcp.php](../../../Modules/Forja/Config/retention-mcp.php)
- [retention.php](../../../Modules/Forja/Config/retention.php)

## Telas (Inertia/React) — 9

- [Index.tsx](../../../resources/js/Pages/Forja/Activity/Index.tsx)
- [Index.tsx](../../../resources/js/Pages/Forja/Backlog/Index.tsx)
- [DetailSheet.tsx](../../../resources/js/Pages/Forja/Board/DetailSheet.tsx)
- [Index.tsx](../../../resources/js/Pages/Forja/Board/Index.tsx)
- [Index.tsx](../../../resources/js/Pages/Forja/Burndown/Index.tsx)
- [Index.tsx](../../../resources/js/Pages/Forja/Inbox/Index.tsx)
- [Index.tsx](../../../resources/js/Pages/Forja/MyWork/Index.tsx)
- [Index.tsx](../../../resources/js/Pages/Forja/Roadmap/Index.tsx)
- [Index.tsx](../../../resources/js/Pages/Forja/Triage/Index.tsx)

## Componentes / apoio de tela — 1

- [TriageDossier.tsx](../../../resources/js/Pages/Forja/Triage/_components/TriageDossier.tsx)

## Charters (lei da tela) — 9

- [Index.charter.md](../../../resources/js/Pages/Forja/Activity/Index.charter.md)
- [Index.charter.md](../../../resources/js/Pages/Forja/Backlog/Index.charter.md)
- [DetailSheet.charter.md](../../../resources/js/Pages/Forja/Board/DetailSheet.charter.md)
- [Index.charter.md](../../../resources/js/Pages/Forja/Board/Index.charter.md)
- [Index.charter.md](../../../resources/js/Pages/Forja/Burndown/Index.charter.md)
- [Index.charter.md](../../../resources/js/Pages/Forja/Inbox/Index.charter.md)
- [Index.charter.md](../../../resources/js/Pages/Forja/MyWork/Index.charter.md)
- [Index.charter.md](../../../resources/js/Pages/Forja/Roadmap/Index.charter.md)
- [Index.charter.md](../../../resources/js/Pages/Forja/Triage/Index.charter.md)

## Testes (Pest) — 50

- 50 arquivos em [Modules/Forja/Tests/Feature/](../../../Modules/Forja/Tests/Feature) — cobertura é do `casos-gate`/`screen-coverage`, não deste índice.

## Outros (raiz/misc) — 16

- [ProjectDecomposerAgent.php](../../../Modules/Forja/Ai/Agents/ProjectDecomposerAgent.php)
- [Tool.php](../../../Modules/Forja/Contracts/Tool.php)
- [routes.php](../../../Modules/Forja/Http/routes.php)
- [BriefFetchTool.php](../../../Modules/Forja/Mcp/Tools/BriefFetchTool.php)
- [HandoffAckTool.php](../../../Modules/Forja/Mcp/Tools/HandoffAckTool.php)
- [HandoffLeverTool.php](../../../Modules/Forja/Mcp/Tools/HandoffLeverTool.php)
- [HandoffPendingTool.php](../../../Modules/Forja/Mcp/Tools/HandoffPendingTool.php)
- [HandoffSubmitTool.php](../../../Modules/Forja/Mcp/Tools/HandoffSubmitTool.php)
- [BoostToolAdapter.php](../../../Modules/Forja/Tools/BoostToolAdapter.php)
- [GitCommitWipTool.php](../../../Modules/Forja/Tools/GitCommitWipTool.php)
- [GitInspectTool.php](../../../Modules/Forja/Tools/GitInspectTool.php)
- [LogReaderTool.php](../../../Modules/Forja/Tools/LogReaderTool.php)
- [MetricsQueryTool.php](../../../Modules/Forja/Tools/MetricsQueryTool.php)
- [RunTestTool.php](../../../Modules/Forja/Tools/RunTestTool.php)
- [WriteFileTool.php](../../../Modules/Forja/Tools/WriteFileTool.php)
- [start.php](../../../Modules/Forja/start.php)
