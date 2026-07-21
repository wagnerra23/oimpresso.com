---
name: "SUPERFÍCIE — Ponto"
description: "Índice GERADO dos artefatos do módulo Ponto reconhecidos pelo classificador, agrupados por papel. NÃO editar à mão."
type: reference
authority: generated
lifecycle: ativo
module: Ponto
---

# 🗺️ Superfície de código — Ponto

> ⚙️ **Gerado por máquina** (`scripts/governance/module-surface.mjs`). NÃO edite à mão — a próxima geração sobrescreve.
> Regenerar: `node scripts/governance/module-surface.mjs Ponto --write`. Validar frescor: `--check` (exit 1 se a árvore mudou e isto não foi regenerado).
>
> **O que isto é:** os artefatos reconhecidos pelo classificador dentro de `Modules/Ponto/**` + `resources/js/Pages/Ponto/**`, separados por papel — inclusive telas e seus componentes sem confundir um com o outro. **O que NÃO é:** manifesto de todo byte da pasta, cobertura/nota/status por tela (donos: `screen-coverage-map.mjs` + `casos-gate`) nem âncoras cross-cutting (bridge em `app/`, FSM) — essas vivem narradas no [BRIEFING](BRIEFING.md), não aqui.

**Total mapeado:** 167 arquivos em 17 papéis.

## Controllers — 13

- [MobileMarcacaoController.php](../../../Modules/Ponto/Http/Controllers/Api/MobileMarcacaoController.php)
- [AprovacaoController.php](../../../Modules/Ponto/Http/Controllers/AprovacaoController.php)
- [BancoHorasController.php](../../../Modules/Ponto/Http/Controllers/BancoHorasController.php)
- [ColaboradorController.php](../../../Modules/Ponto/Http/Controllers/ColaboradorController.php)
- [ConfiguracaoController.php](../../../Modules/Ponto/Http/Controllers/ConfiguracaoController.php)
- [DashboardController.php](../../../Modules/Ponto/Http/Controllers/DashboardController.php)
- [DataController.php](../../../Modules/Ponto/Http/Controllers/DataController.php)
- [EscalaController.php](../../../Modules/Ponto/Http/Controllers/EscalaController.php)
- [EspelhoController.php](../../../Modules/Ponto/Http/Controllers/EspelhoController.php)
- [ImportacaoController.php](../../../Modules/Ponto/Http/Controllers/ImportacaoController.php)
- [InstallController.php](../../../Modules/Ponto/Http/Controllers/InstallController.php)
- [IntercorrenciaController.php](../../../Modules/Ponto/Http/Controllers/IntercorrenciaController.php)
- [RelatorioController.php](../../../Modules/Ponto/Http/Controllers/RelatorioController.php)

## Requests (validação) — 7

- [AnularMarcacaoRequest.php](../../../Modules/Ponto/Http/Requests/AnularMarcacaoRequest.php)
- [ImportacaoAfdRequest.php](../../../Modules/Ponto/Http/Requests/ImportacaoAfdRequest.php)
- [IntercorrenciaRequest.php](../../../Modules/Ponto/Http/Requests/IntercorrenciaRequest.php)
- [StoreBancoHorasMovimentoRequest.php](../../../Modules/Ponto/Http/Requests/StoreBancoHorasMovimentoRequest.php)
- [StoreEscalaRequest.php](../../../Modules/Ponto/Http/Requests/StoreEscalaRequest.php)
- [StoreIntercorrenciaRequest.php](../../../Modules/Ponto/Http/Requests/StoreIntercorrenciaRequest.php)
- [StoreMarcacaoRequest.php](../../../Modules/Ponto/Http/Requests/StoreMarcacaoRequest.php)

## Middleware — 1

- [CheckPontoAccess.php](../../../Modules/Ponto/Http/Middleware/CheckPontoAccess.php)

## Services — 10

- [AfdParserService.php](../../../Modules/Ponto/Services/AfdParserService.php)
- [ApuracaoService.php](../../../Modules/Ponto/Services/ApuracaoService.php)
- [BancoHorasService.php](../../../Modules/Ponto/Services/BancoHorasService.php)
- [IntercorrenciaAIClassifier.php](../../../Modules/Ponto/Services/IntercorrenciaAIClassifier.php)
- [IntercorrenciaService.php](../../../Modules/Ponto/Services/IntercorrenciaService.php)
- [MarcacaoService.php](../../../Modules/Ponto/Services/MarcacaoService.php)
- [MobileMarcacaoService.php](../../../Modules/Ponto/Services/MobileMarcacaoService.php)
- [NsrService.php](../../../Modules/Ponto/Services/NsrService.php)
- [PisNaoCadastradoException.php](../../../Modules/Ponto/Services/PisNaoCadastradoException.php)
- [ReportService.php](../../../Modules/Ponto/Services/ReportService.php)

## Models / Entities — 10

- [ApuracaoDia.php](../../../Modules/Ponto/Entities/ApuracaoDia.php)
- [BancoHorasMovimento.php](../../../Modules/Ponto/Entities/BancoHorasMovimento.php)
- [BancoHorasSaldo.php](../../../Modules/Ponto/Entities/BancoHorasSaldo.php)
- [Colaborador.php](../../../Modules/Ponto/Entities/Colaborador.php)
- [Escala.php](../../../Modules/Ponto/Entities/Escala.php)
- [EscalaTurno.php](../../../Modules/Ponto/Entities/EscalaTurno.php)
- [Importacao.php](../../../Modules/Ponto/Entities/Importacao.php)
- [Intercorrencia.php](../../../Modules/Ponto/Entities/Intercorrencia.php)
- [Marcacao.php](../../../Modules/Ponto/Entities/Marcacao.php)
- [Rep.php](../../../Modules/Ponto/Entities/Rep.php)

## Jobs — 2

- [ProcessarImportacaoAfdJob.php](../../../Modules/Ponto/Jobs/ProcessarImportacaoAfdJob.php)
- [ReapurarDiaJob.php](../../../Modules/Ponto/Jobs/ReapurarDiaJob.php)

## Console / Commands — 3

- [AfdInspecionarCommand.php](../../../Modules/Ponto/Console/Commands/AfdInspecionarCommand.php)
- [ImportAfdCommand.php](../../../Modules/Ponto/Console/Commands/ImportAfdCommand.php)
- [PontoHealthCommand.php](../../../Modules/Ponto/Console/Commands/PontoHealthCommand.php)

## Providers — 1

- [PontoServiceProvider.php](../../../Modules/Ponto/Providers/PontoServiceProvider.php)

## Migrations (schema) — 8

- [2026_04_18_000001_create_ponto_colaborador_config_table.php](../../../Modules/Ponto/Database/Migrations/2026_04_18_000001_create_ponto_colaborador_config_table.php)
- [2026_04_18_000002_create_ponto_reps_table.php](../../../Modules/Ponto/Database/Migrations/2026_04_18_000002_create_ponto_reps_table.php)
- [2026_04_18_000003_create_ponto_escalas_table.php](../../../Modules/Ponto/Database/Migrations/2026_04_18_000003_create_ponto_escalas_table.php)
- [2026_04_18_000004_create_ponto_marcacoes_table.php](../../../Modules/Ponto/Database/Migrations/2026_04_18_000004_create_ponto_marcacoes_table.php)
- [2026_04_18_000005_create_ponto_intercorrencias_table.php](../../../Modules/Ponto/Database/Migrations/2026_04_18_000005_create_ponto_intercorrencias_table.php)
- [2026_04_18_000006_create_ponto_apuracao_dia_table.php](../../../Modules/Ponto/Database/Migrations/2026_04_18_000006_create_ponto_apuracao_dia_table.php)
- [2026_04_18_000007_create_ponto_banco_horas_table.php](../../../Modules/Ponto/Database/Migrations/2026_04_18_000007_create_ponto_banco_horas_table.php)
- [2026_04_18_000008_create_ponto_importacoes_table.php](../../../Modules/Ponto/Database/Migrations/2026_04_18_000008_create_ponto_importacoes_table.php)

## Seeders — 2

- [DevPontoSeeder.php](../../../Modules/Ponto/Database/Seeders/DevPontoSeeder.php)
- [PontoWr2DatabaseSeeder.php](../../../Modules/Ponto/Database/Seeders/PontoWr2DatabaseSeeder.php)

## Config — 2

- [config.php](../../../Modules/Ponto/Config/config.php)
- [retention.php](../../../Modules/Ponto/Config/retention.php)

## Views (Blade) — 26

- 26 arquivos em [Modules/Ponto/Resources/views/aprovacoes/](../../../Modules/Ponto/Resources/views/aprovacoes) — cobertura é do `casos-gate`/`screen-coverage`, não deste índice.

## Telas (Inertia/React) — 20

- [Index.tsx](../../../resources/js/Pages/Ponto/Aprovacoes/Index.tsx)
- [Index.tsx](../../../resources/js/Pages/Ponto/BancoHoras/Index.tsx)
- [Show.tsx](../../../resources/js/Pages/Ponto/BancoHoras/Show.tsx)
- [Edit.tsx](../../../resources/js/Pages/Ponto/Colaboradores/Edit.tsx)
- [Index.tsx](../../../resources/js/Pages/Ponto/Colaboradores/Index.tsx)
- [Index.tsx](../../../resources/js/Pages/Ponto/Configuracoes/Index.tsx)
- [Reps.tsx](../../../resources/js/Pages/Ponto/Configuracoes/Reps.tsx)
- [Index.tsx](../../../resources/js/Pages/Ponto/Dashboard/Index.tsx)
- [Form.tsx](../../../resources/js/Pages/Ponto/Escalas/Form.tsx)
- [Index.tsx](../../../resources/js/Pages/Ponto/Escalas/Index.tsx)
- [Index.tsx](../../../resources/js/Pages/Ponto/Espelho/Index.tsx)
- [Show.tsx](../../../resources/js/Pages/Ponto/Espelho/Show.tsx)
- [Create.tsx](../../../resources/js/Pages/Ponto/Importacoes/Create.tsx)
- [Index.tsx](../../../resources/js/Pages/Ponto/Importacoes/Index.tsx)
- [Show.tsx](../../../resources/js/Pages/Ponto/Importacoes/Show.tsx)
- [Create.tsx](../../../resources/js/Pages/Ponto/Intercorrencias/Create.tsx)
- [Index.tsx](../../../resources/js/Pages/Ponto/Intercorrencias/Index.tsx)
- [Show.tsx](../../../resources/js/Pages/Ponto/Intercorrencias/Show.tsx)
- [Index.tsx](../../../resources/js/Pages/Ponto/Relatorios/Index.tsx)
- [Welcome.tsx](../../../resources/js/Pages/Ponto/Welcome.tsx)

## Componentes / apoio de tela — 6

- [ActivityFeed.tsx](../../../resources/js/Pages/Ponto/_components/ActivityFeed.tsx)
- [AlertInbox.tsx](../../../resources/js/Pages/Ponto/_components/AlertInbox.tsx)
- [MonthHeatmap.tsx](../../../resources/js/Pages/Ponto/_components/MonthHeatmap.tsx)
- [PresenceStrip.tsx](../../../resources/js/Pages/Ponto/_components/PresenceStrip.tsx)
- [PontoPrimaryButton.tsx](../../../resources/js/Pages/Ponto/_shared/PontoPrimaryButton.tsx)
- [PontoSubNav.tsx](../../../resources/js/Pages/Ponto/_shared/PontoSubNav.tsx)

## Charters (lei da tela) — 20

- [Index.charter.md](../../../resources/js/Pages/Ponto/Aprovacoes/Index.charter.md)
- [Index.charter.md](../../../resources/js/Pages/Ponto/BancoHoras/Index.charter.md)
- [Show.charter.md](../../../resources/js/Pages/Ponto/BancoHoras/Show.charter.md)
- [Edit.charter.md](../../../resources/js/Pages/Ponto/Colaboradores/Edit.charter.md)
- [Index.charter.md](../../../resources/js/Pages/Ponto/Colaboradores/Index.charter.md)
- [Index.charter.md](../../../resources/js/Pages/Ponto/Configuracoes/Index.charter.md)
- [Reps.charter.md](../../../resources/js/Pages/Ponto/Configuracoes/Reps.charter.md)
- [Index.charter.md](../../../resources/js/Pages/Ponto/Dashboard/Index.charter.md)
- [Form.charter.md](../../../resources/js/Pages/Ponto/Escalas/Form.charter.md)
- [Index.charter.md](../../../resources/js/Pages/Ponto/Escalas/Index.charter.md)
- [Index.charter.md](../../../resources/js/Pages/Ponto/Espelho/Index.charter.md)
- [Show.charter.md](../../../resources/js/Pages/Ponto/Espelho/Show.charter.md)
- [Create.charter.md](../../../resources/js/Pages/Ponto/Importacoes/Create.charter.md)
- [Index.charter.md](../../../resources/js/Pages/Ponto/Importacoes/Index.charter.md)
- [Show.charter.md](../../../resources/js/Pages/Ponto/Importacoes/Show.charter.md)
- [Create.charter.md](../../../resources/js/Pages/Ponto/Intercorrencias/Create.charter.md)
- [Index.charter.md](../../../resources/js/Pages/Ponto/Intercorrencias/Index.charter.md)
- [Show.charter.md](../../../resources/js/Pages/Ponto/Intercorrencias/Show.charter.md)
- [Index.charter.md](../../../resources/js/Pages/Ponto/Relatorios/Index.charter.md)
- [Welcome.charter.md](../../../resources/js/Pages/Ponto/Welcome.charter.md)

## Testes (Pest) — 29

- 29 arquivos em [Modules/Ponto/Tests/Feature/](../../../Modules/Ponto/Tests/Feature) — cobertura é do `casos-gate`/`screen-coverage`, não deste índice.

## Outros (raiz/misc) — 7

- [ColaboradorFactory.php](../../../Modules/Ponto/Database/factories/ColaboradorFactory.php)
- [EscalaFactory.php](../../../Modules/Ponto/Database/factories/EscalaFactory.php)
- [EscalaTurnoFactory.php](../../../Modules/Ponto/Database/factories/EscalaTurnoFactory.php)
- [IntercorrenciaFactory.php](../../../Modules/Ponto/Database/factories/IntercorrenciaFactory.php)
- [MarcacaoFactory.php](../../../Modules/Ponto/Database/factories/MarcacaoFactory.php)
- [routes.php](../../../Modules/Ponto/Http/routes.php)
- [start.php](../../../Modules/Ponto/start.php)
