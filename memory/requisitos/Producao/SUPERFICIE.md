---
name: "SUPERFÍCIE — Producao"
description: "Índice GERADO dos artefatos do módulo Producao reconhecidos pelo classificador, agrupados por papel. NÃO editar à mão."
type: reference
authority: generated
lifecycle: ativo
module: Producao
---

# 🗺️ Superfície de código — Producao

> ⚙️ **Gerado por máquina** (`scripts/governance/module-surface.mjs`). NÃO edite à mão — a próxima geração sobrescreve.
> Regenerar: `node scripts/governance/module-surface.mjs Producao --write`. Validar frescor: `--check` (exit 1 se a árvore mudou e isto não foi regenerado).
>
> **O que isto é:** o inventário completo das raízes `Modules/Producao/**` + `resources/js/Pages/Producao/**`, separado por papel — inclusive manifestos, documentação local, telas e componentes. **O que NÃO é:** cobertura/nota/status por tela (donos: `screen-coverage-map.mjs` + `casos-gate`), nem qual endpoint ainda entrega Blade em vez de Inertia (dono: `blade-migration-census.mjs` — este índice lista o arquivo, não a camada que a rota serve; a fila por módulo sai em `npm run migracao:report`), nem âncoras cross-cutting fora dessas raízes (bridge em `app/`, FSM) — essas são relações estruturadas do [SCOPE](SCOPE.md) e fatos do [BRIEFING](BRIEFING.md).

**Total mapeado:** 31 arquivos em 12 papéis.

## Controllers — 3

- [ApontamentoController.php](../../../Modules/Producao/Http/Controllers/ApontamentoController.php)
- [DataController.php](../../../Modules/Producao/Http/Controllers/DataController.php)
- [InstallController.php](../../../Modules/Producao/Http/Controllers/InstallController.php)

## Services — 4

- [ApontamentoOperadorService.php](../../../Modules/Producao/Services/ApontamentoOperadorService.php)
- [ApontamentoService.php](../../../Modules/Producao/Services/ApontamentoService.php)
- [CustoRealService.php](../../../Modules/Producao/Services/CustoRealService.php)
- [TurnoService.php](../../../Modules/Producao/Services/TurnoService.php)

## Models / Entities — 6

- [Apontamento.php](../../../Modules/Producao/Entities/Apontamento.php)
- [Evento.php](../../../Modules/Producao/Entities/Evento.php)
- [Operacao.php](../../../Modules/Producao/Entities/Operacao.php)
- [Posto.php](../../../Modules/Producao/Entities/Posto.php)
- [Programacao.php](../../../Modules/Producao/Entities/Programacao.php)
- [Turno.php](../../../Modules/Producao/Entities/Turno.php)

## Providers — 2

- [ProducaoServiceProvider.php](../../../Modules/Producao/Providers/ProducaoServiceProvider.php)
- [RouteServiceProvider.php](../../../Modules/Producao/Providers/RouteServiceProvider.php)

## Rotas — 1

- [web.php](../../../Modules/Producao/Routes/web.php)

## Migrations (schema) — 1

- [2026_10_15_000001_create_pcp_fundo_tables.php](../../../Modules/Producao/Database/Migrations/2026_10_15_000001_create_pcp_fundo_tables.php)

## Config — 1

- [config.php](../../../Modules/Producao/Config/config.php)

## Telas (Inertia/React) — 1

- [Index.tsx](../../../Modules/Producao/Resources/js/Pages/Producao/Apontamento/Index.tsx)

## Charters (lei da tela) — 1

- [Index.charter.md](../../../Modules/Producao/Resources/js/Pages/Producao/Apontamento/Index.charter.md)

## Casos (contrato UC) — 1

- [Index.casos.md](../../../Modules/Producao/Resources/js/Pages/Producao/Apontamento/Index.casos.md)

## Testes (Pest) — 4

- 4 em [Modules/Producao/Tests/Feature/](../../../Modules/Producao/Tests/Feature)
- _Cobertura destes arquivos é do `casos-gate`/`screen-coverage`, não deste índice._

## Demais arquivos (manifestos, docs, assets e misc) — 6

- [ApontamentoRecusado.php](../../../Modules/Producao/Exceptions/ApontamentoRecusado.php)
- [fila.ts](../../../Modules/Producao/Resources/js/Pages/Producao/Apontamento/_lib/fila.ts)
- [producao.php](../../../Modules/Producao/Resources/lang/pt/producao.php)
- [composer.json](../../../Modules/Producao/composer.json)
- [module.json](../../../Modules/Producao/module.json)
- [SCOPE.md](../../../memory/requisitos/Producao/SCOPE.md)
