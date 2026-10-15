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

**Total mapeado:** 43 arquivos em 14 papéis.

## Controllers — 6

- [ColaboradorCustoController.php](../../../Modules/Producao/Http/Controllers/ColaboradorCustoController.php)
- [DataController.php](../../../Modules/Producao/Http/Controllers/DataController.php)
- [FerramentasController.php](../../../Modules/Producao/Http/Controllers/FerramentasController.php)
- [InstallController.php](../../../Modules/Producao/Http/Controllers/InstallController.php)
- [PostosController.php](../../../Modules/Producao/Http/Controllers/PostosController.php)
- [RoteirosController.php](../../../Modules/Producao/Http/Controllers/RoteirosController.php)

## Requests (validação) — 1

- [SalvarRoteiroRequest.php](../../../Modules/Producao/Http/Requests/SalvarRoteiroRequest.php)

## Services — 3

- [ApontamentoService.php](../../../Modules/Producao/Services/ApontamentoService.php)
- [CustoRealService.php](../../../Modules/Producao/Services/CustoRealService.php)
- [TurnoService.php](../../../Modules/Producao/Services/TurnoService.php)

## Models / Entities — 11

- [Apontamento.php](../../../Modules/Producao/Entities/Apontamento.php)
- [ColaboradorCusto.php](../../../Modules/Producao/Entities/ColaboradorCusto.php)
- [Evento.php](../../../Modules/Producao/Entities/Evento.php)
- [Ferramenta.php](../../../Modules/Producao/Entities/Ferramenta.php)
- [Motivo.php](../../../Modules/Producao/Entities/Motivo.php)
- [Operacao.php](../../../Modules/Producao/Entities/Operacao.php)
- [Posto.php](../../../Modules/Producao/Entities/Posto.php)
- [Programacao.php](../../../Modules/Producao/Entities/Programacao.php)
- [Roteiro.php](../../../Modules/Producao/Entities/Roteiro.php)
- [RoteiroEtapa.php](../../../Modules/Producao/Entities/RoteiroEtapa.php)
- [Turno.php](../../../Modules/Producao/Entities/Turno.php)

## Providers — 2

- [ProducaoServiceProvider.php](../../../Modules/Producao/Providers/ProducaoServiceProvider.php)
- [RouteServiceProvider.php](../../../Modules/Producao/Providers/RouteServiceProvider.php)

## Rotas — 1

- [web.php](../../../Modules/Producao/Routes/web.php)

## Migrations (schema) — 2

- [2026_10_15_000001_create_pcp_fundo_tables.php](../../../Modules/Producao/Database/Migrations/2026_10_15_000001_create_pcp_fundo_tables.php)
- [2026_10_16_000001_create_pcp_cadastros_tables.php](../../../Modules/Producao/Database/Migrations/2026_10_16_000001_create_pcp_cadastros_tables.php)

## Config — 1

- [config.php](../../../Modules/Producao/Config/config.php)

## Telas (Inertia/React) — 2

- [Index.tsx](../../../Modules/Producao/Resources/js/Pages/Producao/Postos/Index.tsx)
- [Index.tsx](../../../Modules/Producao/Resources/js/Pages/Producao/Roteiros/Index.tsx)

## Componentes / apoio de tela — 3

- [EditorPosto.tsx](../../../Modules/Producao/Resources/js/Pages/Producao/Postos/_components/EditorPosto.tsx)
- [EditorRoteiro.tsx](../../../Modules/Producao/Resources/js/Pages/Producao/Roteiros/_components/EditorRoteiro.tsx)
- [ProducaoAbas.tsx](../../../Modules/Producao/Resources/js/Pages/Producao/_components/ProducaoAbas.tsx)

## Charters (lei da tela) — 2

- [Index.charter.md](../../../Modules/Producao/Resources/js/Pages/Producao/Postos/Index.charter.md)
- [Index.charter.md](../../../Modules/Producao/Resources/js/Pages/Producao/Roteiros/Index.charter.md)

## Casos (contrato UC) — 2

- [Index.casos.md](../../../Modules/Producao/Resources/js/Pages/Producao/Postos/Index.casos.md)
- [Index.casos.md](../../../Modules/Producao/Resources/js/Pages/Producao/Roteiros/Index.casos.md)

## Testes (Pest) — 3

- 3 em [Modules/Producao/Tests/Feature/](../../../Modules/Producao/Tests/Feature)
- _Cobertura destes arquivos é do `casos-gate`/`screen-coverage`, não deste índice._

## Demais arquivos (manifestos, docs, assets e misc) — 4

- [producao.php](../../../Modules/Producao/Resources/lang/pt/producao.php)
- [composer.json](../../../Modules/Producao/composer.json)
- [module.json](../../../Modules/Producao/module.json)
- [SCOPE.md](../../../memory/requisitos/Producao/SCOPE.md)
