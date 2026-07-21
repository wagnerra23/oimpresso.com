---
name: "SUPERFÍCIE — ADS"
description: "Índice GERADO dos artefatos do módulo ADS reconhecidos pelo classificador, agrupados por papel. NÃO editar à mão."
type: reference
authority: generated
lifecycle: ativo
module: ADS
---

# 🗺️ Superfície de código — ADS

> ⚙️ **Gerado por máquina** (`scripts/governance/module-surface.mjs`). NÃO edite à mão — a próxima geração sobrescreve.
> Regenerar: `node scripts/governance/module-surface.mjs ADS --write`. Validar frescor: `--check` (exit 1 se a árvore mudou e isto não foi regenerado).
>
> **O que isto é:** os artefatos reconhecidos pelo classificador dentro de `Modules/ADS/**` + `resources/js/Pages/ADS/**`, separados por papel — inclusive telas e seus componentes sem confundir um com o outro. **O que NÃO é:** manifesto de todo byte da pasta, cobertura/nota/status por tela (donos: `screen-coverage-map.mjs` + `casos-gate`) nem âncoras cross-cutting (bridge em `app/`, FSM) — essas vivem narradas no [BRIEFING](BRIEFING.md), não aqui.

**Total mapeado:** 152 arquivos em 14 papéis.

## Controllers — 15

- [ConfidenceController.php](../../../Modules/ADS/Http/Controllers/Admin/ConfidenceController.php)
- [ConflictsController.php](../../../Modules/ADS/Http/Controllers/Admin/ConflictsController.php)
- [DecisoesController.php](../../../Modules/ADS/Http/Controllers/Admin/DecisoesController.php)
- [LearningController.php](../../../Modules/ADS/Http/Controllers/Admin/LearningController.php)
- [MetaSkillsController.php](../../../Modules/ADS/Http/Controllers/Admin/MetaSkillsController.php)
- [MetricasController.php](../../../Modules/ADS/Http/Controllers/Admin/MetricasController.php)
- [PatternsController.php](../../../Modules/ADS/Http/Controllers/Admin/PatternsController.php)
- [PolicyController.php](../../../Modules/ADS/Http/Controllers/Admin/PolicyController.php)
- [SkillsController.php](../../../Modules/ADS/Http/Controllers/Admin/SkillsController.php)
- [ContextController.php](../../../Modules/ADS/Http/Controllers/Api/ContextController.php)
- [DecisionController.php](../../../Modules/ADS/Http/Controllers/Api/DecisionController.php)
- [RecentEventsController.php](../../../Modules/ADS/Http/Controllers/Api/RecentEventsController.php)
- [ScopeController.php](../../../Modules/ADS/Http/Controllers/Api/ScopeController.php)
- [DataController.php](../../../Modules/ADS/Http/Controllers/DataController.php)
- [InstallController.php](../../../Modules/ADS/Http/Controllers/InstallController.php)

## Requests (validação) — 16

- [ApproveDecisionRequest.php](../../../Modules/ADS/Http/Requests/ApproveDecisionRequest.php)
- [ApproveSkillVersionRequest.php](../../../Modules/ADS/Http/Requests/ApproveSkillVersionRequest.php)
- [DecomposeProjectRequest.php](../../../Modules/ADS/Http/Requests/DecomposeProjectRequest.php)
- [DismissDecisionRequest.php](../../../Modules/ADS/Http/Requests/DismissDecisionRequest.php)
- [ExecuteToolRequest.php](../../../Modules/ADS/Http/Requests/ExecuteToolRequest.php)
- [MoveSkillLabelRequest.php](../../../Modules/ADS/Http/Requests/MoveSkillLabelRequest.php)
- [PublishSkillVersionRequest.php](../../../Modules/ADS/Http/Requests/PublishSkillVersionRequest.php)
- [RejectDecisionRequest.php](../../../Modules/ADS/Http/Requests/RejectDecisionRequest.php)
- [RejectSkillVersionRequest.php](../../../Modules/ADS/Http/Requests/RejectSkillVersionRequest.php)
- [StoreGovernanceMetaSkillRequest.php](../../../Modules/ADS/Http/Requests/StoreGovernanceMetaSkillRequest.php)
- [StoreMetaSkillRequest.php](../../../Modules/ADS/Http/Requests/StoreMetaSkillRequest.php)
- [StoreSkillRequest.php](../../../Modules/ADS/Http/Requests/StoreSkillRequest.php)
- [StoreSkillVersionRequest.php](../../../Modules/ADS/Http/Requests/StoreSkillVersionRequest.php)
- [TestSkillRequest.php](../../../Modules/ADS/Http/Requests/TestSkillRequest.php)
- [ToggleMetaSkillRequest.php](../../../Modules/ADS/Http/Requests/ToggleMetaSkillRequest.php)
- [ValidateMetaSkillRuleRequest.php](../../../Modules/ADS/Http/Requests/ValidateMetaSkillRuleRequest.php)

## Middleware — 1

- [AdsApiAuth.php](../../../Modules/ADS/Http/Middleware/AdsApiAuth.php)

## Services — 22

- [AutoTaskGeneratorService.php](../../../Modules/ADS/Services/AutoTaskGeneratorService.php)
- [BrainBService.php](../../../Modules/ADS/Services/BrainBService.php)
- [ConfidenceEngine.php](../../../Modules/ADS/Services/ConfidenceEngine.php)
- [ContextForTaskService.php](../../../Modules/ADS/Services/ContextForTaskService.php)
- [DecisionLinksService.php](../../../Modules/ADS/Services/DecisionLinksService.php)
- [DecisionPresenter.php](../../../Modules/ADS/Services/DecisionPresenter.php)
- [DecisionRouter.php](../../../Modules/ADS/Services/DecisionRouter.php)
- [GovernanceRulesService.php](../../../Modules/ADS/Services/GovernanceRulesService.php)
- [PatternLearningService.php](../../../Modules/ADS/Services/PatternLearningService.php)
- [PlannerService.php](../../../Modules/ADS/Services/PlannerService.php)
- [PolicyEngine.php](../../../Modules/ADS/Services/PolicyEngine.php)
- [PolicyResult.php](../../../Modules/ADS/Services/PolicyResult.php)
- [ProjectDecomposerService.php](../../../Modules/ADS/Services/ProjectDecomposerService.php)
- [ReviewerService.php](../../../Modules/ADS/Services/ReviewerService.php)
- [RiskEngine.php](../../../Modules/ADS/Services/RiskEngine.php)
- [RiskResult.php](../../../Modules/ADS/Services/RiskResult.php)
- [RoutingDecision.php](../../../Modules/ADS/Services/RoutingDecision.php)
- [RoutingInput.php](../../../Modules/ADS/Services/RoutingInput.php)
- [ScaffoldSkillFromMissionService.php](../../../Modules/ADS/Services/ScaffoldSkillFromMissionService.php)
- [SkillsService.php](../../../Modules/ADS/Services/SkillsService.php)
- [ToolRegistry.php](../../../Modules/ADS/Services/ToolRegistry.php)
- [UserScopeService.php](../../../Modules/ADS/Services/UserScopeService.php)

## Console / Commands — 7

- [AdsHealthCommand.php](../../../Modules/ADS/Console/Commands/AdsHealthCommand.php)
- [AutoGenerateTasksCommand.php](../../../Modules/ADS/Console/Commands/AutoGenerateTasksCommand.php)
- [LearnPatternsCommand.php](../../../Modules/ADS/Console/Commands/LearnPatternsCommand.php)
- [PlanDecisionsCommand.php](../../../Modules/ADS/Console/Commands/PlanDecisionsCommand.php)
- [ProcessBrainBCommand.php](../../../Modules/ADS/Console/Commands/ProcessBrainBCommand.php)
- [ReviewDecisionsCommand.php](../../../Modules/ADS/Console/Commands/ReviewDecisionsCommand.php)
- [SkillScaffoldCommand.php](../../../Modules/ADS/Console/Commands/SkillScaffoldCommand.php)

## Providers — 2

- [AdsServiceProvider.php](../../../Modules/ADS/Providers/AdsServiceProvider.php)
- [RouteServiceProvider.php](../../../Modules/ADS/Providers/RouteServiceProvider.php)

## Rotas — 2

- [api.php](../../../Modules/ADS/Routes/api.php)
- [web.php](../../../Modules/ADS/Routes/web.php)

## Migrations (schema) — 15

- [2026_05_03_000001_create_mcp_file_locks_table.php](../../../Modules/ADS/Database/Migrations/2026_05_03_000001_create_mcp_file_locks_table.php)
- [2026_05_03_000002_create_mcp_decision_thresholds_table.php](../../../Modules/ADS/Database/Migrations/2026_05_03_000002_create_mcp_decision_thresholds_table.php)
- [2026_05_03_000003_create_mcp_confidence_scores_table.php](../../../Modules/ADS/Database/Migrations/2026_05_03_000003_create_mcp_confidence_scores_table.php)
- [2026_05_03_000004_create_mcp_dual_brain_decisions_table.php](../../../Modules/ADS/Database/Migrations/2026_05_03_000004_create_mcp_dual_brain_decisions_table.php)
- [2026_05_03_000005_create_mcp_decision_patterns_table.php](../../../Modules/ADS/Database/Migrations/2026_05_03_000005_create_mcp_decision_patterns_table.php)
- [2026_05_03_180001_add_dismissed_at_to_dual_brain_decisions.php](../../../Modules/ADS/Database/Migrations/2026_05_03_180001_add_dismissed_at_to_dual_brain_decisions.php)
- [2026_05_03_200001_add_learning_loop_columns.php](../../../Modules/ADS/Database/Migrations/2026_05_03_200001_add_learning_loop_columns.php)
- [2026_05_03_220001_create_mcp_governance_rules_table.php](../../../Modules/ADS/Database/Migrations/2026_05_03_220001_create_mcp_governance_rules_table.php)
- [2026_05_03_230001_create_mcp_projects_table.php](../../../Modules/ADS/Database/Migrations/2026_05_03_230001_create_mcp_projects_table.php)
- [2026_05_03_230002_create_mcp_project_parts_table.php](../../../Modules/ADS/Database/Migrations/2026_05_03_230002_create_mcp_project_parts_table.php)
- [2026_05_03_230003_link_decisions_to_projects.php](../../../Modules/ADS/Database/Migrations/2026_05_03_230003_link_decisions_to_projects.php)
- [2026_05_03_240001_create_mcp_tool_executions_table.php](../../../Modules/ADS/Database/Migrations/2026_05_03_240001_create_mcp_tool_executions_table.php)
- [2026_05_03_250001_create_mcp_decision_links_table.php](../../../Modules/ADS/Database/Migrations/2026_05_03_250001_create_mcp_decision_links_table.php)
- [2026_05_03_260001_create_mcp_user_module_access_table.php](../../../Modules/ADS/Database/Migrations/2026_05_03_260001_create_mcp_user_module_access_table.php)
- [2026_05_11_190001_repair_dual_brain_learning_loop_drift.php](../../../Modules/ADS/Database/Migrations/2026_05_11_190001_repair_dual_brain_learning_loop_drift.php)

## Seeders — 1

- [AdsAdminSkillsPermissionsSeeder.php](../../../Modules/ADS/Database/Seeders/AdsAdminSkillsPermissionsSeeder.php)

## Config — 2

- [config.php](../../../Modules/ADS/Config/config.php)
- [retention.php](../../../Modules/ADS/Config/retention.php)

## Telas (Inertia/React) — 19

- [Confidence.tsx](../../../resources/js/Pages/ADS/Admin/Confidence.tsx)
- [Conflicts.tsx](../../../resources/js/Pages/ADS/Admin/Conflicts.tsx)
- [DecisaoShow.tsx](../../../resources/js/Pages/ADS/Admin/DecisaoShow.tsx)
- [Decisoes.tsx](../../../resources/js/Pages/ADS/Admin/Decisoes.tsx)
- [Graph.tsx](../../../resources/js/Pages/ADS/Admin/Graph.tsx)
- [Learning.tsx](../../../resources/js/Pages/ADS/Admin/Learning.tsx)
- [MetaSkills.tsx](../../../resources/js/Pages/ADS/Admin/MetaSkills.tsx)
- [Metricas.tsx](../../../resources/js/Pages/ADS/Admin/Metricas.tsx)
- [Patterns.tsx](../../../resources/js/Pages/ADS/Admin/Patterns.tsx)
- [Policy.tsx](../../../resources/js/Pages/ADS/Admin/Policy.tsx)
- [ProjectShow.tsx](../../../resources/js/Pages/ADS/Admin/ProjectShow.tsx)
- [Projects.tsx](../../../resources/js/Pages/ADS/Admin/Projects.tsx)
- [Edit.tsx](../../../resources/js/Pages/ADS/Admin/Skills/Edit.tsx)
- [Index.tsx](../../../resources/js/Pages/ADS/Admin/Skills/Index.tsx)
- [Review.tsx](../../../resources/js/Pages/ADS/Admin/Skills/Review.tsx)
- [Show.tsx](../../../resources/js/Pages/ADS/Admin/Skills/Show.tsx)
- [Test.tsx](../../../resources/js/Pages/ADS/Admin/Skills/Test.tsx)
- [TeamScopes.tsx](../../../resources/js/Pages/ADS/Admin/TeamScopes.tsx)
- [Tools.tsx](../../../resources/js/Pages/ADS/Admin/Tools.tsx)

## Charters (lei da tela) — 19

- [Confidence.charter.md](../../../resources/js/Pages/ADS/Admin/Confidence.charter.md)
- [Conflicts.charter.md](../../../resources/js/Pages/ADS/Admin/Conflicts.charter.md)
- [DecisaoShow.charter.md](../../../resources/js/Pages/ADS/Admin/DecisaoShow.charter.md)
- [Decisoes.charter.md](../../../resources/js/Pages/ADS/Admin/Decisoes.charter.md)
- [Graph.charter.md](../../../resources/js/Pages/ADS/Admin/Graph.charter.md)
- [Learning.charter.md](../../../resources/js/Pages/ADS/Admin/Learning.charter.md)
- [MetaSkills.charter.md](../../../resources/js/Pages/ADS/Admin/MetaSkills.charter.md)
- [Metricas.charter.md](../../../resources/js/Pages/ADS/Admin/Metricas.charter.md)
- [Patterns.charter.md](../../../resources/js/Pages/ADS/Admin/Patterns.charter.md)
- [Policy.charter.md](../../../resources/js/Pages/ADS/Admin/Policy.charter.md)
- [ProjectShow.charter.md](../../../resources/js/Pages/ADS/Admin/ProjectShow.charter.md)
- [Projects.charter.md](../../../resources/js/Pages/ADS/Admin/Projects.charter.md)
- [Edit.charter.md](../../../resources/js/Pages/ADS/Admin/Skills/Edit.charter.md)
- [Index.charter.md](../../../resources/js/Pages/ADS/Admin/Skills/Index.charter.md)
- [Review.charter.md](../../../resources/js/Pages/ADS/Admin/Skills/Review.charter.md)
- [Show.charter.md](../../../resources/js/Pages/ADS/Admin/Skills/Show.charter.md)
- [Test.charter.md](../../../resources/js/Pages/ADS/Admin/Skills/Test.charter.md)
- [TeamScopes.charter.md](../../../resources/js/Pages/ADS/Admin/TeamScopes.charter.md)
- [Tools.charter.md](../../../resources/js/Pages/ADS/Admin/Tools.charter.md)

## Testes (Pest) — 19

- 19 arquivos em [Modules/ADS/Tests/Feature/](../../../Modules/ADS/Tests/Feature) — cobertura é do `casos-gate`/`screen-coverage`, não deste índice.

## Outros (raiz/misc) — 12

- [BrainBAgent.php](../../../Modules/ADS/Ai/Agents/BrainBAgent.php)
- [PlannerAgent.php](../../../Modules/ADS/Ai/Agents/PlannerAgent.php)
- [ProjectDecomposerAgent.php](../../../Modules/ADS/Ai/Agents/ProjectDecomposerAgent.php)
- [ReviewerAgent.php](../../../Modules/ADS/Ai/Agents/ReviewerAgent.php)
- [Tool.php](../../../Modules/ADS/Contracts/Tool.php)
- [BoostToolAdapter.php](../../../Modules/ADS/Tools/BoostToolAdapter.php)
- [GitCommitWipTool.php](../../../Modules/ADS/Tools/GitCommitWipTool.php)
- [GitInspectTool.php](../../../Modules/ADS/Tools/GitInspectTool.php)
- [LogReaderTool.php](../../../Modules/ADS/Tools/LogReaderTool.php)
- [MetricsQueryTool.php](../../../Modules/ADS/Tools/MetricsQueryTool.php)
- [RunTestTool.php](../../../Modules/ADS/Tools/RunTestTool.php)
- [WriteFileTool.php](../../../Modules/ADS/Tools/WriteFileTool.php)
