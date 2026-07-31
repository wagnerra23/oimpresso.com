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

**Total mapeado:** 47 arquivos em 11 papéis.

## Controllers — 3

- [DataController.php](../../../Modules/TeamMcp/Http/Controllers/DataController.php)
- [ForjaController.php](../../../Modules/TeamMcp/Http/Controllers/ForjaController.php)
- [InstallController.php](../../../Modules/TeamMcp/Http/Controllers/InstallController.php)

## Services — 5

- [ForjaBacklogService.php](../../../Modules/TeamMcp/Services/Forja/ForjaBacklogService.php)
- [ForjaChangelogService.php](../../../Modules/TeamMcp/Services/Forja/ForjaChangelogService.php)
- [ForjaMcpService.php](../../../Modules/TeamMcp/Services/Forja/ForjaMcpService.php)
- [ForjaQuadroService.php](../../../Modules/TeamMcp/Services/Forja/ForjaQuadroService.php)
- [PrChecksResolver.php](../../../Modules/TeamMcp/Services/PrChecksResolver.php)

## Providers — 1

- [TeamMcpServiceProvider.php](../../../Modules/TeamMcp/Providers/TeamMcpServiceProvider.php)

## Seeders — 1

- [ForjaDemoTicketsSeeder.php](../../../Modules/TeamMcp/Database/Seeders/ForjaDemoTicketsSeeder.php)

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

## Testes (Pest) — 11

- 11 arquivos em [Modules/TeamMcp/Tests/Feature/](../../../Modules/TeamMcp/Tests/Feature) — cobertura é do `casos-gate`/`screen-coverage`, não deste índice.

## Outros (raiz/misc) — 2

- [routes.php](../../../Modules/TeamMcp/Http/routes.php)
- [start.php](../../../Modules/TeamMcp/start.php)
