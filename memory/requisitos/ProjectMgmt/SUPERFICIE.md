---
name: "SUPERFÍCIE — ProjectMgmt"
description: "Índice GERADO dos artefatos do módulo ProjectMgmt reconhecidos pelo classificador, agrupados por papel. NÃO editar à mão."
type: reference
authority: generated
lifecycle: ativo
module: ProjectMgmt
---

# 🗺️ Superfície de código — ProjectMgmt

> ⚙️ **Gerado por máquina** (`scripts/governance/module-surface.mjs`). NÃO edite à mão — a próxima geração sobrescreve.
> Regenerar: `node scripts/governance/module-surface.mjs ProjectMgmt --write`. Validar frescor: `--check` (exit 1 se a árvore mudou e isto não foi regenerado).
>
> **O que isto é:** os artefatos reconhecidos pelo classificador dentro de `Modules/ProjectMgmt/**` + `resources/js/Pages/ProjectMgmt/**`, separados por papel — inclusive telas e seus componentes sem confundir um com o outro. **O que NÃO é:** manifesto de todo byte da pasta, cobertura/nota/status por tela (donos: `screen-coverage-map.mjs` + `casos-gate`) nem âncoras cross-cutting (bridge em `app/`, FSM) — essas vivem narradas no [BRIEFING](BRIEFING.md), não aqui.

**Total mapeado:** 61 arquivos em 11 papéis.

## Controllers — 12

- [ActivityController.php](../../../Modules/ProjectMgmt/Http/Controllers/ActivityController.php)
- [ProjectsController.php](../../../Modules/ProjectMgmt/Http/Controllers/Admin/ProjectsController.php)
- [BacklogController.php](../../../Modules/ProjectMgmt/Http/Controllers/BacklogController.php)
- [BoardController.php](../../../Modules/ProjectMgmt/Http/Controllers/BoardController.php)
- [BurndownController.php](../../../Modules/ProjectMgmt/Http/Controllers/BurndownController.php)
- [DataController.php](../../../Modules/ProjectMgmt/Http/Controllers/DataController.php)
- [InboxController.php](../../../Modules/ProjectMgmt/Http/Controllers/InboxController.php)
- [InstallController.php](../../../Modules/ProjectMgmt/Http/Controllers/InstallController.php)
- [MyWorkController.php](../../../Modules/ProjectMgmt/Http/Controllers/MyWorkController.php)
- [RoadmapController.php](../../../Modules/ProjectMgmt/Http/Controllers/RoadmapController.php)
- [SearchController.php](../../../Modules/ProjectMgmt/Http/Controllers/SearchController.php)
- [TriageController.php](../../../Modules/ProjectMgmt/Http/Controllers/TriageController.php)

## Requests (validação) — 9

- [AddCommentRequest.php](../../../Modules/ProjectMgmt/Http/Requests/AddCommentRequest.php)
- [AddSubtaskRequest.php](../../../Modules/ProjectMgmt/Http/Requests/AddSubtaskRequest.php)
- [BulkBacklogRequest.php](../../../Modules/ProjectMgmt/Http/Requests/BulkBacklogRequest.php)
- [StoreProjectRequest.php](../../../Modules/ProjectMgmt/Http/Requests/StoreProjectRequest.php)
- [StoreTaskRequest.php](../../../Modules/ProjectMgmt/Http/Requests/StoreTaskRequest.php)
- [UpdateProjectRequest.php](../../../Modules/ProjectMgmt/Http/Requests/UpdateProjectRequest.php)
- [UpdateTaskRequest.php](../../../Modules/ProjectMgmt/Http/Requests/UpdateTaskRequest.php)
- [UpdateTaskStatusRequest.php](../../../Modules/ProjectMgmt/Http/Requests/UpdateTaskStatusRequest.php)
- [WatchTaskRequest.php](../../../Modules/ProjectMgmt/Http/Requests/WatchTaskRequest.php)

## Services — 2

- [ProjectMgmtAuditService.php](../../../Modules/ProjectMgmt/Services/ProjectMgmtAuditService.php)
- [ProjectService.php](../../../Modules/ProjectMgmt/Services/ProjectService.php)

## Console / Commands — 1

- [ProjectMgmtHealthCommand.php](../../../Modules/ProjectMgmt/Console/Commands/ProjectMgmtHealthCommand.php)

## Providers — 1

- [ProjectMgmtServiceProvider.php](../../../Modules/ProjectMgmt/Providers/ProjectMgmtServiceProvider.php)

## Config — 2

- [config.php](../../../Modules/ProjectMgmt/Config/config.php)
- [retention.php](../../../Modules/ProjectMgmt/Config/retention.php)

## Telas (Inertia/React) — 9

- [Index.tsx](../../../resources/js/Pages/ProjectMgmt/Activity/Index.tsx)
- [Index.tsx](../../../resources/js/Pages/ProjectMgmt/Backlog/Index.tsx)
- [DetailSheet.tsx](../../../resources/js/Pages/ProjectMgmt/Board/DetailSheet.tsx)
- [Index.tsx](../../../resources/js/Pages/ProjectMgmt/Board/Index.tsx)
- [Index.tsx](../../../resources/js/Pages/ProjectMgmt/Burndown/Index.tsx)
- [Index.tsx](../../../resources/js/Pages/ProjectMgmt/Inbox/Index.tsx)
- [Index.tsx](../../../resources/js/Pages/ProjectMgmt/MyWork/Index.tsx)
- [Index.tsx](../../../resources/js/Pages/ProjectMgmt/Roadmap/Index.tsx)
- [Index.tsx](../../../resources/js/Pages/ProjectMgmt/Triage/Index.tsx)

## Componentes / apoio de tela — 1

- [TriageDossier.tsx](../../../resources/js/Pages/ProjectMgmt/Triage/_components/TriageDossier.tsx)

## Charters (lei da tela) — 9

- [Index.charter.md](../../../resources/js/Pages/ProjectMgmt/Activity/Index.charter.md)
- [Index.charter.md](../../../resources/js/Pages/ProjectMgmt/Backlog/Index.charter.md)
- [DetailSheet.charter.md](../../../resources/js/Pages/ProjectMgmt/Board/DetailSheet.charter.md)
- [Index.charter.md](../../../resources/js/Pages/ProjectMgmt/Board/Index.charter.md)
- [Index.charter.md](../../../resources/js/Pages/ProjectMgmt/Burndown/Index.charter.md)
- [Index.charter.md](../../../resources/js/Pages/ProjectMgmt/Inbox/Index.charter.md)
- [Index.charter.md](../../../resources/js/Pages/ProjectMgmt/MyWork/Index.charter.md)
- [Index.charter.md](../../../resources/js/Pages/ProjectMgmt/Roadmap/Index.charter.md)
- [Index.charter.md](../../../resources/js/Pages/ProjectMgmt/Triage/Index.charter.md)

## Testes (Pest) — 13

- 13 arquivos em [Modules/ProjectMgmt/Tests/Feature/](../../../Modules/ProjectMgmt/Tests/Feature) — cobertura é do `casos-gate`/`screen-coverage`, não deste índice.

## Outros (raiz/misc) — 2

- [routes.php](../../../Modules/ProjectMgmt/Http/routes.php)
- [start.php](../../../Modules/ProjectMgmt/start.php)
