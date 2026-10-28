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

**Total mapeado:** 166 arquivos em 17 papéis.

## Controllers — 22

- [AlocacaoController.php](../../../Modules/Producao/Http/Controllers/AlocacaoController.php)
- [AppApontamentoController.php](../../../Modules/Producao/Http/Controllers/Api/AppApontamentoController.php)
- [ApontamentoController.php](../../../Modules/Producao/Http/Controllers/ApontamentoController.php)
- [AprovacaoArteController.php](../../../Modules/Producao/Http/Controllers/AprovacaoArteController.php)
- [AproveitamentoMidiaController.php](../../../Modules/Producao/Http/Controllers/AproveitamentoMidiaController.php)
- [ColaboradorCustoController.php](../../../Modules/Producao/Http/Controllers/ColaboradorCustoController.php)
- [DataController.php](../../../Modules/Producao/Http/Controllers/DataController.php)
- [EtiquetaOsController.php](../../../Modules/Producao/Http/Controllers/EtiquetaOsController.php)
- [FerramentasController.php](../../../Modules/Producao/Http/Controllers/FerramentasController.php)
- [InstallController.php](../../../Modules/Producao/Http/Controllers/InstallController.php)
- [LinhaController.php](../../../Modules/Producao/Http/Controllers/LinhaController.php)
- [LoteRastreioController.php](../../../Modules/Producao/Http/Controllers/LoteRastreioController.php)
- [OrdemCustoController.php](../../../Modules/Producao/Http/Controllers/OrdemCustoController.php)
- [OrdemMaterialController.php](../../../Modules/Producao/Http/Controllers/OrdemMaterialController.php)
- [PainelController.php](../../../Modules/Producao/Http/Controllers/PainelController.php)
- [PostoOperadoresController.php](../../../Modules/Producao/Http/Controllers/PostoOperadoresController.php)
- [PostosController.php](../../../Modules/Producao/Http/Controllers/PostosController.php)
- [PrazoDaVendaController.php](../../../Modules/Producao/Http/Controllers/PrazoDaVendaController.php)
- [PrevistoRealizadoController.php](../../../Modules/Producao/Http/Controllers/PrevistoRealizadoController.php)
- [QuadroController.php](../../../Modules/Producao/Http/Controllers/QuadroController.php)
- [RomaneioController.php](../../../Modules/Producao/Http/Controllers/RomaneioController.php)
- [RoteirosController.php](../../../Modules/Producao/Http/Controllers/RoteirosController.php)

## Requests (validação) — 1

- [SalvarRoteiroRequest.php](../../../Modules/Producao/Http/Requests/SalvarRoteiroRequest.php)

## Services — 36

- [AlocacaoDaEtapa.php](../../../Modules/Producao/Services/AlocacaoDaEtapa.php)
- [ApontamentoOperadorService.php](../../../Modules/Producao/Services/ApontamentoOperadorService.php)
- [ApontamentoService.php](../../../Modules/Producao/Services/ApontamentoService.php)
- [AprovacaoArteService.php](../../../Modules/Producao/Services/AprovacaoArteService.php)
- [AproveitamentoDeMidia.php](../../../Modules/Producao/Services/AproveitamentoDeMidia.php)
- [ArteDasOrdens.php](../../../Modules/Producao/Services/ArteDasOrdens.php)
- [CalendarioDeProducao.php](../../../Modules/Producao/Services/CalendarioDeProducao.php)
- [ColunaDoQuadro.php](../../../Modules/Producao/Services/ColunaDoQuadro.php)
- [ConsumoMaterialService.php](../../../Modules/Producao/Services/ConsumoMaterialService.php)
- [CriarOsDaVenda.php](../../../Modules/Producao/Services/CriarOsDaVenda.php)
- [CustoHoraDoPostoService.php](../../../Modules/Producao/Services/CustoHoraDoPostoService.php)
- [CustoRealDaOsService.php](../../../Modules/Producao/Services/CustoRealDaOsService.php)
- [CustoRealService.php](../../../Modules/Producao/Services/CustoRealService.php)
- [EnvioDaProva.php](../../../Modules/Producao/Services/EnvioDaProva.php)
- [FaltaDeMaterialDaOs.php](../../../Modules/Producao/Services/FaltaDeMaterialDaOs.php)
- [HorasDosPostosService.php](../../../Modules/Producao/Services/HorasDosPostosService.php)
- [LinhaDaProducao.php](../../../Modules/Producao/Services/LinhaDaProducao.php)
- [MateriaisDaEtapa.php](../../../Modules/Producao/Services/MateriaisDaEtapa.php)
- [OperadoresDoPosto.php](../../../Modules/Producao/Services/OperadoresDoPosto.php)
- [OsDaVenda.php](../../../Modules/Producao/Services/OsDaVenda.php)
- [PainelDaProducao.php](../../../Modules/Producao/Services/PainelDaProducao.php)
- [PrazoPelaFila.php](../../../Modules/Producao/Services/PrazoPelaFila.php)
- [PrevisaoCustoDaOrdem.php](../../../Modules/Producao/Services/PrevisaoCustoDaOrdem.php)
- [PrevistoRealizadoDaOrdem.php](../../../Modules/Producao/Services/PrevistoRealizadoDaOrdem.php)
- [ProcessoDaOrdem.php](../../../Modules/Producao/Services/ProcessoDaOrdem.php)
- [ProgramacaoDoRoteiro.php](../../../Modules/Producao/Services/ProgramacaoDoRoteiro.php)
- [PrometerPrazoDaVenda.php](../../../Modules/Producao/Services/PrometerPrazoDaVenda.php)
- [QuadroDaProducao.php](../../../Modules/Producao/Services/QuadroDaProducao.php)
- [RastreioDoRolo.php](../../../Modules/Producao/Services/RastreioDoRolo.php)
- [RefugoService.php](../../../Modules/Producao/Services/RefugoService.php)
- [ReservaDeInsumoDaOs.php](../../../Modules/Producao/Services/ReservaDeInsumoDaOs.php)
- [RomaneioDeImpressao.php](../../../Modules/Producao/Services/RomaneioDeImpressao.php)
- [RoteiroDoProdutoService.php](../../../Modules/Producao/Services/RoteiroDoProdutoService.php)
- [SinalDaOrdem.php](../../../Modules/Producao/Services/SinalDaOrdem.php)
- [SituacaoDaOrdem.php](../../../Modules/Producao/Services/SituacaoDaOrdem.php)
- [TurnoService.php](../../../Modules/Producao/Services/TurnoService.php)

## Models / Entities — 18

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
- [PostoOperador.php](../../../Modules/Producao/Entities/PostoOperador.php)
- [Programacao.php](../../../Modules/Producao/Entities/Programacao.php)
- [Refugo.php](../../../Modules/Producao/Entities/Refugo.php)
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

## Migrations (schema) — 14

- [2026_10_15_000001_create_pcp_fundo_tables.php](../../../Modules/Producao/Database/Migrations/2026_10_15_000001_create_pcp_fundo_tables.php)
- [2026_10_16_000001_create_pcp_cadastros_tables.php](../../../Modules/Producao/Database/Migrations/2026_10_16_000001_create_pcp_cadastros_tables.php)
- [2026_10_17_000001_create_pcp_ordens_tables.php](../../../Modules/Producao/Database/Migrations/2026_10_17_000001_create_pcp_ordens_tables.php)
- [2026_10_19_210000_create_pcp_consumos_table.php](../../../Modules/Producao/Database/Migrations/2026_10_19_210000_create_pcp_consumos_table.php)
- [2026_10_20_000001_create_pcp_aprovacoes_arte_table.php](../../../Modules/Producao/Database/Migrations/2026_10_20_000001_create_pcp_aprovacoes_arte_table.php)
- [2026_10_20_000001_create_pcp_romaneios_tables.php](../../../Modules/Producao/Database/Migrations/2026_10_20_000001_create_pcp_romaneios_tables.php)
- [2026_10_20_120000_add_pcp_ordem_id_to_stock_reservations.php](../../../Modules/Producao/Database/Migrations/2026_10_20_120000_add_pcp_ordem_id_to_stock_reservations.php)
- [2026_10_20_120000_add_prazo_prometido_to_pcp_ordens.php](../../../Modules/Producao/Database/Migrations/2026_10_20_120000_add_prazo_prometido_to_pcp_ordens.php)
- [2026_10_20_130000_create_pcp_posto_operadores_table.php](../../../Modules/Producao/Database/Migrations/2026_10_20_130000_create_pcp_posto_operadores_table.php)
- [2026_10_20_160000_create_pcp_refugos_table.php](../../../Modules/Producao/Database/Migrations/2026_10_20_160000_create_pcp_refugos_table.php)
- [2026_10_20_200000_create_pcp_aproveitamento_midia_tables.php](../../../Modules/Producao/Database/Migrations/2026_10_20_200000_create_pcp_aproveitamento_midia_tables.php)
- [2026_10_21_100000_add_impressao_to_pcp_apontamentos.php](../../../Modules/Producao/Database/Migrations/2026_10_21_100000_add_impressao_to_pcp_apontamentos.php)
- [2026_10_21_200000_add_custo_hora_direto_to_pcp_postos.php](../../../Modules/Producao/Database/Migrations/2026_10_21_200000_add_custo_hora_direto_to_pcp_postos.php)
- [2026_10_22_000001_create_pcp_quadro_colunas_table.php](../../../Modules/Producao/Database/Migrations/2026_10_22_000001_create_pcp_quadro_colunas_table.php)

## Config — 1

- [config.php](../../../Modules/Producao/Config/config.php)

## Views (Blade) — 2

- [aprovacao-arte.blade.php](../../../Modules/Producao/Resources/views/aprovacao-arte.blade.php)
- [etiqueta-os.blade.php](../../../Modules/Producao/Resources/views/etiqueta-os.blade.php)

## Telas (Inertia/React) — 6

- [Index.tsx](../../../Modules/Producao/Resources/js/Pages/Producao/Apontamento/Index.tsx)
- [Index.tsx](../../../Modules/Producao/Resources/js/Pages/Producao/Linha/Index.tsx)
- [Index.tsx](../../../Modules/Producao/Resources/js/Pages/Producao/Painel/Index.tsx)
- [Index.tsx](../../../Modules/Producao/Resources/js/Pages/Producao/Postos/Index.tsx)
- [Index.tsx](../../../Modules/Producao/Resources/js/Pages/Producao/Quadro/Index.tsx)
- [Index.tsx](../../../Modules/Producao/Resources/js/Pages/Producao/Roteiros/Index.tsx)

## Componentes / apoio de tela — 7

- [AcaoDaProva.tsx](../../../Modules/Producao/Resources/js/Pages/Producao/Painel/_components/AcaoDaProva.tsx)
- [EditorPosto.tsx](../../../Modules/Producao/Resources/js/Pages/Producao/Postos/_components/EditorPosto.tsx)
- [Alocar.tsx](../../../Modules/Producao/Resources/js/Pages/Producao/Quadro/_components/Alocar.tsx)
- [SimuladorBobina.tsx](../../../Modules/Producao/Resources/js/Pages/Producao/Quadro/_components/SimuladorBobina.tsx)
- [EditorRoteiro.tsx](../../../Modules/Producao/Resources/js/Pages/Producao/Roteiros/_components/EditorRoteiro.tsx)
- [ProdutosDoRoteiro.tsx](../../../Modules/Producao/Resources/js/Pages/Producao/Roteiros/_components/ProdutosDoRoteiro.tsx)
- [ProducaoAbas.tsx](../../../Modules/Producao/Resources/js/Pages/Producao/_components/ProducaoAbas.tsx)

## Charters (lei da tela) — 6

- [Index.charter.md](../../../Modules/Producao/Resources/js/Pages/Producao/Apontamento/Index.charter.md)
- [Index.charter.md](../../../Modules/Producao/Resources/js/Pages/Producao/Linha/Index.charter.md)
- [Index.charter.md](../../../Modules/Producao/Resources/js/Pages/Producao/Painel/Index.charter.md)
- [Index.charter.md](../../../Modules/Producao/Resources/js/Pages/Producao/Postos/Index.charter.md)
- [Index.charter.md](../../../Modules/Producao/Resources/js/Pages/Producao/Quadro/Index.charter.md)
- [Index.charter.md](../../../Modules/Producao/Resources/js/Pages/Producao/Roteiros/Index.charter.md)

## Casos (contrato UC) — 6

- [Index.casos.md](../../../Modules/Producao/Resources/js/Pages/Producao/Apontamento/Index.casos.md)
- [Index.casos.md](../../../Modules/Producao/Resources/js/Pages/Producao/Linha/Index.casos.md)
- [Index.casos.md](../../../Modules/Producao/Resources/js/Pages/Producao/Painel/Index.casos.md)
- [Index.casos.md](../../../Modules/Producao/Resources/js/Pages/Producao/Postos/Index.casos.md)
- [Index.casos.md](../../../Modules/Producao/Resources/js/Pages/Producao/Quadro/Index.casos.md)
- [Index.casos.md](../../../Modules/Producao/Resources/js/Pages/Producao/Roteiros/Index.casos.md)

## Testes (Pest) — 32

- 32 em [Modules/Producao/Tests/Feature/](../../../Modules/Producao/Tests/Feature)
- _Cobertura destes arquivos é do `casos-gate`/`screen-coverage`, não deste índice._

## Demais arquivos (manifestos, docs, assets e misc) — 10

- [ApontamentoRecusado.php](../../../Modules/Producao/Exceptions/ApontamentoRecusado.php)
- [fila.ts](../../../Modules/Producao/Resources/js/Pages/Producao/Apontamento/_lib/fila.ts)
- [quantidade.ts](../../../Modules/Producao/Resources/js/Pages/Producao/Apontamento/_lib/quantidade.ts)
- [json.ts](../../../Modules/Producao/Resources/js/Pages/Producao/Quadro/_lib/json.ts)
- [tipos.ts](../../../Modules/Producao/Resources/js/Pages/Producao/Quadro/_lib/tipos.ts)
- [filtro.ts](../../../Modules/Producao/Resources/js/Pages/Producao/_lib/filtro.ts)
- [producao.php](../../../Modules/Producao/Resources/lang/pt/producao.php)
- [composer.json](../../../Modules/Producao/composer.json)
- [module.json](../../../Modules/Producao/module.json)
- [SCOPE.md](../../../memory/requisitos/Producao/SCOPE.md)
