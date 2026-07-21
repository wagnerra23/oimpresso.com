---
name: "SUPERFÍCIE — Vestuario"
description: "Índice GERADO dos artefatos do módulo Vestuario reconhecidos pelo classificador, agrupados por papel. NÃO editar à mão."
type: reference
authority: generated
lifecycle: ativo
module: Vestuario
---

# 🗺️ Superfície de código — Vestuario

> ⚙️ **Gerado por máquina** (`scripts/governance/module-surface.mjs`). NÃO edite à mão — a próxima geração sobrescreve.
> Regenerar: `node scripts/governance/module-surface.mjs Vestuario --write`. Validar frescor: `--check` (exit 1 se a árvore mudou e isto não foi regenerado).
>
> **O que isto é:** os artefatos reconhecidos pelo classificador dentro de `Modules/Vestuario/**` + `resources/js/Pages/Vestuario/**`, separados por papel — inclusive telas e seus componentes sem confundir um com o outro. **O que NÃO é:** manifesto de todo byte da pasta, cobertura/nota/status por tela (donos: `screen-coverage-map.mjs` + `casos-gate`) nem âncoras cross-cutting (bridge em `app/`, FSM) — essas vivem narradas no [BRIEFING](BRIEFING.md), não aqui.

**Total mapeado:** 37 arquivos em 14 papéis.

## Controllers — 3

- [DataController.php](../../../Modules/Vestuario/Http/Controllers/DataController.php)
- [EtiquetaTagController.php](../../../Modules/Vestuario/Http/Controllers/EtiquetaTagController.php)
- [InstallController.php](../../../Modules/Vestuario/Http/Controllers/InstallController.php)

## Requests (validação) — 2

- [StoreVestuarioRequest.php](../../../Modules/Vestuario/Http/Requests/StoreVestuarioRequest.php)
- [UpdateGradeRequest.php](../../../Modules/Vestuario/Http/Requests/UpdateGradeRequest.php)

## Services — 4

- [DevolucaoService.php](../../../Modules/Vestuario/Services/DevolucaoService.php)
- [EtiquetaTagService.php](../../../Modules/Vestuario/Services/EtiquetaTagService.php)
- [GradeCurvaService.php](../../../Modules/Vestuario/Services/GradeCurvaService.php)
- [VestuarioSettingsResolver.php](../../../Modules/Vestuario/Services/VestuarioSettingsResolver.php)

## Models / Entities — 1

- [VestuarioSetting.php](../../../Modules/Vestuario/Entities/VestuarioSetting.php)

## Console / Commands — 2

- [VestuarioHealthCommand.php](../../../Modules/Vestuario/Console/Commands/VestuarioHealthCommand.php)
- [VestuarioSettingsCommand.php](../../../Modules/Vestuario/Console/Commands/VestuarioSettingsCommand.php)

## Providers — 1

- [VestuarioServiceProvider.php](../../../Modules/Vestuario/Providers/VestuarioServiceProvider.php)

## Rotas — 1

- [web.php](../../../Modules/Vestuario/Routes/web.php)

## Migrations (schema) — 3

- [2026_05_10_000001_create_vestuario_settings_table.php](../../../Modules/Vestuario/Database/Migrations/2026_05_10_000001_create_vestuario_settings_table.php)
- [2026_05_17_000001_create_vestuario_devolucoes_table.php](../../../Modules/Vestuario/Database/Migrations/2026_05_17_000001_create_vestuario_devolucoes_table.php)
- [2026_05_17_000002_create_vestuario_creditos_cliente_table.php](../../../Modules/Vestuario/Database/Migrations/2026_05_17_000002_create_vestuario_creditos_cliente_table.php)

## Seeders — 1

- [RepairSettingsSeeder.php](../../../Modules/Vestuario/Database/Seeders/RepairSettingsSeeder.php)

## Config — 1

- [retention.php](../../../Modules/Vestuario/Config/retention.php)

## Views (Blade) — 1

- 1 arquivos em [Modules/Vestuario/Resources/views/etiquetas/](../../../Modules/Vestuario/Resources/views/etiquetas) — cobertura é do `casos-gate`/`screen-coverage`, não deste índice.

## Telas (Inertia/React) — 1

- [Index.tsx](../../../resources/js/Pages/Vestuario/Etiquetas/Index.tsx)

## Charters (lei da tela) — 1

- [Index.charter.md](../../../resources/js/Pages/Vestuario/Etiquetas/Index.charter.md)

## Testes (Pest) — 15

- 15 arquivos em [Modules/Vestuario/Tests/Feature/](../../../Modules/Vestuario/Tests/Feature) — cobertura é do `casos-gate`/`screen-coverage`, não deste índice.
