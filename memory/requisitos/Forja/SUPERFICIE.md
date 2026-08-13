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
> **O que isto é:** o inventário completo das raízes `Modules/Forja/**` + `resources/js/Pages/Forja/**` + `resources/js/Pages/team-mcp/**` (namespaces Inertia `Forja`, `team-mcp`, declarados em `module-surface.mjs::PAGES_NS` porque diferem do nome do módulo `Forja` — confira com `--namespaces`), separado por papel — inclusive manifestos, documentação local, telas e componentes. **O que NÃO é:** cobertura/nota/status por tela (donos: `screen-coverage-map.mjs` + `casos-gate`), nem qual endpoint ainda entrega Blade em vez de Inertia (dono: `blade-migration-census.mjs` — este índice lista o arquivo, não a camada que a rota serve; a fila por módulo sai em `npm run migracao:report`), nem âncoras cross-cutting fora dessas raízes (bridge em `app/`, FSM) — essas são relações estruturadas do [SCOPE](SCOPE.md) e fatos do [BRIEFING](BRIEFING.md).

**Total mapeado:** 304 arquivos em 15 papéis.

## Controllers — 26

- [ActivityController.php](../../../Modules/Forja/Http/Controllers/ActivityController.php)
- [ProjectsController.php](../../../Modules/Forja/Http/Controllers/Admin/ProjectsController.php)
- [TeamScopesController.php](../../../Modules/Forja/Http/Controllers/Admin/TeamScopesController.php)
- [ToolsController.php](../../../Modules/Forja/Http/Controllers/Admin/ToolsController.php)
- [AprovacoesController.php](../../../Modules/Forja/Http/Controllers/AprovacoesController.php)
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
- [RoadmapGanttController.php](../../../Modules/Forja/Http/Controllers/RoadmapGanttController.php)
- [ScorecardController.php](../../../Modules/Forja/Http/Controllers/ScorecardController.php)
- [SearchController.php](../../../Modules/Forja/Http/Controllers/SearchController.php)
- [TasksAdminController.php](../../../Modules/Forja/Http/Controllers/TasksAdminController.php)
- [TeamController.php](../../../Modules/Forja/Http/Controllers/TeamController.php)
- [TrabalhoController.php](../../../Modules/Forja/Http/Controllers/TrabalhoController.php)
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

## Services — 28

- [ActorResolver.php](../../../Modules/Forja/Services/ActorResolver.php)
- [BriefGeneratorService.php](../../../Modules/Forja/Services/BriefGeneratorService.php)
- [BriefValidator.php](../../../Modules/Forja/Services/BriefValidator.php)
- [CcIngestService.php](../../../Modules/Forja/Services/CcIngestService.php)
- [DecisionLinksService.php](../../../Modules/Forja/Services/DecisionLinksService.php)
- [ForjaAprovacoesService.php](../../../Modules/Forja/Services/ForjaAprovacoesService.php)
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
- [TrabalhoService.php](../../../Modules/Forja/Services/TrabalhoService.php)
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

## Migrations (schema) — 66

- [2026_04_29_100001_create_mcp_scopes_table.php](../../../Modules/Forja/Database/Migrations/2026_04_29_100001_create_mcp_scopes_table.php)
- [2026_04_29_100002_create_mcp_user_scopes_table.php](../../../Modules/Forja/Database/Migrations/2026_04_29_100002_create_mcp_user_scopes_table.php)
- [2026_04_29_100003_create_mcp_tokens_table.php](../../../Modules/Forja/Database/Migrations/2026_04_29_100003_create_mcp_tokens_table.php)
- [2026_04_29_100004_create_mcp_quotas_table.php](../../../Modules/Forja/Database/Migrations/2026_04_29_100004_create_mcp_quotas_table.php)
- [2026_04_29_100005_create_mcp_audit_log_table.php](../../../Modules/Forja/Database/Migrations/2026_04_29_100005_create_mcp_audit_log_table.php)
- [2026_04_29_100006_create_mcp_usage_diaria_table.php](../../../Modules/Forja/Database/Migrations/2026_04_29_100006_create_mcp_usage_diaria_table.php)
- [2026_04_29_100007_create_mcp_alertas_table.php](../../../Modules/Forja/Database/Migrations/2026_04_29_100007_create_mcp_alertas_table.php)
- [2026_04_29_100008_create_mcp_memory_documents_table.php](../../../Modules/Forja/Database/Migrations/2026_04_29_100008_create_mcp_memory_documents_table.php)
- [2026_04_29_100009_create_mcp_memory_documents_history_table.php](../../../Modules/Forja/Database/Migrations/2026_04_29_100009_create_mcp_memory_documents_history_table.php)
- [2026_04_29_300001_create_mcp_cc_sessions_table.php](../../../Modules/Forja/Database/Migrations/2026_04_29_300001_create_mcp_cc_sessions_table.php)
- [2026_04_29_300002_create_mcp_cc_messages_table.php](../../../Modules/Forja/Database/Migrations/2026_04_29_300002_create_mcp_cc_messages_table.php)
- [2026_04_29_300003_create_mcp_cc_blobs_table.php](../../../Modules/Forja/Database/Migrations/2026_04_29_300003_create_mcp_cc_blobs_table.php)
- [2026_04_29_600001_create_mcp_alertas_eventos_table.php](../../../Modules/Forja/Database/Migrations/2026_04_29_600001_create_mcp_alertas_eventos_table.php)
- [2026_04_30_120001_expand_mcp_memory_documents_type_enum.php](../../../Modules/Forja/Database/Migrations/2026_04_30_120001_expand_mcp_memory_documents_type_enum.php)
- [2026_04_30_180001_create_mcp_tasks_table.php](../../../Modules/Forja/Database/Migrations/2026_04_30_180001_create_mcp_tasks_table.php)
- [2026_04_30_200001_add_business_id_to_mcp_memory_documents.php](../../../Modules/Forja/Database/Migrations/2026_04_30_200001_add_business_id_to_mcp_memory_documents.php)
- [2026_05_01_100001_add_typed_cols_to_mcp_memory_documents.php](../../../Modules/Forja/Database/Migrations/2026_05_01_100001_add_typed_cols_to_mcp_memory_documents.php)
- [2026_05_01_120001_create_mcp_task_comments_table.php](../../../Modules/Forja/Database/Migrations/2026_05_01_120001_create_mcp_task_comments_table.php)
- [2026_05_01_120002_create_mcp_task_events_table.php](../../../Modules/Forja/Database/Migrations/2026_05_01_120002_create_mcp_task_events_table.php)
- [2026_05_04_180001_create_mcp_jira_projects_table.php](../../../Modules/Forja/Database/Migrations/2026_05_04_180001_create_mcp_jira_projects_table.php)
- [2026_05_04_180002_create_mcp_epics_table.php](../../../Modules/Forja/Database/Migrations/2026_05_04_180002_create_mcp_epics_table.php)
- [2026_05_04_180003_create_mcp_cycles_table.php](../../../Modules/Forja/Database/Migrations/2026_05_04_180003_create_mcp_cycles_table.php)
- [2026_05_04_180004_create_mcp_cycle_goals_table.php](../../../Modules/Forja/Database/Migrations/2026_05_04_180004_create_mcp_cycle_goals_table.php)
- [2026_05_04_180005_create_mcp_components_table.php](../../../Modules/Forja/Database/Migrations/2026_05_04_180005_create_mcp_components_table.php)
- [2026_05_04_180006_create_mcp_workflows_table.php](../../../Modules/Forja/Database/Migrations/2026_05_04_180006_create_mcp_workflows_table.php)
- [2026_05_04_180007_create_mcp_issue_templates_table.php](../../../Modules/Forja/Database/Migrations/2026_05_04_180007_create_mcp_issue_templates_table.php)
- [2026_05_04_180008_create_mcp_views_table.php](../../../Modules/Forja/Database/Migrations/2026_05_04_180008_create_mcp_views_table.php)
- [2026_05_04_180009_create_mcp_inbox_notifications_table.php](../../../Modules/Forja/Database/Migrations/2026_05_04_180009_create_mcp_inbox_notifications_table.php)
- [2026_05_04_180010_create_mcp_task_dependencies_table.php](../../../Modules/Forja/Database/Migrations/2026_05_04_180010_create_mcp_task_dependencies_table.php)
- [2026_05_04_180011_create_mcp_task_watchers_table.php](../../../Modules/Forja/Database/Migrations/2026_05_04_180011_create_mcp_task_watchers_table.php)
- [2026_05_04_180012_create_mcp_task_attachments_table.php](../../../Modules/Forja/Database/Migrations/2026_05_04_180012_create_mcp_task_attachments_table.php)
- [2026_05_04_180013_create_mcp_task_memory_links_table.php](../../../Modules/Forja/Database/Migrations/2026_05_04_180013_create_mcp_task_memory_links_table.php)
- [2026_05_04_180014_create_mcp_git_links_table.php](../../../Modules/Forja/Database/Migrations/2026_05_04_180014_create_mcp_git_links_table.php)
- [2026_05_04_180015_extend_mcp_tasks_for_jira_style.php](../../../Modules/Forja/Database/Migrations/2026_05_04_180015_extend_mcp_tasks_for_jira_style.php)
- [2026_05_05_220001_create_mcp_skills_table.php](../../../Modules/Forja/Database/Migrations/2026_05_05_220001_create_mcp_skills_table.php)
- [2026_05_05_220002_create_mcp_skill_versions_table.php](../../../Modules/Forja/Database/Migrations/2026_05_05_220002_create_mcp_skill_versions_table.php)
- [2026_05_05_220003_create_mcp_skill_labels_table.php](../../../Modules/Forja/Database/Migrations/2026_05_05_220003_create_mcp_skill_labels_table.php)
- [2026_05_05_220004_create_mcp_skill_test_runs_table.php](../../../Modules/Forja/Database/Migrations/2026_05_05_220004_create_mcp_skill_test_runs_table.php)
- [2026_05_05_220005_create_mcp_skill_approvals_table.php](../../../Modules/Forja/Database/Migrations/2026_05_05_220005_create_mcp_skill_approvals_table.php)
- [2026_05_05_230001_add_immutability_triggers_to_mcp_audit_log.php](../../../Modules/Forja/Database/Migrations/2026_05_05_230001_add_immutability_triggers_to_mcp_audit_log.php)
- [2026_05_05_240001_create_mcp_actors_and_link_tokens.php](../../../Modules/Forja/Database/Migrations/2026_05_05_240001_create_mcp_actors_and_link_tokens.php)
- [2026_05_05_240002_seed_initial_actors.php](../../../Modules/Forja/Database/Migrations/2026_05_05_240002_seed_initial_actors.php)
- [2026_05_07_140000_update_actor_display_name_maiara.php](../../../Modules/Forja/Database/Migrations/2026_05_07_140000_update_actor_display_name_maiara.php)
- [2026_05_10_120000_seed_modulos_verticais_mcp_jira_projects.php](../../../Modules/Forja/Database/Migrations/2026_05_10_120000_seed_modulos_verticais_mcp_jira_projects.php)
- [2026_05_10_150000_seed_auditoria_mcp_jira_project.php](../../../Modules/Forja/Database/Migrations/2026_05_10_150000_seed_auditoria_mcp_jira_project.php)
- [2026_05_13_120000_create_mcp_handoff_summaries_table.php](../../../Modules/Forja/Database/Migrations/2026_05_13_120000_create_mcp_handoff_summaries_table.php)
- [2026_05_13_130000_create_mcp_handoff_diffs_table.php](../../../Modules/Forja/Database/Migrations/2026_05_13_130000_create_mcp_handoff_diffs_table.php)
- [2026_05_13_140000_create_mcp_weekly_digests_table.php](../../../Modules/Forja/Database/Migrations/2026_05_13_140000_create_mcp_weekly_digests_table.php)
- [2026_05_13_150000_create_mcp_doc_summaries_table.php](../../../Modules/Forja/Database/Migrations/2026_05_13_150000_create_mcp_doc_summaries_table.php)
- [2026_05_15_120000_add_contextual_context_to_mcp_memory_documents.php](../../../Modules/Forja/Database/Migrations/2026_05_15_120000_add_contextual_context_to_mcp_memory_documents.php)
- [2026_05_16_220001_create_mcp_scorecard_ai_suggestions_table.php](../../../Modules/Forja/Database/Migrations/2026_05_16_220001_create_mcp_scorecard_ai_suggestions_table.php)
- [2026_05_29_100001_create_mcp_automations_table.php](../../../Modules/Forja/Database/Migrations/2026_05_29_100001_create_mcp_automations_table.php)
- [2026_05_29_100002_create_mcp_automation_runs_table.php](../../../Modules/Forja/Database/Migrations/2026_05_29_100002_create_mcp_automation_runs_table.php)
- [2026_06_14_120000_add_soft_deletes_to_mcp_tokens_table.php](../../../Modules/Forja/Database/Migrations/2026_06_14_120000_add_soft_deletes_to_mcp_tokens_table.php)
- [2026_06_15_100000_create_mcp_ingest_heartbeat_table.php](../../../Modules/Forja/Database/Migrations/2026_06_15_100000_create_mcp_ingest_heartbeat_table.php)
- [2026_06_15_140000_create_mcp_work_leases_table.php](../../../Modules/Forja/Database/Migrations/2026_06_15_140000_create_mcp_work_leases_table.php)
- [2026_06_15_150000_add_acceptance_ref_to_mcp_tasks.php](../../../Modules/Forja/Database/Migrations/2026_06_15_150000_add_acceptance_ref_to_mcp_tasks.php)
- [2026_06_15_160000_add_immutability_triggers_to_mcp_task_events.php](../../../Modules/Forja/Database/Migrations/2026_06_15_160000_add_immutability_triggers_to_mcp_task_events.php)
- [2026_06_17_120000_create_cowork_handoffs_table.php](../../../Modules/Forja/Database/Migrations/2026_06_17_120000_create_cowork_handoffs_table.php)
- [2026_06_20_000001_add_hash_chain_to_mcp_audit_log.php](../../../Modules/Forja/Database/Migrations/2026_06_20_000001_add_hash_chain_to_mcp_audit_log.php)
- [2026_07_18_120000_add_model_to_mcp_cc_messages.php](../../../Modules/Forja/Database/Migrations/2026_07_18_120000_add_model_to_mcp_cc_messages.php)
- [2026_07_22_100000_add_briefing_surface_to_mcp_type_enum.php](../../../Modules/Forja/Database/Migrations/2026_07_22_100000_add_briefing_surface_to_mcp_type_enum.php)
- [2026_07_28_120000_create_mcp_handoff_drafts_table.php](../../../Modules/Forja/Database/Migrations/2026_07_28_120000_create_mcp_handoff_drafts_table.php)
- [2026_08_02_100000_add_charter_casos_to_mcp_type_enum.php](../../../Modules/Forja/Database/Migrations/2026_08_02_100000_add_charter_casos_to_mcp_type_enum.php)
- [2026_08_04_100000_add_feature_to_mcp_memory_documents_type_enum.php](../../../Modules/Forja/Database/Migrations/2026_08_04_100000_add_feature_to_mcp_memory_documents_type_enum.php)
- [2026_08_04_190000_add_pending_approval_status_to_mcp_tasks.php](../../../Modules/Forja/Database/Migrations/2026_08_04_190000_add_pending_approval_status_to_mcp_tasks.php)

## Seeders — 2

- [ForjaDemoTicketsSeeder.php](../../../Modules/Forja/Database/Seeders/ForjaDemoTicketsSeeder.php)
- [McpActorsSeeder.php](../../../Modules/Forja/Database/Seeders/McpActorsSeeder.php)

## Config — 4

- [brief-retention.php](../../../Modules/Forja/Config/brief-retention.php)
- [config.php](../../../Modules/Forja/Config/config.php)
- [retention-mcp.php](../../../Modules/Forja/Config/retention-mcp.php)
- [retention.php](../../../Modules/Forja/Config/retention.php)

## Telas (Inertia/React) — 21

- [Index.tsx](../../../Modules/Forja/Resources/js/Pages/Forja/Activity/Index.tsx)
- [Index.tsx](../../../Modules/Forja/Resources/js/Pages/Forja/Aprovacoes/Index.tsx)
- [Index.tsx](../../../Modules/Forja/Resources/js/Pages/Forja/Backlog/Index.tsx)
- [DetailSheet.tsx](../../../Modules/Forja/Resources/js/Pages/Forja/Board/DetailSheet.tsx)
- [Index.tsx](../../../Modules/Forja/Resources/js/Pages/Forja/Board/Index.tsx)
- [Index.tsx](../../../Modules/Forja/Resources/js/Pages/Forja/Burndown/Index.tsx)
- [Index.tsx](../../../Modules/Forja/Resources/js/Pages/Forja/Inbox/Index.tsx)
- [Index.tsx](../../../Modules/Forja/Resources/js/Pages/Forja/MyWork/Index.tsx)
- [Gantt.tsx](../../../Modules/Forja/Resources/js/Pages/Forja/Roadmap/Gantt.tsx)
- [Index.tsx](../../../Modules/Forja/Resources/js/Pages/Forja/Roadmap/Index.tsx)
- [Index.tsx](../../../Modules/Forja/Resources/js/Pages/Forja/Trabalho/Index.tsx)
- [Index.tsx](../../../Modules/Forja/Resources/js/Pages/Forja/Triage/Index.tsx)
- [ProjectShow.tsx](../../../Modules/Forja/Resources/js/Pages/ads/Admin/ProjectShow.tsx)
- [Projects.tsx](../../../Modules/Forja/Resources/js/Pages/ads/Admin/Projects.tsx)
- [TeamScopes.tsx](../../../Modules/Forja/Resources/js/Pages/ads/Admin/TeamScopes.tsx)
- [Tools.tsx](../../../Modules/Forja/Resources/js/Pages/ads/Admin/Tools.tsx)
- [Index.tsx](../../../Modules/Forja/Resources/js/Pages/team-mcp/CcSessions/Index.tsx)
- [Cockpit.tsx](../../../Modules/Forja/Resources/js/Pages/team-mcp/Forja/Cockpit.tsx)
- [Index.tsx](../../../Modules/Forja/Resources/js/Pages/team-mcp/Scorecard/Index.tsx)
- [Index.tsx](../../../Modules/Forja/Resources/js/Pages/team-mcp/Tasks/Index.tsx)
- [Index.tsx](../../../Modules/Forja/Resources/js/Pages/team-mcp/Team/Index.tsx)

## Componentes / apoio de tela — 13

- [ShortcutsOverlay.tsx](../../../Modules/Forja/Resources/js/Pages/Forja/Board/_components/ShortcutsOverlay.tsx)
- [TrabalhoQuadro.tsx](../../../Modules/Forja/Resources/js/Pages/Forja/Trabalho/_components/TrabalhoQuadro.tsx)
- [TriageDossier.tsx](../../../Modules/Forja/Resources/js/Pages/Forja/Triage/_components/TriageDossier.tsx)
- [SessionDrawer.tsx](../../../Modules/Forja/Resources/js/Pages/team-mcp/CcSessions/_components/SessionDrawer.tsx)
- [ForjaBacklog.tsx](../../../Modules/Forja/Resources/js/Pages/team-mcp/Forja/_components/ForjaBacklog.tsx)
- [ForjaChangelog.tsx](../../../Modules/Forja/Resources/js/Pages/team-mcp/Forja/_components/ForjaChangelog.tsx)
- [ForjaDossier.tsx](../../../Modules/Forja/Resources/js/Pages/team-mcp/Forja/_components/ForjaDossier.tsx)
- [ForjaHandoffs.tsx](../../../Modules/Forja/Resources/js/Pages/team-mcp/Forja/_components/ForjaHandoffs.tsx)
- [ForjaHub.tsx](../../../Modules/Forja/Resources/js/Pages/team-mcp/Forja/_components/ForjaHub.tsx)
- [ForjaMcp.tsx](../../../Modules/Forja/Resources/js/Pages/team-mcp/Forja/_components/ForjaMcp.tsx)
- [ForjaQuadro.tsx](../../../Modules/Forja/Resources/js/Pages/team-mcp/Forja/_components/ForjaQuadro.tsx)
- [ForjaTriage.tsx](../../../Modules/Forja/Resources/js/Pages/team-mcp/Forja/_components/ForjaTriage.tsx)
- [TaskDrawer.tsx](../../../Modules/Forja/Resources/js/Pages/team-mcp/Tasks/_components/TaskDrawer.tsx)

## Charters (lei da tela) — 21

- [Index.charter.md](../../../Modules/Forja/Resources/js/Pages/Forja/Activity/Index.charter.md)
- [Index.charter.md](../../../Modules/Forja/Resources/js/Pages/Forja/Aprovacoes/Index.charter.md)
- [Index.charter.md](../../../Modules/Forja/Resources/js/Pages/Forja/Backlog/Index.charter.md)
- [DetailSheet.charter.md](../../../Modules/Forja/Resources/js/Pages/Forja/Board/DetailSheet.charter.md)
- [Index.charter.md](../../../Modules/Forja/Resources/js/Pages/Forja/Board/Index.charter.md)
- [Index.charter.md](../../../Modules/Forja/Resources/js/Pages/Forja/Burndown/Index.charter.md)
- [Index.charter.md](../../../Modules/Forja/Resources/js/Pages/Forja/Inbox/Index.charter.md)
- [Index.charter.md](../../../Modules/Forja/Resources/js/Pages/Forja/MyWork/Index.charter.md)
- [Gantt.charter.md](../../../Modules/Forja/Resources/js/Pages/Forja/Roadmap/Gantt.charter.md)
- [Index.charter.md](../../../Modules/Forja/Resources/js/Pages/Forja/Roadmap/Index.charter.md)
- [Index.charter.md](../../../Modules/Forja/Resources/js/Pages/Forja/Trabalho/Index.charter.md)
- [Index.charter.md](../../../Modules/Forja/Resources/js/Pages/Forja/Triage/Index.charter.md)
- [ProjectShow.charter.md](../../../Modules/Forja/Resources/js/Pages/ads/Admin/ProjectShow.charter.md)
- [Projects.charter.md](../../../Modules/Forja/Resources/js/Pages/ads/Admin/Projects.charter.md)
- [TeamScopes.charter.md](../../../Modules/Forja/Resources/js/Pages/ads/Admin/TeamScopes.charter.md)
- [Tools.charter.md](../../../Modules/Forja/Resources/js/Pages/ads/Admin/Tools.charter.md)
- [Index.charter.md](../../../Modules/Forja/Resources/js/Pages/team-mcp/CcSessions/Index.charter.md)
- [Cockpit.charter.md](../../../Modules/Forja/Resources/js/Pages/team-mcp/Forja/Cockpit.charter.md)
- [Index.charter.md](../../../Modules/Forja/Resources/js/Pages/team-mcp/Scorecard/Index.charter.md)
- [Index.charter.md](../../../Modules/Forja/Resources/js/Pages/team-mcp/Tasks/Index.charter.md)
- [Index.charter.md](../../../Modules/Forja/Resources/js/Pages/team-mcp/Team/Index.charter.md)

## Casos (contrato UC) — 8

- [Index.casos.md](../../../Modules/Forja/Resources/js/Pages/Forja/Aprovacoes/Index.casos.md)
- [Index.casos.md](../../../Modules/Forja/Resources/js/Pages/Forja/Board/Index.casos.md)
- [Index.casos.md](../../../Modules/Forja/Resources/js/Pages/Forja/Inbox/Index.casos.md)
- [Gantt.casos.md](../../../Modules/Forja/Resources/js/Pages/Forja/Roadmap/Gantt.casos.md)
- [Index.casos.md](../../../Modules/Forja/Resources/js/Pages/Forja/Trabalho/Index.casos.md)
- [Index.casos.md](../../../Modules/Forja/Resources/js/Pages/Forja/Triage/Index.casos.md)
- [Cockpit.casos.md](../../../Modules/Forja/Resources/js/Pages/team-mcp/Forja/Cockpit.casos.md)
- [Index.casos.md](../../../Modules/Forja/Resources/js/Pages/team-mcp/Scorecard/Index.casos.md)

## Testes (Pest) — 55

- 54 em [Modules/Forja/Tests/Feature/](../../../Modules/Forja/Tests/Feature)
- 1 em [Modules/Forja/Tests/Feature/Roadmap/](../../../Modules/Forja/Tests/Feature/Roadmap)
- _Cobertura destes arquivos é do `casos-gate`/`screen-coverage`, não deste índice._

## Demais arquivos (manifestos, docs, assets e misc) — 25

- [ProjectDecomposerAgent.php](../../../Modules/Forja/Ai/Agents/ProjectDecomposerAgent.php)
- [Tool.php](../../../Modules/Forja/Contracts/Tool.php)
- [routes.php](../../../Modules/Forja/Http/routes.php)
- [BriefFetchTool.php](../../../Modules/Forja/Mcp/Tools/BriefFetchTool.php)
- [HandoffAckTool.php](../../../Modules/Forja/Mcp/Tools/HandoffAckTool.php)
- [HandoffLeverTool.php](../../../Modules/Forja/Mcp/Tools/HandoffLeverTool.php)
- [HandoffPendingTool.php](../../../Modules/Forja/Mcp/Tools/HandoffPendingTool.php)
- [HandoffSubmitTool.php](../../../Modules/Forja/Mcp/Tools/HandoffSubmitTool.php)
- [useBoardShortcuts.ts](../../../Modules/Forja/Resources/js/Pages/Forja/Board/_components/useBoardShortcuts.ts)
- [Index.design-spec.json](../../../Modules/Forja/Resources/js/Pages/Forja/Trabalho/Index.design-spec.json)
- [TrabalhoQuadro.design-spec.json](../../../Modules/Forja/Resources/js/Pages/Forja/Trabalho/_components/TrabalhoQuadro.design-spec.json)
- [sessionTokens.ts](../../../Modules/Forja/Resources/js/Pages/team-mcp/CcSessions/_components/sessionTokens.ts)
- [projectmgmt.php](../../../Modules/Forja/Resources/lang/en/projectmgmt.php)
- [projectmgmt.php](../../../Modules/Forja/Resources/lang/pt/projectmgmt.php)
- [topnav.php](../../../Modules/Forja/Resources/menus/topnav.php)
- [BoostToolAdapter.php](../../../Modules/Forja/Tools/BoostToolAdapter.php)
- [GitCommitWipTool.php](../../../Modules/Forja/Tools/GitCommitWipTool.php)
- [GitInspectTool.php](../../../Modules/Forja/Tools/GitInspectTool.php)
- [LogReaderTool.php](../../../Modules/Forja/Tools/LogReaderTool.php)
- [RunTestTool.php](../../../Modules/Forja/Tools/RunTestTool.php)
- [WriteFileTool.php](../../../Modules/Forja/Tools/WriteFileTool.php)
- [composer.json](../../../Modules/Forja/composer.json)
- [module.json](../../../Modules/Forja/module.json)
- [start.php](../../../Modules/Forja/start.php)
- [SCOPE.md](../../../memory/requisitos/Forja/SCOPE.md)
