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
> **O que isto é:** o inventário completo das raízes `Modules/Governance/**` + `resources/js/Pages/governance/**` (namespace Inertia `governance`, declarado em `module-surface.mjs::PAGES_NS` porque difere do nome do módulo `Governance`), separado por papel — inclusive manifestos, documentação local, telas e componentes. **O que NÃO é:** cobertura/nota/status por tela (donos: `screen-coverage-map.mjs` + `casos-gate`), nem qual endpoint ainda entrega Blade em vez de Inertia (dono: `blade-migration-census.mjs` — este índice lista o arquivo, não a camada que a rota serve; a fila por módulo sai em `npm run migracao:report`), nem âncoras cross-cutting fora dessas raízes (bridge em `app/`, FSM) — essas são relações estruturadas do [SCOPE](../../../Modules/Governance/SCOPE.md) e fatos do [BRIEFING](BRIEFING.md).

**Total mapeado:** 169 arquivos em 15 papéis.

## Controllers — 10

- [AuditController.php](../../../Modules/Governance/Http/Controllers/AuditController.php)
- [CustosController.php](../../../Modules/Governance/Http/Controllers/CustosController.php)
- [DashboardController.php](../../../Modules/Governance/Http/Controllers/DashboardController.php)
- [DataController.php](../../../Modules/Governance/Http/Controllers/DataController.php)
- [DriftAlertsController.php](../../../Modules/Governance/Http/Controllers/DriftAlertsController.php)
- [DsRolloutController.php](../../../Modules/Governance/Http/Controllers/DsRolloutController.php)
- [InstallController.php](../../../Modules/Governance/Http/Controllers/InstallController.php)
- [ModuleGradeController.php](../../../Modules/Governance/Http/Controllers/ModuleGradeController.php)
- [PoliciesController.php](../../../Modules/Governance/Http/Controllers/PoliciesController.php)
- [QualidadeIaController.php](../../../Modules/Governance/Http/Controllers/QualidadeIaController.php)

## Requests (validação) — 4

- [FilterAuditRequest.php](../../../Modules/Governance/Http/Requests/FilterAuditRequest.php)
- [GenerateReportRequest.php](../../../Modules/Governance/Http/Requests/GenerateReportRequest.php)
- [TogglePolicyRequest.php](../../../Modules/Governance/Http/Requests/TogglePolicyRequest.php)
- [UpdateActorRequest.php](../../../Modules/Governance/Http/Requests/UpdateActorRequest.php)

## Middleware — 1

- [ActionGate.php](../../../Modules/Governance/Http/Middleware/ActionGate.php)

## Services — 36

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
- [PlanDriftChecker.php](../../../Modules/Governance/Services/Checkers/PlanDriftChecker.php)
- [RoutesZombieChecker.php](../../../Modules/Governance/Services/Checkers/RoutesZombieChecker.php)
- [PersistsDriftAlert.php](../../../Modules/Governance/Services/Concerns/PersistsDriftAlert.php)
- [PublishesDriftToCentrifugo.php](../../../Modules/Governance/Services/Concerns/PublishesDriftToCentrifugo.php)
- [DriftAlertService.php](../../../Modules/Governance/Services/DriftAlertService.php)
- [DriftCheckResult.php](../../../Modules/Governance/Services/DriftCheckResult.php)
- [DriftCheckerRegistry.php](../../../Modules/Governance/Services/DriftCheckerRegistry.php)
- [DriftFinding.php](../../../Modules/Governance/Services/DriftFinding.php)
- [ExposicaoTier0BriefLineService.php](../../../Modules/Governance/Services/ExposicaoTier0BriefLineService.php)
- [GovernanceRulesService.php](../../../Modules/Governance/Services/GovernanceRulesService.php)
- [InitiativeService.php](../../../Modules/Governance/Services/InitiativeService.php)
- [ModuleGradeService.php](../../../Modules/Governance/Services/ModuleGradeService.php)
- [ObraParadaBriefLineService.php](../../../Modules/Governance/Services/ObraParadaBriefLineService.php)
- [ObservabilitySnapshotService.php](../../../Modules/Governance/Services/ObservabilitySnapshotService.php)
- [PlanHealthBriefLineService.php](../../../Modules/Governance/Services/PlanHealthBriefLineService.php)
- [PolicyEngine.php](../../../Modules/Governance/Services/PolicyEngine.php)
- [PolicyResult.php](../../../Modules/Governance/Services/PolicyResult.php)
- [PolicyToggleService.php](../../../Modules/Governance/Services/PolicyToggleService.php)
- [ScopedScorecardEvaluator.php](../../../Modules/Governance/Services/ScopedScorecardEvaluator.php)
- [SddBriefLineService.php](../../../Modules/Governance/Services/SddBriefLineService.php)
- [ShippedLogBriefLineService.php](../../../Modules/Governance/Services/ShippedLogBriefLineService.php)

## Models / Entities — 1

- [Initiative.php](../../../Modules/Governance/Entities/Initiative.php)

## Console / Commands — 19

- [AdrReviewFlushCommand.php](../../../Modules/Governance/Console/Commands/AdrReviewFlushCommand.php)
- [BladeMigrationSentinelCommand.php](../../../Modules/Governance/Console/Commands/BladeMigrationSentinelCommand.php)
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
- [ObservabilityAggregateCommand.php](../../../Modules/Governance/Console/Commands/ObservabilityAggregateCommand.php)
- [RecordRagasEvalAlertCommand.php](../../../Modules/Governance/Console/Commands/RecordRagasEvalAlertCommand.php)
- [RecordStagingFreshnessAlertCommand.php](../../../Modules/Governance/Console/Commands/RecordStagingFreshnessAlertCommand.php)
- [ScorecardInitiativeSyncCommand.php](../../../Modules/Governance/Console/Commands/ScorecardInitiativeSyncCommand.php)
- [ScorecardSnapshotCommand.php](../../../Modules/Governance/Console/Commands/ScorecardSnapshotCommand.php)
- [SddScorecardSnapshotCommand.php](../../../Modules/Governance/Console/Commands/SddScorecardSnapshotCommand.php)
- [UiCatalogGenerateCommand.php](../../../Modules/Governance/Console/Commands/UiCatalogGenerateCommand.php)

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

## Telas (Inertia/React) — 9

- [Audit.tsx](../../../resources/js/Pages/governance/Audit.tsx)
- [Custos.tsx](../../../resources/js/Pages/governance/Custos.tsx)
- [Dashboard.tsx](../../../resources/js/Pages/governance/Dashboard.tsx)
- [DriftAlerts.tsx](../../../resources/js/Pages/governance/DriftAlerts.tsx)
- [DsRollout.tsx](../../../resources/js/Pages/governance/DsRollout.tsx)
- [Index.tsx](../../../resources/js/Pages/governance/ModuleGrades/Index.tsx)
- [Show.tsx](../../../resources/js/Pages/governance/ModuleGrades/Show.tsx)
- [Policies.tsx](../../../resources/js/Pages/governance/Policies.tsx)
- [QualidadeIa.tsx](../../../resources/js/Pages/governance/QualidadeIa.tsx)

## Componentes / apoio de tela — 1

- [GovernancaSubNav.tsx](../../../resources/js/Pages/governance/_shared/GovernancaSubNav.tsx)

## Charters (lei da tela) — 9

- [Audit.charter.md](../../../resources/js/Pages/governance/Audit.charter.md)
- [Custos.charter.md](../../../resources/js/Pages/governance/Custos.charter.md)
- [Dashboard.charter.md](../../../resources/js/Pages/governance/Dashboard.charter.md)
- [DriftAlerts.charter.md](../../../resources/js/Pages/governance/DriftAlerts.charter.md)
- [DsRollout.charter.md](../../../resources/js/Pages/governance/DsRollout.charter.md)
- [Index.charter.md](../../../resources/js/Pages/governance/ModuleGrades/Index.charter.md)
- [Show.charter.md](../../../resources/js/Pages/governance/ModuleGrades/Show.charter.md)
- [Policies.charter.md](../../../resources/js/Pages/governance/Policies.charter.md)
- [QualidadeIa.charter.md](../../../resources/js/Pages/governance/QualidadeIa.charter.md)

## Casos (contrato UC) — 1

- [DsRollout.casos.md](../../../resources/js/Pages/governance/DsRollout.casos.md)

## Testes (Pest) — 59

- 1 em [Modules/Governance/Tests/](../../../Modules/Governance/Tests)
- 56 em [Modules/Governance/Tests/Feature/](../../../Modules/Governance/Tests/Feature)
- 2 em [Modules/Governance/Tests/Unit/](../../../Modules/Governance/Tests/Unit)
- _Cobertura destes arquivos é do `casos-gate`/`screen-coverage`, não deste índice._

## Demais arquivos (manifestos, docs, assets e misc) — 11

- [CHANGELOG.md](../../../Modules/Governance/CHANGELOG.md)
- [DriftChecker.php](../../../Modules/Governance/Contracts/DriftChecker.php)
- [routes.php](../../../Modules/Governance/Http/routes.php)
- [governance.php](../../../Modules/Governance/Resources/lang/en/governance.php)
- [governance.php](../../../Modules/Governance/Resources/lang/pt-BR/governance.php)
- [governance.php](../../../Modules/Governance/Resources/lang/pt/governance.php)
- [topnav.php](../../../Modules/Governance/Resources/menus/topnav.php)
- [SCOPE.md](../../../Modules/Governance/SCOPE.md)
- [composer.json](../../../Modules/Governance/composer.json)
- [module.json](../../../Modules/Governance/module.json)
- [start.php](../../../Modules/Governance/start.php)
