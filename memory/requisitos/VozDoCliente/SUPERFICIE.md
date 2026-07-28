---
name: "SUPERFÍCIE — VozDoCliente"
description: "Índice GERADO dos artefatos do módulo VozDoCliente reconhecidos pelo classificador, agrupados por papel. NÃO editar à mão."
type: reference
authority: generated
lifecycle: ativo
module: VozDoCliente
---

# 🗺️ Superfície de código — VozDoCliente

> ⚙️ **Gerado por máquina** (`scripts/governance/module-surface.mjs`). NÃO edite à mão — a próxima geração sobrescreve.
> Regenerar: `node scripts/governance/module-surface.mjs VozDoCliente --write`. Validar frescor: `--check` (exit 1 se a árvore mudou e isto não foi regenerado).
>
> **O que isto é:** os artefatos reconhecidos pelo classificador dentro de `Modules/VozDoCliente/**` + `resources/js/Pages/VozDoCliente/**`, separados por papel — inclusive telas e seus componentes sem confundir um com o outro. **O que NÃO é:** manifesto de todo byte da pasta, cobertura/nota/status por tela (donos: `screen-coverage-map.mjs` + `casos-gate`) nem âncoras cross-cutting (bridge em `app/`, FSM) — essas vivem narradas no [BRIEFING](BRIEFING.md), não aqui.

**Total mapeado:** 12 arquivos em 9 papéis.

## Controllers — 3

- [DataController.php](../../../Modules/VozDoCliente/Http/Controllers/DataController.php)
- [InstallController.php](../../../Modules/VozDoCliente/Http/Controllers/InstallController.php)
- [SinalController.php](../../../Modules/VozDoCliente/Http/Controllers/SinalController.php)

## Requests (validação) — 1

- [StoreSinalRequest.php](../../../Modules/VozDoCliente/Http/Requests/StoreSinalRequest.php)

## Models / Entities — 1

- [Sinal.php](../../../Modules/VozDoCliente/Entities/Sinal.php)

## Providers — 2

- [RouteServiceProvider.php](../../../Modules/VozDoCliente/Providers/RouteServiceProvider.php)
- [VozDoClienteServiceProvider.php](../../../Modules/VozDoCliente/Providers/VozDoClienteServiceProvider.php)

## Rotas — 1

- [web.php](../../../Modules/VozDoCliente/Routes/web.php)

## Migrations (schema) — 1

- [2026_07_28_100000_create_voz_sinais_table.php](../../../Modules/VozDoCliente/Database/Migrations/2026_07_28_100000_create_voz_sinais_table.php)

## Config — 1

- [config.php](../../../Modules/VozDoCliente/Config/config.php)

## Views (Blade) — 1

- 1 arquivos em [Modules/VozDoCliente/Resources/views/](../../../Modules/VozDoCliente/Resources/views) — cobertura é do `casos-gate`/`screen-coverage`, não deste índice.

## Testes (Pest) — 1

- 1 arquivos em [Modules/VozDoCliente/Tests/Feature/](../../../Modules/VozDoCliente/Tests/Feature) — cobertura é do `casos-gate`/`screen-coverage`, não deste índice.
