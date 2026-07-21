---
name: "SUPERFÍCIE — Governance"
description: "Índice GERADO dos artefatos do módulo Governance reconhecidos pelo classificador, agrupados por papel. NÃO editar à mão."
type: reference
authority: generated
lifecycle: ativo
module: Governance
---

# 🗺️ Superfície de código — Governance

> ⚙️ **Gerado por máquina** (`scripts/governance/module-surface.mjs`). NÃO edite à mão — a próxima geração sobrescreve.
> Regenerar: `node scripts/governance/module-surface.mjs Governance --write`. Validar frescor: `--check` (exit 1 se a árvore mudou e isto não foi regenerado).
>
> **O que isto é:** os artefatos reconhecidos pelo classificador dentro de `Modules/Governance/**` + `resources/js/Pages/governance/**` (namespace Inertia `governance`, declarado em `module-surface.mjs::PAGES_NS` porque difere do nome do módulo `Governance`), separados por papel — inclusive telas e seus componentes sem confundir um com o outro. **O que NÃO é:** manifesto de todo byte da pasta, cobertura/nota/status por tela (donos: `screen-coverage-map.mjs` + `casos-gate`) nem âncoras cross-cutting (bridge em `app/`, FSM) — essas vivem narradas no [BRIEFING](BRIEFING.md), não aqui.

**Total mapeado:** 139 arquivos em 14 papéis.

## Controllers — 8

- [AuditController.php](../../../Modules/Governance/Http/Controllers/AuditController.php)
- [DashboardController.php](../../../Modules/Governance/Http/Controllers/DashboardController.php)
- [DataController.php](../../../Modules/Governance/Http/Controllers/DataController.php)
- [DriftAlertsController.php](../../../Modules/Governance/Http/Controllers/DriftAlertsController.php)
- [DsRolloutController.php](../../../Modules/Governance/Http/Controllers/DsRolloutController.php)
- [InstallController.php](../../../Modules/Governance/Http/Controllers/InstallController.php)
- [ModuleGradeController.php](../../../Modules/Governance/Http/Controllers/ModuleGradeController.php)
- [PoliciesController.php](../../../Modules/Governance/Http/Controllers/PoliciesController.php)

## Requests (validação) — 4

- [FilterAuditRequest.php](../../../Modules/Governance/Http/Requests/FilterAuditRequest.php)
- [GenerateReportRequest.php](../../../Modules/Governance/Http/Requests/GenerateReportRequest.php)
- [TogglePolicyRequest.php](../../../Modules/Governance/Http/Requests/TogglePolicyRequest.php)
- [UpdateActorRequest.php](../../../Modules/Governance/Http/Requests/UpdateActorRequest.php)

## Middleware — 1

- [ActionGate.php](../../../Modules/Governance/Http/Middleware/ActionGate.php)

## Services — 30

- [AdrPendenteBriefLineService.php](../../../Modules/Governance/Services/AdrPendenteBriefLineService.php)
- [AdrReviewBriefLineService.php](../../../Modules/Governance/Services/AdrReviewBriefLineService.php)
- [AgentOutcomeBriefSectionService.php](../../../Modules/Governance/Services/AgentOutcomeBriefSectionService.php)
- [AuditDrillDownService.php](../../../Modules/Governance/Services/AuditDrillDownService.php)
- [AdrLinksChecker.php](../../../Modules/Governance/Services/Checkers/AdrLinksChecker.php)
- [ChartersFreshnessChecker.php](../../../Modules/Governance/Services/Checkers/ChartersFreshnessChecker.php)
- [ComposerAuditChecker.php](../../../Modules/Governance/Services/Checkers/ComposerAuditChecker.php)
- [DeployDriftChecker.php](../../../Modules/Governance/Services/Checkers/DeployDriftChecker.php)
- [DesignDocsFreshnessChecker.php](../../../Modules/Governance/Services/Checkers/DesignDocsFreshnessChecker.php)
- [IngestLivenessChecker.php](../../../Modules/Governance/Services/Checkers/IngestLivenessChecker.php)
- [McpIndexFreshnessChecker.php](../../../Modules/Governance/Services/Checkers/McpIndexFreshnessChecker.php)
- [McpServedDriftChecker.php](../../../Modules/Governance/Services/Checkers/McpServedDriftChecker.php)
- [MeilisearchSettingsDriftChecker.php](../../../Modules/Governance/Services/Checkers/MeilisearchSettingsDriftChecker.php)
- [MultiTenantScopeChecker.php](../../../Modules/Governance/Services/Checkers/MultiTenantScopeChecker.php)
- [NpmAuditChecker.php](../../../Modules/Governance/Services/Checkers/NpmAuditChecker.php)
- [RoutesZombieChecker.php](../../../Modules/Governance/Services/Checkers/RoutesZombieChecker.php)
- [PersistsDriftAlert.php](../../../Modules/Governance/Services/Concerns/PersistsDriftAlert.php)
- [PublishesDriftToCentrifugo.php](../../../Modules/Governance/Services/Concerns/PublishesDriftToCentrifugo.php)
- [DriftAlertService.php](../../../Modules/Governance/Services/DriftAlertService.php)
- [DriftCheckResult.php](../../../Modules/Governance/Services/DriftCheckResult.php)
- [DriftCheckerRegistry.php](../../../Modules/Governance/Services/DriftCheckerRegistry.php)
- [DriftFinding.php](../../../Modules/Governance/Services/DriftFinding.php)
- [InitiativeService.php](../../../Modules/Governance/Services/InitiativeService.php)
- [ModuleGradeService.php](../../../Modules/Governance/Services/ModuleGradeService.php)
- [ObservabilitySnapshotService.php](../../../Modules/Governance/Services/ObservabilitySnapshotService.php)
- [PlanHealthBriefLineService.php](../../../Modules/Governance/Services/PlanHealthBriefLineService.php)
- [PolicyToggleService.php](../../../Modules/Governance/Services/PolicyToggleService.php)
- [ScopedScorecardEvaluator.php](../../../Modules/Governance/Services/ScopedScorecardEvaluator.php)
- [SddBriefLineService.php](../../../Modules/Governance/Services/SddBriefLineService.php)
- [ShippedLogBriefLineService.php](../../../Modules/Governance/Services/ShippedLogBriefLineService.php)

## Models / Entities — 1

- [Initiative.php](../../../Modules/Governance/Entities/Initiative.php)

## Console / Commands — 17

- [AdrReviewFlushCommand.php](../../../Modules/Governance/Console/Commands/AdrReviewFlushCommand.php)
- [CharterAuditCommand.php](../../../Modules/Governance/Console/Commands/CharterAuditCommand.php)
- [CharterHealthCommand.php](../../../Modules/Governance/Console/Commands/CharterHealthCommand.php)
- [CharterMetricsCommand.php](../../../Modules/Governance/Console/Commands/CharterMetricsCommand.php)
- [CicloDiarioGovernancaCommand.php](../../../Modules/Governance/Console/Commands/CicloDiarioGovernancaCommand.php)
- [DetectDriftCommand.php](../../../Modules/Governance/Console/Commands/DetectDriftCommand.php)
- [GovernancaScorecardCommand.php](../../../Modules/Governance/Console/Commands/GovernancaScorecardCommand.php)
- [GovernanceAuditCommand.php](../../../Modules/Governance/Console/Commands/GovernanceAuditCommand.php)
- [GovernanceHealthCommand.php](../../../Modules/Governance/Console/Commands/GovernanceHealthCommand.php)
- [ModuleGradeCommand.php](../../../Modules/Governance/Console/Commands/ModuleGradeCommand.php)
- [ModuleGradeSnapshotCommand.php](../../../Modules/Governance/Console/Commands/ModuleGradeSnapshotCommand.php)
- [ModuleGradeV4Command.php](../../../Modules/Governance/Console/Commands/ModuleGradeV4Command.php)
- [ObservabilityAggregateCommand.php](../../../Modules/Governance/Console/Commands/ObservabilityAggregateCommand.php)
- [RecordStagingFreshnessAlertCommand.php](../../../Modules/Governance/Console/Commands/RecordStagingFreshnessAlertCommand.php)
- [ScorecardInitiativeSyncCommand.php](../../../Modules/Governance/Console/Commands/ScorecardInitiativeSyncCommand.php)
- [ScorecardSnapshotCommand.php](../../../Modules/Governance/Console/Commands/ScorecardSnapshotCommand.php)
- [SddScorecardSnapshotCommand.php](../../../Modules/Governance/Console/Commands/SddScorecardSnapshotCommand.php)

## Providers — 1

- [GovernanceServiceProvider.php](../../../Modules/Governance/Providers/GovernanceServiceProvider.php)

## Migrations (schema) — 5

- [2026_05_16_120000_create_mcp_module_grades_history_table.php](../../../Modules/Governance/Database/Migrations/2026_05_16_120000_create_mcp_module_grades_history_table.php)
- [2026_05_17_000001_create_mcp_scorecard_runs_table.php](../../../Modules/Governance/Database/Migrations/2026_05_17_000001_create_mcp_scorecard_runs_table.php)
- [2026_05_17_000002_create_mcp_observability_spans_table.php](../../../Modules/Governance/Database/Migrations/2026_05_17_000002_create_mcp_observability_spans_table.php)
- [2026_05_17_000003_create_mcp_governance_initiatives_table.php](../../../Modules/Governance/Database/Migrations/2026_05_17_000003_create_mcp_governance_initiatives_table.php)
- [2026_06_12_100000_create_mcp_sdd_scorecard_history_table.php](../../../Modules/Governance/Database/Migrations/2026_06_12_100000_create_mcp_sdd_scorecard_history_table.php)

## Config — 2

- [config.php](../../../Modules/Governance/Config/config.php)
- [retention.php](../../../Modules/Governance/Config/retention.php)

## Telas (Inertia/React) — 7

- [Audit.tsx](../../../resources/js/Pages/governance/Audit.tsx)
- [Dashboard.tsx](../../../resources/js/Pages/governance/Dashboard.tsx)
- [DriftAlerts.tsx](../../../resources/js/Pages/governance/DriftAlerts.tsx)
- [DsRollout.tsx](../../../resources/js/Pages/governance/DsRollout.tsx)
- [Index.tsx](../../../resources/js/Pages/governance/ModuleGrades/Index.tsx)
- [Show.tsx](../../../resources/js/Pages/governance/ModuleGrades/Show.tsx)
- [Policies.tsx](../../../resources/js/Pages/governance/Policies.tsx)

## Charters (lei da tela) — 7

- [Audit.charter.md](../../../resources/js/Pages/governance/Audit.charter.md)
- [Dashboard.charter.md](../../../resources/js/Pages/governance/Dashboard.charter.md)
- [DriftAlerts.charter.md](../../../resources/js/Pages/governance/DriftAlerts.charter.md)
- [DsRollout.charter.md](../../../resources/js/Pages/governance/DsRollout.charter.md)
- [Index.charter.md](../../../resources/js/Pages/governance/ModuleGrades/Index.charter.md)
- [Show.charter.md](../../../resources/js/Pages/governance/ModuleGrades/Show.charter.md)
- [Policies.charter.md](../../../resources/js/Pages/governance/Policies.charter.md)

## Casos (contrato UC) — 1

- [DsRollout.casos.md](../../../resources/js/Pages/governance/DsRollout.casos.md)

## Testes (Pest) — 52

- 52 arquivos em [Modules/Governance/Tests/Feature/](../../../Modules/Governance/Tests/Feature) — cobertura é do `casos-gate`/`screen-coverage`, não deste índice.

## Outros (raiz/misc) — 3

- [DriftChecker.php](../../../Modules/Governance/Contracts/DriftChecker.php)
- [routes.php](../../../Modules/Governance/Http/routes.php)
- [start.php](../../../Modules/Governance/start.php)
