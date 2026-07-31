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

**Total mapeado:** 90 arquivos em 11 papéis.

## Controllers — 13

- [ActivityController.php](../../../Modules/Forja/Http/Controllers/ActivityController.php)
- [ProjectsController.php](../../../Modules/Forja/Http/Controllers/Admin/ProjectsController.php)
- [BacklogController.php](../../../Modules/Forja/Http/Controllers/BacklogController.php)
- [BoardController.php](../../../Modules/Forja/Http/Controllers/BoardController.php)
- [BriefFetchController.php](../../../Modules/Forja/Http/Controllers/BriefFetchController.php)
- [BurndownController.php](../../../Modules/Forja/Http/Controllers/BurndownController.php)
- [DataController.php](../../../Modules/Forja/Http/Controllers/DataController.php)
- [InboxController.php](../../../Modules/Forja/Http/Controllers/InboxController.php)
- [InstallController.php](../../../Modules/Forja/Http/Controllers/InstallController.php)
- [MyWorkController.php](../../../Modules/Forja/Http/Controllers/MyWorkController.php)
- [RoadmapController.php](../../../Modules/Forja/Http/Controllers/RoadmapController.php)
- [SearchController.php](../../../Modules/Forja/Http/Controllers/SearchController.php)
- [TriageController.php](../../../Modules/Forja/Http/Controllers/TriageController.php)

## Requests (validação) — 18

- [AddCommentRequest.php](../../../Modules/Forja/Http/Requests/AddCommentRequest.php)
- [AddSubtaskRequest.php](../../../Modules/Forja/Http/Requests/AddSubtaskRequest.php)
- [BriefFetchToolRequest.php](../../../Modules/Forja/Http/Requests/BriefFetchToolRequest.php)
- [BulkBacklogRequest.php](../../../Modules/Forja/Http/Requests/BulkBacklogRequest.php)
- [CompareBriefRequest.php](../../../Modules/Forja/Http/Requests/CompareBriefRequest.php)
- [ExportBriefMarkdownRequest.php](../../../Modules/Forja/Http/Requests/ExportBriefMarkdownRequest.php)
- [FetchBriefHistoryRequest.php](../../../Modules/Forja/Http/Requests/FetchBriefHistoryRequest.php)
- [ForceRefreshBriefRequest.php](../../../Modules/Forja/Http/Requests/ForceRefreshBriefRequest.php)
- [GenerateBriefRequest.php](../../../Modules/Forja/Http/Requests/GenerateBriefRequest.php)
- [InvalidateBriefRequest.php](../../../Modules/Forja/Http/Requests/InvalidateBriefRequest.php)
- [MarkBriefValidRequest.php](../../../Modules/Forja/Http/Requests/MarkBriefValidRequest.php)
- [PurgeBriefHistoryRequest.php](../../../Modules/Forja/Http/Requests/PurgeBriefHistoryRequest.php)
- [StoreProjectRequest.php](../../../Modules/Forja/Http/Requests/StoreProjectRequest.php)
- [StoreTaskRequest.php](../../../Modules/Forja/Http/Requests/StoreTaskRequest.php)
- [UpdateProjectRequest.php](../../../Modules/Forja/Http/Requests/UpdateProjectRequest.php)
- [UpdateTaskRequest.php](../../../Modules/Forja/Http/Requests/UpdateTaskRequest.php)
- [UpdateTaskStatusRequest.php](../../../Modules/Forja/Http/Requests/UpdateTaskStatusRequest.php)
- [WatchTaskRequest.php](../../../Modules/Forja/Http/Requests/WatchTaskRequest.php)

## Services — 6

- [BriefGeneratorService.php](../../../Modules/Forja/Services/BriefGeneratorService.php)
- [BriefValidator.php](../../../Modules/Forja/Services/BriefValidator.php)
- [ForjaAuditService.php](../../../Modules/Forja/Services/ForjaAuditService.php)
- [LeaseBriefSectionService.php](../../../Modules/Forja/Services/LeaseBriefSectionService.php)
- [ProjectService.php](../../../Modules/Forja/Services/ProjectService.php)
- [ValidationResult.php](../../../Modules/Forja/Services/ValidationResult.php)

## Console / Commands — 4

- [BriefHealthCommand.php](../../../Modules/Forja/Console/Commands/BriefHealthCommand.php)
- [ForjaHealthCommand.php](../../../Modules/Forja/Console/Commands/ForjaHealthCommand.php)
- [GenerateBriefCommand.php](../../../Modules/Forja/Console/Commands/GenerateBriefCommand.php)
- [SkillTierReviewCommand.php](../../../Modules/Forja/Console/Commands/SkillTierReviewCommand.php)

## Providers — 1

- [ForjaServiceProvider.php](../../../Modules/Forja/Providers/ForjaServiceProvider.php)

## Config — 3

- [brief-retention.php](../../../Modules/Forja/Config/brief-retention.php)
- [config.php](../../../Modules/Forja/Config/config.php)
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

## Testes (Pest) — 23

- 23 arquivos em [Modules/Forja/Tests/Feature/](../../../Modules/Forja/Tests/Feature) — cobertura é do `casos-gate`/`screen-coverage`, não deste índice.

## Outros (raiz/misc) — 3

- [routes.php](../../../Modules/Forja/Http/routes.php)
- [BriefFetchTool.php](../../../Modules/Forja/Mcp/Tools/BriefFetchTool.php)
- [start.php](../../../Modules/Forja/start.php)
