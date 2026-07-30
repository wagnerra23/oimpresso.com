---
name: "SUPERFÍCIE — Brief"
description: "Índice GERADO dos artefatos do módulo Brief reconhecidos pelo classificador, agrupados por papel. NÃO editar à mão."
type: reference
authority: generated
lifecycle: ativo
module: Brief
---

# 🗺️ Superfície de código — Brief

> ⚙️ **Gerado por máquina** (`scripts/governance/module-surface.mjs`). NÃO edite à mão — a próxima geração sobrescreve.
> Regenerar: `node scripts/governance/module-surface.mjs Brief --write`. Validar frescor: `--check` (exit 1 se a árvore mudou e isto não foi regenerado).
>
> **O que isto é:** o inventário completo das raízes `Modules/Brief/**` + `resources/js/Pages/Brief/**`, separado por papel — inclusive manifestos, documentação local, telas e componentes. **O que NÃO é:** cobertura/nota/status por tela (donos: `screen-coverage-map.mjs` + `casos-gate`) nem âncoras cross-cutting fora dessas raízes (bridge em `app/`, FSM) — essas são relações estruturadas do [SCOPE](../../../Modules/Brief/SCOPE.md) e fatos do [BRIEFING](BRIEFING.md).

**Total mapeado:** 38 arquivos em 9 papéis.

## Controllers — 3

- [BriefFetchController.php](../../../Modules/Brief/Http/Controllers/BriefFetchController.php)
- [DataController.php](../../../Modules/Brief/Http/Controllers/DataController.php)
- [InstallController.php](../../../Modules/Brief/Http/Controllers/InstallController.php)

## Requests (validação) — 9

- [BriefFetchToolRequest.php](../../../Modules/Brief/Http/Requests/BriefFetchToolRequest.php)
- [CompareBriefRequest.php](../../../Modules/Brief/Http/Requests/CompareBriefRequest.php)
- [ExportBriefMarkdownRequest.php](../../../Modules/Brief/Http/Requests/ExportBriefMarkdownRequest.php)
- [FetchBriefHistoryRequest.php](../../../Modules/Brief/Http/Requests/FetchBriefHistoryRequest.php)
- [ForceRefreshBriefRequest.php](../../../Modules/Brief/Http/Requests/ForceRefreshBriefRequest.php)
- [GenerateBriefRequest.php](../../../Modules/Brief/Http/Requests/GenerateBriefRequest.php)
- [InvalidateBriefRequest.php](../../../Modules/Brief/Http/Requests/InvalidateBriefRequest.php)
- [MarkBriefValidRequest.php](../../../Modules/Brief/Http/Requests/MarkBriefValidRequest.php)
- [PurgeBriefHistoryRequest.php](../../../Modules/Brief/Http/Requests/PurgeBriefHistoryRequest.php)

## Services — 4

- [BriefGeneratorService.php](../../../Modules/Brief/Services/BriefGeneratorService.php)
- [BriefValidator.php](../../../Modules/Brief/Services/BriefValidator.php)
- [LeaseBriefSectionService.php](../../../Modules/Brief/Services/LeaseBriefSectionService.php)
- [ValidationResult.php](../../../Modules/Brief/Services/ValidationResult.php)

## Console / Commands — 3

- [BriefHealthCommand.php](../../../Modules/Brief/Console/Commands/BriefHealthCommand.php)
- [GenerateBriefCommand.php](../../../Modules/Brief/Console/Commands/GenerateBriefCommand.php)
- [SkillTierReviewCommand.php](../../../Modules/Brief/Console/Commands/SkillTierReviewCommand.php)

## Providers — 1

- [BriefServiceProvider.php](../../../Modules/Brief/Providers/BriefServiceProvider.php)

## Rotas — 2

- [api.php](../../../Modules/Brief/Routes/api.php)
- [web.php](../../../Modules/Brief/Routes/web.php)

## Config — 1

- [retention.php](../../../Modules/Brief/Config/retention.php)

## Testes (Pest) — 11

- 11 arquivos em [Modules/Brief/Tests/Feature/](../../../Modules/Brief/Tests/Feature) — cobertura é do `casos-gate`/`screen-coverage`, não deste índice.

## Demais arquivos (manifestos, docs, assets e misc) — 4

- [CHANGELOG.md](../../../Modules/Brief/CHANGELOG.md)
- [BriefFetchTool.php](../../../Modules/Brief/Mcp/Tools/BriefFetchTool.php)
- [SCOPE.md](../../../Modules/Brief/SCOPE.md)
- [module.json](../../../Modules/Brief/module.json)
