---
name: "SUPERFÍCIE — Compras"
description: "Índice GERADO dos artefatos do módulo Compras reconhecidos pelo classificador, agrupados por papel. NÃO editar à mão."
type: reference
authority: generated
lifecycle: ativo
module: Compras
---

# 🗺️ Superfície de código — Compras

> ⚙️ **Gerado por máquina** (`scripts/governance/module-surface.mjs`). NÃO edite à mão — a próxima geração sobrescreve.
> Regenerar: `node scripts/governance/module-surface.mjs Compras --write`. Validar frescor: `--check` (exit 1 se a árvore mudou e isto não foi regenerado).
>
> **O que isto é:** o inventário completo das raízes `Modules/Compras/**` + `resources/js/Pages/Compras/**`, separado por papel — inclusive manifestos, documentação local, telas e componentes. **O que NÃO é:** cobertura/nota/status por tela (donos: `screen-coverage-map.mjs` + `casos-gate`), nem qual endpoint ainda entrega Blade em vez de Inertia (dono: `blade-migration-census.mjs` — este índice lista o arquivo, não a camada que a rota serve; a fila por módulo sai em `npm run migracao:report`), nem âncoras cross-cutting fora dessas raízes (bridge em `app/`, FSM) — essas são relações estruturadas do [SCOPE](SCOPE.md) e fatos do [BRIEFING](BRIEFING.md).

**Total mapeado:** 53 arquivos em 18 papéis.

## Controllers — 5

- [ComprasController.php](../../../Modules/Compras/Http/Controllers/ComprasController.php)
- [DataController.php](../../../Modules/Compras/Http/Controllers/DataController.php)
- [InstallController.php](../../../Modules/Compras/Http/Controllers/InstallController.php)
- [InsumoMetodoCustoController.php](../../../Modules/Compras/Http/Controllers/InsumoMetodoCustoController.php)
- [PropostasPrecoController.php](../../../Modules/Compras/Http/Controllers/PropostasPrecoController.php)

## Requests (validação) — 1

- [ListarComprasRequest.php](../../../Modules/Compras/Http/Requests/ListarComprasRequest.php)

## Services — 6

- [AplicarCustoDoRecebimento.php](../../../Modules/Compras/Services/AplicarCustoDoRecebimento.php)
- [ComprasService.php](../../../Modules/Compras/Services/ComprasService.php)
- [CustoDoInsumoService.php](../../../Modules/Compras/Services/CustoDoInsumoService.php)
- [MetodoCustoDoProduto.php](../../../Modules/Compras/Services/MetodoCustoDoProduto.php)
- [PropostasDePreco.php](../../../Modules/Compras/Services/PropostasDePreco.php)
- [RequisicaoEstoqueMinimoService.php](../../../Modules/Compras/Services/RequisicaoEstoqueMinimoService.php)

## Models / Entities — 1

- [InsumoMetodoCusto.php](../../../Modules/Compras/Entities/InsumoMetodoCusto.php)

## Observers — 1

- [TransactionSellLineCustoObserver.php](../../../Modules/Compras/Observers/TransactionSellLineCustoObserver.php)

## Jobs — 1

- [RequisicaoEstoqueMinimoJob.php](../../../Modules/Compras/Jobs/RequisicaoEstoqueMinimoJob.php)

## Console / Commands — 1

- [RequisicaoEstoqueMinimoCommand.php](../../../Modules/Compras/Console/Commands/RequisicaoEstoqueMinimoCommand.php)

## Providers — 2

- [ComprasServiceProvider.php](../../../Modules/Compras/Providers/ComprasServiceProvider.php)
- [RouteServiceProvider.php](../../../Modules/Compras/Providers/RouteServiceProvider.php)

## Rotas — 1

- [web.php](../../../Modules/Compras/Routes/web.php)

## Migrations (schema) — 3

- [2026_10_20_000001_create_compras_insumo_metodo_custo_table.php](../../../Modules/Compras/Database/Migrations/2026_10_20_000001_create_compras_insumo_metodo_custo_table.php)
- [2026_10_20_120000_create_compras_propostas_preco_table.php](../../../Modules/Compras/Database/Migrations/2026_10_20_120000_create_compras_propostas_preco_table.php)
- [2026_10_27_000001_create_compras_produto_fornecedor_table.php](../../../Modules/Compras/Database/Migrations/2026_10_27_000001_create_compras_produto_fornecedor_table.php)

## Config — 1

- [config.php](../../../Modules/Compras/Config/config.php)

## Views (Blade) — 1

- [metodo_custo_produto.blade.php](../../../Modules/Compras/Resources/views/partials/metodo_custo_produto.blade.php)

## Telas (Inertia/React) — 1

- [Index.tsx](../../../resources/js/Pages/Compras/Index.tsx)

## Componentes / apoio de tela — 3

- [AcoesDropdown.tsx](../../../resources/js/Pages/Compras/components/AcoesDropdown.tsx)
- [Drawer.tsx](../../../resources/js/Pages/Compras/components/Drawer.tsx)
- [VisibilidadeColunas.tsx](../../../resources/js/Pages/Compras/components/VisibilidadeColunas.tsx)

## Charters (lei da tela) — 1

- [Index.charter.md](../../../resources/js/Pages/Compras/Index.charter.md)

## Casos (contrato UC) — 1

- [Index.casos.md](../../../resources/js/Pages/Compras/Index.casos.md)

## Testes (Pest) — 20

- 20 em [Modules/Compras/Tests/Feature/](../../../Modules/Compras/Tests/Feature)
- _Cobertura destes arquivos é do `casos-gate`/`screen-coverage`, não deste índice._

## Demais arquivos (manifestos, docs, assets e misc) — 3

- [composer.json](../../../Modules/Compras/composer.json)
- [module.json](../../../Modules/Compras/module.json)
- [SCOPE.md](../../../memory/requisitos/Compras/SCOPE.md)
