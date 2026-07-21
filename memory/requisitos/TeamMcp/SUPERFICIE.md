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
> **O que isto é:** os artefatos reconhecidos pelo classificador dentro de `Modules/TeamMcp/**` + `resources/js/Pages/TeamMcp/**`, separados por papel — inclusive telas e seus componentes sem confundir um com o outro. **O que NÃO é:** manifesto de todo byte da pasta, cobertura/nota/status por tela (donos: `screen-coverage-map.mjs` + `casos-gate`) nem âncoras cross-cutting (bridge em `app/`, FSM) — essas vivem narradas no [BRIEFING](BRIEFING.md), não aqui.

**Total mapeado:** 82 arquivos em 11 papéis.

## Controllers — 12

- [TeamScopesController.php](../../../Modules/TeamMcp/Http/Controllers/Admin/TeamScopesController.php)
- [ToolsController.php](../../../Modules/TeamMcp/Http/Controllers/Admin/ToolsController.php)
- [CcSessionsController.php](../../../Modules/TeamMcp/Http/Controllers/CcSessionsController.php)
- [DataController.php](../../../Modules/TeamMcp/Http/Controllers/DataController.php)
- [ForjaController.php](../../../Modules/TeamMcp/Http/Controllers/ForjaController.php)
- [InstallController.php](../../../Modules/TeamMcp/Http/Controllers/InstallController.php)
- [CcIngestController.php](../../../Modules/TeamMcp/Http/Controllers/Mcp/CcIngestController.php)
- [HealthController.php](../../../Modules/TeamMcp/Http/Controllers/Mcp/HealthController.php)
- [SyncMemoryWebhookController.php](../../../Modules/TeamMcp/Http/Controllers/Mcp/SyncMemoryWebhookController.php)
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

## Testes (Pest) — 26

- 26 arquivos em [Modules/TeamMcp/Tests/Feature/](../../../Modules/TeamMcp/Tests/Feature) — cobertura é do `casos-gate`/`screen-coverage`, não deste índice.

## Outros (raiz/misc) — 6

- [routes.php](../../../Modules/TeamMcp/Http/routes.php)
- [HandoffAckTool.php](../../../Modules/TeamMcp/Mcp/Tools/HandoffAckTool.php)
- [HandoffLeverTool.php](../../../Modules/TeamMcp/Mcp/Tools/HandoffLeverTool.php)
- [HandoffPendingTool.php](../../../Modules/TeamMcp/Mcp/Tools/HandoffPendingTool.php)
- [HandoffSubmitTool.php](../../../Modules/TeamMcp/Mcp/Tools/HandoffSubmitTool.php)
- [start.php](../../../Modules/TeamMcp/start.php)
