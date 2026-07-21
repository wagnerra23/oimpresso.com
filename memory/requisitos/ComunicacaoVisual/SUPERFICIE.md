---
name: "SUPERFÍCIE — ComunicacaoVisual"
description: "Índice GERADO dos artefatos do módulo ComunicacaoVisual reconhecidos pelo classificador, agrupados por papel. NÃO editar à mão."
type: reference
authority: generated
lifecycle: ativo
module: ComunicacaoVisual
---

# 🗺️ Superfície de código — ComunicacaoVisual

> ⚙️ **Gerado por máquina** (`scripts/governance/module-surface.mjs`). NÃO edite à mão — a próxima geração sobrescreve.
> Regenerar: `node scripts/governance/module-surface.mjs ComunicacaoVisual --write`. Validar frescor: `--check` (exit 1 se a árvore mudou e isto não foi regenerado).
>
> **O que isto é:** os artefatos reconhecidos pelo classificador dentro de `Modules/ComunicacaoVisual/**` + `resources/js/Pages/ComunicacaoVisual/**`, separados por papel — inclusive telas e seus componentes sem confundir um com o outro. **O que NÃO é:** manifesto de todo byte da pasta, cobertura/nota/status por tela (donos: `screen-coverage-map.mjs` + `casos-gate`) nem âncoras cross-cutting (bridge em `app/`, FSM) — essas vivem narradas no [BRIEFING](BRIEFING.md), não aqui.

**Total mapeado:** 62 arquivos em 13 papéis.

## Controllers — 4

- [ApontamentoController.php](../../../Modules/ComunicacaoVisual/Http/Controllers/ApontamentoController.php)
- [DataController.php](../../../Modules/ComunicacaoVisual/Http/Controllers/DataController.php)
- [InstallController.php](../../../Modules/ComunicacaoVisual/Http/Controllers/InstallController.php)
- [OrcamentoController.php](../../../Modules/ComunicacaoVisual/Http/Controllers/OrcamentoController.php)

## Requests (validação) — 6

- [AgendarInstalacaoRequest.php](../../../Modules/ComunicacaoVisual/Http/Requests/AgendarInstalacaoRequest.php)
- [AprovarOrcamentoRequest.php](../../../Modules/ComunicacaoVisual/Http/Requests/AprovarOrcamentoRequest.php)
- [CalcularOrcamentoRequest.php](../../../Modules/ComunicacaoVisual/Http/Requests/CalcularOrcamentoRequest.php)
- [FinalizarApontamentoRequest.php](../../../Modules/ComunicacaoVisual/Http/Requests/FinalizarApontamentoRequest.php)
- [IniciarApontamentoRequest.php](../../../Modules/ComunicacaoVisual/Http/Requests/IniciarApontamentoRequest.php)
- [RecusarOrcamentoRequest.php](../../../Modules/ComunicacaoVisual/Http/Requests/RecusarOrcamentoRequest.php)

## Services — 2

- [ApontamentoTracker.php](../../../Modules/ComunicacaoVisual/Services/ApontamentoTracker.php)
- [OrcamentoCalculator.php](../../../Modules/ComunicacaoVisual/Services/OrcamentoCalculator.php)

## Models / Entities — 10

- [Acabamento.php](../../../Modules/ComunicacaoVisual/Entities/Acabamento.php)
- [Apontamento.php](../../../Modules/ComunicacaoVisual/Entities/Apontamento.php)
- [Instalacao.php](../../../Modules/ComunicacaoVisual/Entities/Instalacao.php)
- [InstalacaoCatalogo.php](../../../Modules/ComunicacaoVisual/Entities/InstalacaoCatalogo.php)
- [Material.php](../../../Modules/ComunicacaoVisual/Entities/Material.php)
- [Orcamento.php](../../../Modules/ComunicacaoVisual/Entities/Orcamento.php)
- [OrcamentoItem.php](../../../Modules/ComunicacaoVisual/Entities/OrcamentoItem.php)
- [OrdemProducao.php](../../../Modules/ComunicacaoVisual/Entities/OrdemProducao.php)
- [Os.php](../../../Modules/ComunicacaoVisual/Entities/Os.php)
- [Substrato.php](../../../Modules/ComunicacaoVisual/Entities/Substrato.php)

## Console / Commands — 2

- [ComvisHealthCommand.php](../../../Modules/ComunicacaoVisual/Console/Commands/ComvisHealthCommand.php)
- [DemoSeedCommand.php](../../../Modules/ComunicacaoVisual/Console/Commands/DemoSeedCommand.php)

## Providers — 2

- [ComunicacaoVisualServiceProvider.php](../../../Modules/ComunicacaoVisual/Providers/ComunicacaoVisualServiceProvider.php)
- [RouteServiceProvider.php](../../../Modules/ComunicacaoVisual/Providers/RouteServiceProvider.php)

## Rotas — 1

- [web.php](../../../Modules/ComunicacaoVisual/Routes/web.php)

## Migrations (schema) — 9

- [2026_05_10_000040_create_comvis_materiais_table.php](../../../Modules/ComunicacaoVisual/Database/Migrations/2026_05_10_000040_create_comvis_materiais_table.php)
- [2026_05_10_000041_create_comvis_orcamentos_table.php](../../../Modules/ComunicacaoVisual/Database/Migrations/2026_05_10_000041_create_comvis_orcamentos_table.php)
- [2026_05_10_000042_create_comvis_os_table.php](../../../Modules/ComunicacaoVisual/Database/Migrations/2026_05_10_000042_create_comvis_os_table.php)
- [2026_05_10_000043_create_comvis_apontamentos_table.php](../../../Modules/ComunicacaoVisual/Database/Migrations/2026_05_10_000043_create_comvis_apontamentos_table.php)
- [2026_05_12_000010_create_cv_substratos_table.php](../../../Modules/ComunicacaoVisual/Database/Migrations/2026_05_12_000010_create_cv_substratos_table.php)
- [2026_05_12_000011_create_cv_acabamentos_table.php](../../../Modules/ComunicacaoVisual/Database/Migrations/2026_05_12_000011_create_cv_acabamentos_table.php)
- [2026_05_12_000012_create_cv_instalacoes_catalogo_table.php](../../../Modules/ComunicacaoVisual/Database/Migrations/2026_05_12_000012_create_cv_instalacoes_catalogo_table.php)
- [2026_05_12_000013_create_cv_ordens_producao_table.php](../../../Modules/ComunicacaoVisual/Database/Migrations/2026_05_12_000013_create_cv_ordens_producao_table.php)
- [2026_05_12_000014_create_cv_instalacoes_table.php](../../../Modules/ComunicacaoVisual/Database/Migrations/2026_05_12_000014_create_cv_instalacoes_table.php)

## Seeders — 2

- [MaterialSeeder.php](../../../Modules/ComunicacaoVisual/Database/Seeders/MaterialSeeder.php)
- [RepairSettingsSeeder.php](../../../Modules/ComunicacaoVisual/Database/Seeders/RepairSettingsSeeder.php)

## Config — 2

- [config.php](../../../Modules/ComunicacaoVisual/Config/config.php)
- [retention.php](../../../Modules/ComunicacaoVisual/Config/retention.php)

## Telas (Inertia/React) — 1

- [Index.tsx](../../../resources/js/Pages/ComunicacaoVisual/Index.tsx)

## Charters (lei da tela) — 1

- [Index.charter.md](../../../resources/js/Pages/ComunicacaoVisual/Index.charter.md)

## Testes (Pest) — 20

- 20 arquivos em [Modules/ComunicacaoVisual/Tests/Feature/](../../../Modules/ComunicacaoVisual/Tests/Feature) — cobertura é do `casos-gate`/`screen-coverage`, não deste índice.
