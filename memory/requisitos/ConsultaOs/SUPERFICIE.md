---
name: "SUPERFÍCIE — ConsultaOs"
description: "Índice GERADO dos artefatos do módulo ConsultaOs reconhecidos pelo classificador, agrupados por papel. NÃO editar à mão."
type: reference
authority: generated
lifecycle: ativo
module: ConsultaOs
---

# 🗺️ Superfície de código — ConsultaOs

> ⚙️ **Gerado por máquina** (`scripts/governance/module-surface.mjs`). NÃO edite à mão — a próxima geração sobrescreve.
> Regenerar: `node scripts/governance/module-surface.mjs ConsultaOs --write`. Validar frescor: `--check` (exit 1 se a árvore mudou e isto não foi regenerado).
>
> **O que isto é:** os artefatos reconhecidos pelo classificador dentro de `Modules/ConsultaOs/**` + `resources/js/Pages/ConsultaOs/**`, separados por papel — inclusive telas e seus componentes sem confundir um com o outro. **O que NÃO é:** manifesto de todo byte da pasta, cobertura/nota/status por tela (donos: `screen-coverage-map.mjs` + `casos-gate`) nem âncoras cross-cutting (bridge em `app/`, FSM) — essas vivem narradas no [BRIEFING](BRIEFING.md), não aqui.

**Total mapeado:** 32 arquivos em 12 papéis.

## Controllers — 3

- [ConsultaOsController.php](../../../Modules/ConsultaOs/Http/Controllers/ConsultaOsController.php)
- [DataController.php](../../../Modules/ConsultaOs/Http/Controllers/DataController.php)
- [InstallController.php](../../../Modules/ConsultaOs/Http/Controllers/InstallController.php)

## Requests (validação) — 3

- [ConsultaPorEstagioRequest.php](../../../Modules/ConsultaOs/Http/Requests/ConsultaPorEstagioRequest.php)
- [ConsultaPublicaRequest.php](../../../Modules/ConsultaOs/Http/Requests/ConsultaPublicaRequest.php)
- [FeedbackPublicoRequest.php](../../../Modules/ConsultaOs/Http/Requests/FeedbackPublicoRequest.php)

## Services — 1

- [ConsultaOsMockService.php](../../../Modules/ConsultaOs/Services/ConsultaOsMockService.php)

## Console / Commands — 1

- [ConsultaOsHealthCommand.php](../../../Modules/ConsultaOs/Console/Commands/ConsultaOsHealthCommand.php)

## Providers — 2

- [ConsultaOsServiceProvider.php](../../../Modules/ConsultaOs/Providers/ConsultaOsServiceProvider.php)
- [RouteServiceProvider.php](../../../Modules/ConsultaOs/Providers/RouteServiceProvider.php)

## Rotas — 1

- [web.php](../../../Modules/ConsultaOs/Routes/web.php)

## Config — 2

- [config.php](../../../Modules/ConsultaOs/Config/config.php)
- [retention.php](../../../Modules/ConsultaOs/Config/retention.php)

## Telas (Inertia/React) — 1

- [Index.tsx](../../../resources/js/Pages/ConsultaOs/Index.tsx)

## Componentes / apoio de tela — 4

- [OsLookupForm.tsx](../../../resources/js/Pages/ConsultaOs/_components/OsLookupForm.tsx)
- [OsPipeline.tsx](../../../resources/js/Pages/ConsultaOs/_components/OsPipeline.tsx)
- [OsResultCard.tsx](../../../resources/js/Pages/ConsultaOs/_components/OsResultCard.tsx)
- [OsStageBadge.tsx](../../../resources/js/Pages/ConsultaOs/_components/OsStageBadge.tsx)

## Charters (lei da tela) — 1

- [Index.charter.md](../../../resources/js/Pages/ConsultaOs/Index.charter.md)

## Testes (Pest) — 11

- 11 arquivos em [Modules/ConsultaOs/Tests/Feature/](../../../Modules/ConsultaOs/Tests/Feature) — cobertura é do `casos-gate`/`screen-coverage`, não deste índice.

## Outros (raiz/misc) — 2

- [ConsultaOsRepositoryInterface.php](../../../Modules/ConsultaOs/Contracts/ConsultaOsRepositoryInterface.php)
- [MockConsultaOsRepository.php](../../../Modules/ConsultaOs/Repositories/MockConsultaOsRepository.php)
