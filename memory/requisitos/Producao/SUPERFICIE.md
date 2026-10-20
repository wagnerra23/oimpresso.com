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

**Total mapeado:** 93 arquivos em 17 papéis.

## Controllers — 12

- [AppApontamentoController.php](../../../Modules/Producao/Http/Controllers/Api/AppApontamentoController.php)
- [ApontamentoController.php](../../../Modules/Producao/Http/Controllers/ApontamentoController.php)
- [AprovacaoArteController.php](../../../Modules/Producao/Http/Controllers/AprovacaoArteController.php)
- [ColaboradorCustoController.php](../../../Modules/Producao/Http/Controllers/ColaboradorCustoController.php)
- [DataController.php](../../../Modules/Producao/Http/Controllers/DataController.php)
- [EtiquetaOsController.php](../../../Modules/Producao/Http/Controllers/EtiquetaOsController.php)
- [FerramentasController.php](../../../Modules/Producao/Http/Controllers/FerramentasController.php)
- [InstallController.php](../../../Modules/Producao/Http/Controllers/InstallController.php)
- [OrdemCustoController.php](../../../Modules/Producao/Http/Controllers/OrdemCustoController.php)
- [PostosController.php](../../../Modules/Producao/Http/Controllers/PostosController.php)
- [RomaneioController.php](../../../Modules/Producao/Http/Controllers/RomaneioController.php)
- [RoteirosController.php](../../../Modules/Producao/Http/Controllers/RoteirosController.php)

## Requests (validação) — 1

- [SalvarRoteiroRequest.php](../../../Modules/Producao/Http/Requests/SalvarRoteiroRequest.php)

## Services — 14

- [ApontamentoOperadorService.php](../../../Modules/Producao/Services/ApontamentoOperadorService.php)
- [ApontamentoService.php](../../../Modules/Producao/Services/ApontamentoService.php)
- [AprovacaoArteService.php](../../../Modules/Producao/Services/AprovacaoArteService.php)
- [ConsumoMaterialService.php](../../../Modules/Producao/Services/ConsumoMaterialService.php)
- [CriarOsDaVenda.php](../../../Modules/Producao/Services/CriarOsDaVenda.php)
- [CustoHoraDoPostoService.php](../../../Modules/Producao/Services/CustoHoraDoPostoService.php)
- [CustoRealService.php](../../../Modules/Producao/Services/CustoRealService.php)
- [OsDaVenda.php](../../../Modules/Producao/Services/OsDaVenda.php)
- [PrevisaoCustoDaOrdem.php](../../../Modules/Producao/Services/PrevisaoCustoDaOrdem.php)
- [ProcessoDaOrdem.php](../../../Modules/Producao/Services/ProcessoDaOrdem.php)
- [RomaneioDeImpressao.php](../../../Modules/Producao/Services/RomaneioDeImpressao.php)
- [SinalDaOrdem.php](../../../Modules/Producao/Services/SinalDaOrdem.php)
- [SituacaoDaOrdem.php](../../../Modules/Producao/Services/SituacaoDaOrdem.php)
- [TurnoService.php](../../../Modules/Producao/Services/TurnoService.php)

## Models / Entities — 16

- [Apontamento.php](../../../Modules/Producao/Entities/Apontamento.php)
- [AprovacaoArte.php](../../../Modules/Producao/Entities/AprovacaoArte.php)
- [ColaboradorCusto.php](../../../Modules/Producao/Entities/ColaboradorCusto.php)
- [Consumo.php](../../../Modules/Producao/Entities/Consumo.php)
- [Evento.php](../../../Modules/Producao/Entities/Evento.php)
- [Ferramenta.php](../../../Modules/Producao/Entities/Ferramenta.php)
- [Motivo.php](../../../Modules/Producao/Entities/Motivo.php)
- [Operacao.php](../../../Modules/Producao/Entities/Operacao.php)
- [Ordem.php](../../../Modules/Producao/Entities/Ordem.php)
- [Posto.php](../../../Modules/Producao/Entities/Posto.php)
- [Programacao.php](../../../Modules/Producao/Entities/Programacao.php)
- [Romaneio.php](../../../Modules/Producao/Entities/Romaneio.php)
- [RomaneioLinha.php](../../../Modules/Producao/Entities/RomaneioLinha.php)
- [Roteiro.php](../../../Modules/Producao/Entities/Roteiro.php)
- [RoteiroEtapa.php](../../../Modules/Producao/Entities/RoteiroEtapa.php)
- [Turno.php](../../../Modules/Producao/Entities/Turno.php)

## Jobs — 1

- [CriarOsDaVendaJob.php](../../../Modules/Producao/Jobs/CriarOsDaVendaJob.php)

## Console / Commands — 1

- [FecharTurnoCommand.php](../../../Modules/Producao/Console/FecharTurnoCommand.php)

## Providers — 2

- [ProducaoServiceProvider.php](../../../Modules/Producao/Providers/ProducaoServiceProvider.php)
- [RouteServiceProvider.php](../../../Modules/Producao/Providers/RouteServiceProvider.php)

## Rotas — 1

- [web.php](../../../Modules/Producao/Routes/web.php)

## Migrations (schema) — 6

- [2026_10_15_000001_create_pcp_fundo_tables.php](../../../Modules/Producao/Database/Migrations/2026_10_15_000001_create_pcp_fundo_tables.php)
- [2026_10_16_000001_create_pcp_cadastros_tables.php](../../../Modules/Producao/Database/Migrations/2026_10_16_000001_create_pcp_cadastros_tables.php)
- [2026_10_17_000001_create_pcp_ordens_tables.php](../../../Modules/Producao/Database/Migrations/2026_10_17_000001_create_pcp_ordens_tables.php)
- [2026_10_19_210000_create_pcp_consumos_table.php](../../../Modules/Producao/Database/Migrations/2026_10_19_210000_create_pcp_consumos_table.php)
- [2026_10_20_000001_create_pcp_aprovacoes_arte_table.php](../../../Modules/Producao/Database/Migrations/2026_10_20_000001_create_pcp_aprovacoes_arte_table.php)
- [2026_10_20_000001_create_pcp_romaneios_tables.php](../../../Modules/Producao/Database/Migrations/2026_10_20_000001_create_pcp_romaneios_tables.php)

## Config — 1

- [config.php](../../../Modules/Producao/Config/config.php)

## Views (Blade) — 2

- [aprovacao-arte.blade.php](../../../Modules/Producao/Resources/views/aprovacao-arte.blade.php)
- [etiqueta-os.blade.php](../../../Modules/Producao/Resources/views/etiqueta-os.blade.php)

## Telas (Inertia/React) — 3

- [Index.tsx](../../../Modules/Producao/Resources/js/Pages/Producao/Apontamento/Index.tsx)
- [Index.tsx](../../../Modules/Producao/Resources/js/Pages/Producao/Postos/Index.tsx)
- [Index.tsx](../../../Modules/Producao/Resources/js/Pages/Producao/Roteiros/Index.tsx)

## Componentes / apoio de tela — 3

- [EditorPosto.tsx](../../../Modules/Producao/Resources/js/Pages/Producao/Postos/_components/EditorPosto.tsx)
- [EditorRoteiro.tsx](../../../Modules/Producao/Resources/js/Pages/Producao/Roteiros/_components/EditorRoteiro.tsx)
- [ProducaoAbas.tsx](../../../Modules/Producao/Resources/js/Pages/Producao/_components/ProducaoAbas.tsx)

## Charters (lei da tela) — 3

- [Index.charter.md](../../../Modules/Producao/Resources/js/Pages/Producao/Apontamento/Index.charter.md)
- [Index.charter.md](../../../Modules/Producao/Resources/js/Pages/Producao/Postos/Index.charter.md)
- [Index.charter.md](../../../Modules/Producao/Resources/js/Pages/Producao/Roteiros/Index.charter.md)

## Casos (contrato UC) — 3

- [Index.casos.md](../../../Modules/Producao/Resources/js/Pages/Producao/Apontamento/Index.casos.md)
- [Index.casos.md](../../../Modules/Producao/Resources/js/Pages/Producao/Postos/Index.casos.md)
- [Index.casos.md](../../../Modules/Producao/Resources/js/Pages/Producao/Roteiros/Index.casos.md)

## Testes (Pest) — 18

- 18 em [Modules/Producao/Tests/Feature/](../../../Modules/Producao/Tests/Feature)
- _Cobertura destes arquivos é do `casos-gate`/`screen-coverage`, não deste índice._

## Demais arquivos (manifestos, docs, assets e misc) — 6

- [ApontamentoRecusado.php](../../../Modules/Producao/Exceptions/ApontamentoRecusado.php)
- [fila.ts](../../../Modules/Producao/Resources/js/Pages/Producao/Apontamento/_lib/fila.ts)
- [producao.php](../../../Modules/Producao/Resources/lang/pt/producao.php)
- [composer.json](../../../Modules/Producao/composer.json)
- [module.json](../../../Modules/Producao/module.json)
- [SCOPE.md](../../../memory/requisitos/Producao/SCOPE.md)
