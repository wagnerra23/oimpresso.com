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
> **O que isto é:** o inventário completo das raízes `Modules/VozDoCliente/**` + `resources/js/Pages/VozDoCliente/**`, separado por papel — inclusive manifestos, documentação local, telas e componentes. **O que NÃO é:** cobertura/nota/status por tela (donos: `screen-coverage-map.mjs` + `casos-gate`), nem qual endpoint ainda entrega Blade em vez de Inertia (dono: `blade-migration-census.mjs` — este índice lista o arquivo, não a camada que a rota serve; a fila por módulo sai em `npm run migracao:report`), nem âncoras cross-cutting fora dessas raízes (bridge em `app/`, FSM) — essas são relações estruturadas do [SCOPE](SCOPE.md) e fatos do [BRIEFING](BRIEFING.md).

**Total mapeado:** 18 arquivos em 11 papéis.

## Controllers — 3

- [DataController.php](../../../Modules/VozDoCliente/Http/Controllers/DataController.php)
- [InstallController.php](../../../Modules/VozDoCliente/Http/Controllers/InstallController.php)
- [SinalController.php](../../../Modules/VozDoCliente/Http/Controllers/SinalController.php)

## Requests (validação) — 1

- [StoreSinalRequest.php](../../../Modules/VozDoCliente/Http/Requests/StoreSinalRequest.php)

## Models / Entities — 1

- [Sinal.php](../../../Modules/VozDoCliente/Entities/Sinal.php)

## Console / Commands — 1

- [HabilitarVozDoClienteCommand.php](../../../Modules/VozDoCliente/Console/Commands/HabilitarVozDoClienteCommand.php)

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

- [caixa.blade.php](../../../Modules/VozDoCliente/Resources/views/caixa.blade.php)

## Testes (Pest) — 1

- 1 em [Modules/VozDoCliente/Tests/Feature/](../../../Modules/VozDoCliente/Tests/Feature)
- _Cobertura destes arquivos é do `casos-gate`/`screen-coverage`, não deste índice._

## Demais arquivos (manifestos, docs, assets e misc) — 5

- [vozdocliente.php](../../../Modules/VozDoCliente/Resources/lang/en/vozdocliente.php)
- [vozdocliente.php](../../../Modules/VozDoCliente/Resources/lang/pt/vozdocliente.php)
- [composer.json](../../../Modules/VozDoCliente/composer.json)
- [module.json](../../../Modules/VozDoCliente/module.json)
- [SCOPE.md](../../../memory/requisitos/VozDoCliente/SCOPE.md)
