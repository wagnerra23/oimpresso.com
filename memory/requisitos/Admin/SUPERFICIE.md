---
name: "SUPERFÍCIE — Admin"
description: "Índice GERADO dos artefatos do módulo Admin reconhecidos pelo classificador, agrupados por papel. NÃO editar à mão."
type: reference
authority: generated
lifecycle: ativo
module: Admin
---

# 🗺️ Superfície de código — Admin

> ⚙️ **Gerado por máquina** (`scripts/governance/module-surface.mjs`). NÃO edite à mão — a próxima geração sobrescreve.
> Regenerar: `node scripts/governance/module-surface.mjs Admin --write`. Validar frescor: `--check` (exit 1 se a árvore mudou e isto não foi regenerado).
>
> **O que isto é:** os artefatos reconhecidos pelo classificador dentro de `Modules/Admin/**` + `resources/js/Pages/Admin/**`, separados por papel — inclusive telas e seus componentes sem confundir um com o outro. **O que NÃO é:** manifesto de todo byte da pasta, cobertura/nota/status por tela (donos: `screen-coverage-map.mjs` + `casos-gate`) nem âncoras cross-cutting (bridge em `app/`, FSM) — essas vivem narradas no [BRIEFING](BRIEFING.md), não aqui.

**Total mapeado:** 97 arquivos em 13 papéis.

## Controllers — 8

- [DataController.php](../../../Modules/Admin/Http/Controllers/DataController.php)
- [FeatureFlagsController.php](../../../Modules/Admin/Http/Controllers/FeatureFlagsController.php)
- [GovernanceV4DashboardController.php](../../../Modules/Admin/Http/Controllers/GovernanceV4DashboardController.php)
- [IndexController.php](../../../Modules/Admin/Http/Controllers/IndexController.php)
- [InstallController.php](../../../Modules/Admin/Http/Controllers/InstallController.php)
- [MutationsController.php](../../../Modules/Admin/Http/Controllers/MutationsController.php)
- [RagQualityDashboardController.php](../../../Modules/Admin/Http/Controllers/RagQualityDashboardController.php)
- [ScreenReviewController.php](../../../Modules/Admin/Http/Controllers/ScreenReviewController.php)

## Requests (validação) — 7

- [AlertAcknowledgeRequest.php](../../../Modules/Admin/Http/Requests/AlertAcknowledgeRequest.php)
- [CreateInitiativeRequest.php](../../../Modules/Admin/Http/Requests/CreateInitiativeRequest.php)
- [OverrideBucketRequest.php](../../../Modules/Admin/Http/Requests/OverrideBucketRequest.php)
- [RemediationRequest.php](../../../Modules/Admin/Http/Requests/RemediationRequest.php)
- [StoreUserRequest.php](../../../Modules/Admin/Http/Requests/StoreUserRequest.php)
- [UpdatePermissionRequest.php](../../../Modules/Admin/Http/Requests/UpdatePermissionRequest.php)
- [UpdateReviewStatusRequest.php](../../../Modules/Admin/Http/Requests/UpdateReviewStatusRequest.php)

## Middleware — 2

- [IsWagner.php](../../../Modules/Admin/Http/Middleware/IsWagner.php)
- [TailscaleOnly.php](../../../Modules/Admin/Http/Middleware/TailscaleOnly.php)

## Services — 12

- [AdminAuditLogger.php](../../../Modules/Admin/Services/AdminAuditLogger.php)
- [AdrAlertReader.php](../../../Modules/Admin/Services/AdrAlertReader.php)
- [BrainBCostReader.php](../../../Modules/Admin/Services/BrainBCostReader.php)
- [BriefAdapter.php](../../../Modules/Admin/Services/BriefAdapter.php)
- [CentrifugoAdminChannel.php](../../../Modules/Admin/Services/CentrifugoAdminChannel.php)
- [CuradorStatsReader.php](../../../Modules/Admin/Services/CuradorStatsReader.php)
- [CyclesAggregator.php](../../../Modules/Admin/Services/CyclesAggregator.php)
- [HealthSnapshotReader.php](../../../Modules/Admin/Services/HealthSnapshotReader.php)
- [InfraStatusReader.php](../../../Modules/Admin/Services/InfraStatusReader.php)
- [McpServerHealthReader.php](../../../Modules/Admin/Services/McpServerHealthReader.php)
- [SessionsReader.php](../../../Modules/Admin/Services/SessionsReader.php)
- [VaultwardenReader.php](../../../Modules/Admin/Services/VaultwardenReader.php)

## Console / Commands — 3

- [AdminHealthCommand.php](../../../Modules/Admin/Console/Commands/AdminHealthCommand.php)
- [ExportAuditCommand.php](../../../Modules/Admin/Console/Commands/ExportAuditCommand.php)
- [ScreenCatalogGenerateCommand.php](../../../Modules/Admin/Console/Commands/ScreenCatalogGenerateCommand.php)

## Providers — 1

- [AdminServiceProvider.php](../../../Modules/Admin/Providers/AdminServiceProvider.php)

## Rotas — 1

- [web.php](../../../Modules/Admin/Routes/web.php)

## Migrations (schema) — 1

- [2026_05_10_000001_create_mcp_admin_audit_log_table.php](../../../Modules/Admin/Database/Migrations/2026_05_10_000001_create_mcp_admin_audit_log_table.php)

## Config — 2

- [config.php](../../../Modules/Admin/Config/config.php)
- [retention.php](../../../Modules/Admin/Config/retention.php)

## Telas (Inertia/React) — 8

- [Index.tsx](../../../resources/js/Pages/Admin/FeatureFlags/Index.tsx)
- [Show.tsx](../../../resources/js/Pages/Admin/FeatureFlags/Show.tsx)
- [GovernanceV4.tsx](../../../resources/js/Pages/Admin/GovernanceV4.tsx)
- [GovernanceV4Dashboard.tsx](../../../resources/js/Pages/Admin/GovernanceV4Dashboard.tsx)
- [Index.tsx](../../../resources/js/Pages/Admin/Index.tsx)
- [RagQualityDashboard.tsx](../../../resources/js/Pages/Admin/RagQualityDashboard.tsx)
- [ScreenReview.tsx](../../../resources/js/Pages/Admin/ScreenReview.tsx)
- [ScreenReviewDashboard.tsx](../../../resources/js/Pages/Admin/ScreenReviewDashboard.tsx)

## Componentes / apoio de tela — 25

- [AiSuggestionPanel.tsx](../../../resources/js/Pages/Admin/_components/AiSuggestionPanel.tsx)
- [BucketSidebar.tsx](../../../resources/js/Pages/Admin/_components/BucketSidebar.tsx)
- [CommandPaletteV4.tsx](../../../resources/js/Pages/Admin/_components/CommandPaletteV4.tsx)
- [DimensionProgressBar.tsx](../../../resources/js/Pages/Admin/_components/DimensionProgressBar.tsx)
- [DriftAlertBanner.tsx](../../../resources/js/Pages/Admin/_components/DriftAlertBanner.tsx)
- [HealthPanelV4.tsx](../../../resources/js/Pages/Admin/_components/HealthPanelV4.tsx)
- [InitiativeBadge.tsx](../../../resources/js/Pages/Admin/_components/InitiativeBadge.tsx)
- [ModuleList.tsx](../../../resources/js/Pages/Admin/_components/ModuleList.tsx)
- [ModuleReader.tsx](../../../resources/js/Pages/Admin/_components/ModuleReader.tsx)
- [ReviewReader.tsx](../../../resources/js/Pages/Admin/_components/ReviewReader.tsx)
- [RoundBadge.tsx](../../../resources/js/Pages/Admin/_components/RoundBadge.tsx)
- [ScreenList.tsx](../../../resources/js/Pages/Admin/_components/ScreenList.tsx)
- [ScreenReviewSidebar.tsx](../../../resources/js/Pages/Admin/_components/ScreenReviewSidebar.tsx)
- [SparklineTrend.tsx](../../../resources/js/Pages/Admin/_components/SparklineTrend.tsx)
- [WidgetAdrTier0.tsx](../../../resources/js/Pages/Admin/_components/WidgetAdrTier0.tsx)
- [WidgetBrainBCost.tsx](../../../resources/js/Pages/Admin/_components/WidgetBrainBCost.tsx)
- [WidgetBrief.tsx](../../../resources/js/Pages/Admin/_components/WidgetBrief.tsx)
- [WidgetCurador.tsx](../../../resources/js/Pages/Admin/_components/WidgetCurador.tsx)
- [WidgetCycles.tsx](../../../resources/js/Pages/Admin/_components/WidgetCycles.tsx)
- [WidgetHealth.tsx](../../../resources/js/Pages/Admin/_components/WidgetHealth.tsx)
- [WidgetInfraStatus.tsx](../../../resources/js/Pages/Admin/_components/WidgetInfraStatus.tsx)
- [WidgetMcpServer.tsx](../../../resources/js/Pages/Admin/_components/WidgetMcpServer.tsx)
- [WidgetMutations.tsx](../../../resources/js/Pages/Admin/_components/WidgetMutations.tsx)
- [WidgetSessions.tsx](../../../resources/js/Pages/Admin/_components/WidgetSessions.tsx)
- [WidgetVaultwarden.tsx](../../../resources/js/Pages/Admin/_components/WidgetVaultwarden.tsx)

## Charters (lei da tela) — 8

- [Index.charter.md](../../../resources/js/Pages/Admin/FeatureFlags/Index.charter.md)
- [Show.charter.md](../../../resources/js/Pages/Admin/FeatureFlags/Show.charter.md)
- [GovernanceV4.charter.md](../../../resources/js/Pages/Admin/GovernanceV4.charter.md)
- [GovernanceV4Dashboard.charter.md](../../../resources/js/Pages/Admin/GovernanceV4Dashboard.charter.md)
- [Index.charter.md](../../../resources/js/Pages/Admin/Index.charter.md)
- [RagQualityDashboard.charter.md](../../../resources/js/Pages/Admin/RagQualityDashboard.charter.md)
- [ScreenReview.charter.md](../../../resources/js/Pages/Admin/ScreenReview.charter.md)
- [ScreenReviewDashboard.charter.md](../../../resources/js/Pages/Admin/ScreenReviewDashboard.charter.md)

## Testes (Pest) — 19

- 19 arquivos em [Modules/Admin/Tests/Feature/](../../../Modules/Admin/Tests/Feature) — cobertura é do `casos-gate`/`screen-coverage`, não deste índice.
