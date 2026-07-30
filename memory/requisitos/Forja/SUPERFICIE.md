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

**Total mapeado:** 42 arquivos em 8 papéis.

## Controllers — 12

- [ActivityController.php](../../../Modules/Forja/Http/Controllers/ActivityController.php)
- [ProjectsController.php](../../../Modules/Forja/Http/Controllers/Admin/ProjectsController.php)
- [BacklogController.php](../../../Modules/Forja/Http/Controllers/BacklogController.php)
- [BoardController.php](../../../Modules/Forja/Http/Controllers/BoardController.php)
- [BurndownController.php](../../../Modules/Forja/Http/Controllers/BurndownController.php)
- [DataController.php](../../../Modules/Forja/Http/Controllers/DataController.php)
- [InboxController.php](../../../Modules/Forja/Http/Controllers/InboxController.php)
- [InstallController.php](../../../Modules/Forja/Http/Controllers/InstallController.php)
- [MyWorkController.php](../../../Modules/Forja/Http/Controllers/MyWorkController.php)
- [RoadmapController.php](../../../Modules/Forja/Http/Controllers/RoadmapController.php)
- [SearchController.php](../../../Modules/Forja/Http/Controllers/SearchController.php)
- [TriageController.php](../../../Modules/Forja/Http/Controllers/TriageController.php)

## Requests (validação) — 9

- [AddCommentRequest.php](../../../Modules/Forja/Http/Requests/AddCommentRequest.php)
- [AddSubtaskRequest.php](../../../Modules/Forja/Http/Requests/AddSubtaskRequest.php)
- [BulkBacklogRequest.php](../../../Modules/Forja/Http/Requests/BulkBacklogRequest.php)
- [StoreProjectRequest.php](../../../Modules/Forja/Http/Requests/StoreProjectRequest.php)
- [StoreTaskRequest.php](../../../Modules/Forja/Http/Requests/StoreTaskRequest.php)
- [UpdateProjectRequest.php](../../../Modules/Forja/Http/Requests/UpdateProjectRequest.php)
- [UpdateTaskRequest.php](../../../Modules/Forja/Http/Requests/UpdateTaskRequest.php)
- [UpdateTaskStatusRequest.php](../../../Modules/Forja/Http/Requests/UpdateTaskStatusRequest.php)
- [WatchTaskRequest.php](../../../Modules/Forja/Http/Requests/WatchTaskRequest.php)

## Services — 2

- [ForjaAuditService.php](../../../Modules/Forja/Services/ForjaAuditService.php)
- [ProjectService.php](../../../Modules/Forja/Services/ProjectService.php)

## Console / Commands — 1

- [ForjaHealthCommand.php](../../../Modules/Forja/Console/Commands/ForjaHealthCommand.php)

## Providers — 1

- [ForjaServiceProvider.php](../../../Modules/Forja/Providers/ForjaServiceProvider.php)

## Config — 2

- [config.php](../../../Modules/Forja/Config/config.php)
- [retention.php](../../../Modules/Forja/Config/retention.php)

## Testes (Pest) — 13

- 13 arquivos em [Modules/Forja/Tests/Feature/](../../../Modules/Forja/Tests/Feature) — cobertura é do `casos-gate`/`screen-coverage`, não deste índice.

## Outros (raiz/misc) — 2

- [routes.php](../../../Modules/Forja/Http/routes.php)
- [start.php](../../../Modules/Forja/start.php)
