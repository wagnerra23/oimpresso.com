---
name: "SUPERFÍCIE — TeamMcp"
description: "Índice GERADO dos artefatos do módulo TeamMcp reconhecidos pelo classificador, agrupados por papel. NÃO editar à mão."
type: reference
authority: generated
lifecycle: ativo
module: TeamMcp
---

# 🗺️ Superfície de código — TeamMcp

> ⚙️ **Gerado por máquina** (`scripts/governance/module-surface.mjs`). NÃO edite à mão — a próxima geração sobrescreve.
> Regenerar: `node scripts/governance/module-surface.mjs TeamMcp --write`. Validar frescor: `--check` (exit 1 se a árvore mudou e isto não foi regenerado).
>
> **O que isto é:** os artefatos reconhecidos pelo classificador dentro de `Modules/TeamMcp/**` + `resources/js/Pages/team-mcp/**` (namespace Inertia `team-mcp`, declarado em `module-surface.mjs::PAGES_NS` porque difere do nome do módulo `TeamMcp`), separados por papel — inclusive telas e seus componentes sem confundir um com o outro. **O que NÃO é:** manifesto de todo byte da pasta, cobertura/nota/status por tela (donos: `screen-coverage-map.mjs` + `casos-gate`) nem âncoras cross-cutting (bridge em `app/`, FSM) — essas vivem narradas no [BRIEFING](BRIEFING.md), não aqui.

**Total mapeado:** 103 arquivos em 15 papéis.

## Controllers — 10

- [TeamScopesController.php](../../../Modules/TeamMcp/Http/Controllers/Admin/TeamScopesController.php)
- [ToolsController.php](../../../Modules/TeamMcp/Http/Controllers/Admin/ToolsController.php)
- [CcSessionsController.php](../../../Modules/TeamMcp/Http/Controllers/CcSessionsController.php)
- [DataController.php](../../../Modules/TeamMcp/Http/Controllers/DataController.php)
- [ForjaController.php](../../../Modules/TeamMcp/Http/Controllers/ForjaController.php)
- [InstallController.php](../../../Modules/TeamMcp/Http/Controllers/InstallController.php)
- [CcIngestController.php](../../../Modules/TeamMcp/Http/Controllers/Mcp/CcIngestController.php)
- [ScorecardController.php](../../../Modules/TeamMcp/Http/Controllers/ScorecardController.php)
- [TasksAdminController.php](../../../Modules/TeamMcp/Http/Controllers/TasksAdminController.php)
- [TeamController.php](../../../Modules/TeamMcp/Http/Controllers/TeamController.php)

## Requests (validação) — 5

- [CcIngestRequest.php](../../../Modules/TeamMcp/Http/Requests/CcIngestRequest.php)
- [ExportUsageCsvRequest.php](../../../Modules/TeamMcp/Http/Requests/ExportUsageCsvRequest.php)
- [IssueActorTokenRequest.php](../../../Modules/TeamMcp/Http/Requests/IssueActorTokenRequest.php)
- [StoreActorRequest.php](../../../Modules/TeamMcp/Http/Requests/StoreActorRequest.php)
- [UpdateQuotaRequest.php](../../../Modules/TeamMcp/Http/Requests/UpdateQuotaRequest.php)

## Services — 16

- [ActorResolver.php](../../../Modules/TeamMcp/Services/ActorResolver.php)
- [CcIngestService.php](../../../Modules/TeamMcp/Services/CcIngestService.php)
- [ForjaBacklogService.php](../../../Modules/TeamMcp/Services/Forja/ForjaBacklogService.php)
- [ForjaChangelogService.php](../../../Modules/TeamMcp/Services/Forja/ForjaChangelogService.php)
- [ForjaMcpService.php](../../../Modules/TeamMcp/Services/Forja/ForjaMcpService.php)
- [ForjaQuadroService.php](../../../Modules/TeamMcp/Services/Forja/ForjaQuadroService.php)
- [GitMainResolver.php](../../../Modules/TeamMcp/Services/GitMainResolver.php)
- [HandoffIngestService.php](../../../Modules/TeamMcp/Services/HandoffIngestService.php)
- [HandoffLeverService.php](../../../Modules/TeamMcp/Services/HandoffLeverService.php)
- [IngestLivenessService.php](../../../Modules/TeamMcp/Services/IngestLivenessService.php)
- [McpActorRepository.php](../../../Modules/TeamMcp/Services/McpActorRepository.php)
- [McpTokenIssuer.php](../../../Modules/TeamMcp/Services/McpTokenIssuer.php)
- [PrChecksResolver.php](../../../Modules/TeamMcp/Services/PrChecksResolver.php)
- [ScorecardBuilderService.php](../../../Modules/TeamMcp/Services/ScorecardBuilderService.php)
- [TeamUsageAggregator.php](../../../Modules/TeamMcp/Services/TeamUsageAggregator.php)
- [UsageCsvExporter.php](../../../Modules/TeamMcp/Services/UsageCsvExporter.php)

## Models / Entities — 3

- [CoworkHandoff.php](../../../Modules/TeamMcp/Entities/CoworkHandoff.php)
- [McpActor.php](../../../Modules/TeamMcp/Entities/McpActor.php)
- [McpIngestHeartbeat.php](../../../Modules/TeamMcp/Entities/McpIngestHeartbeat.php)

## Console / Commands — 4

- [HandoffIngestCommand.php](../../../Modules/TeamMcp/Console/Commands/HandoffIngestCommand.php)
- [HandoffStaleAlertCommand.php](../../../Modules/TeamMcp/Console/Commands/HandoffStaleAlertCommand.php)
- [RotateTokenCommand.php](../../../Modules/TeamMcp/Console/Commands/RotateTokenCommand.php)
- [SeedActorsCommand.php](../../../Modules/TeamMcp/Console/Commands/SeedActorsCommand.php)

## Providers — 1

- [TeamMcpServiceProvider.php](../../../Modules/TeamMcp/Providers/TeamMcpServiceProvider.php)

## Migrations (schema) — 5

- [2026_05_05_240001_create_mcp_actors_and_link_tokens.php](../../../Modules/TeamMcp/Database/Migrations/2026_05_05_240001_create_mcp_actors_and_link_tokens.php)
- [2026_05_05_240002_seed_initial_actors.php](../../../Modules/TeamMcp/Database/Migrations/2026_05_05_240002_seed_initial_actors.php)
- [2026_05_07_140000_update_actor_display_name_maiara.php](../../../Modules/TeamMcp/Database/Migrations/2026_05_07_140000_update_actor_display_name_maiara.php)
- [2026_06_15_100000_create_mcp_ingest_heartbeat_table.php](../../../Modules/TeamMcp/Database/Migrations/2026_06_15_100000_create_mcp_ingest_heartbeat_table.php)
- [2026_06_17_120000_create_cowork_handoffs_table.php](../../../Modules/TeamMcp/Database/Migrations/2026_06_17_120000_create_cowork_handoffs_table.php)

## Seeders — 2

- [ForjaDemoTicketsSeeder.php](../../../Modules/TeamMcp/Database/Seeders/ForjaDemoTicketsSeeder.php)
- [McpActorsSeeder.php](../../../Modules/TeamMcp/Database/Seeders/McpActorsSeeder.php)

## Config — 2

- [config.php](../../../Modules/TeamMcp/Config/config.php)
- [retention.php](../../../Modules/TeamMcp/Config/retention.php)

## Telas (Inertia/React) — 5

- [Index.tsx](../../../resources/js/Pages/team-mcp/CcSessions/Index.tsx)
- [Cockpit.tsx](../../../resources/js/Pages/team-mcp/Forja/Cockpit.tsx)
- [Index.tsx](../../../resources/js/Pages/team-mcp/Scorecard/Index.tsx)
- [Index.tsx](../../../resources/js/Pages/team-mcp/Tasks/Index.tsx)
- [Index.tsx](../../../resources/js/Pages/team-mcp/Team/Index.tsx)

## Componentes / apoio de tela — 10

- [SessionDrawer.tsx](../../../resources/js/Pages/team-mcp/CcSessions/_components/SessionDrawer.tsx)
- [ForjaBacklog.tsx](../../../resources/js/Pages/team-mcp/Forja/_components/ForjaBacklog.tsx)
- [ForjaChangelog.tsx](../../../resources/js/Pages/team-mcp/Forja/_components/ForjaChangelog.tsx)
- [ForjaDossier.tsx](../../../resources/js/Pages/team-mcp/Forja/_components/ForjaDossier.tsx)
- [ForjaHub.tsx](../../../resources/js/Pages/team-mcp/Forja/_components/ForjaHub.tsx)
- [ForjaMcp.tsx](../../../resources/js/Pages/team-mcp/Forja/_components/ForjaMcp.tsx)
- [ForjaQuadro.tsx](../../../resources/js/Pages/team-mcp/Forja/_components/ForjaQuadro.tsx)
- [ForjaTriage.tsx](../../../resources/js/Pages/team-mcp/Forja/_components/ForjaTriage.tsx)
- [TaskDrawer.tsx](../../../resources/js/Pages/team-mcp/Tasks/_components/TaskDrawer.tsx)
- [taskBadges.tsx](../../../resources/js/Pages/team-mcp/Tasks/_components/taskBadges.tsx)

## Charters (lei da tela) — 5

- [Index.charter.md](../../../resources/js/Pages/team-mcp/CcSessions/Index.charter.md)
- [Cockpit.charter.md](../../../resources/js/Pages/team-mcp/Forja/Cockpit.charter.md)
- [Index.charter.md](../../../resources/js/Pages/team-mcp/Scorecard/Index.charter.md)
- [Index.charter.md](../../../resources/js/Pages/team-mcp/Tasks/Index.charter.md)
- [Index.charter.md](../../../resources/js/Pages/team-mcp/Team/Index.charter.md)

## Casos (contrato UC) — 2

- [Cockpit.casos.md](../../../resources/js/Pages/team-mcp/Forja/Cockpit.casos.md)
- [Index.casos.md](../../../resources/js/Pages/team-mcp/Scorecard/Index.casos.md)

## Testes (Pest) — 27

- 27 arquivos em [Modules/TeamMcp/Tests/Feature/](../../../Modules/TeamMcp/Tests/Feature) — cobertura é do `casos-gate`/`screen-coverage`, não deste índice.

## Outros (raiz/misc) — 6

- [routes.php](../../../Modules/TeamMcp/Http/routes.php)
- [HandoffAckTool.php](../../../Modules/TeamMcp/Mcp/Tools/HandoffAckTool.php)
- [HandoffLeverTool.php](../../../Modules/TeamMcp/Mcp/Tools/HandoffLeverTool.php)
- [HandoffPendingTool.php](../../../Modules/TeamMcp/Mcp/Tools/HandoffPendingTool.php)
- [HandoffSubmitTool.php](../../../Modules/TeamMcp/Mcp/Tools/HandoffSubmitTool.php)
- [start.php](../../../Modules/TeamMcp/start.php)
