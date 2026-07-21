---
name: "SUPERFÍCIE — Financeiro"
description: "Índice GERADO dos artefatos do módulo Financeiro reconhecidos pelo classificador, agrupados por papel. NÃO editar à mão."
type: reference
authority: generated
lifecycle: ativo
module: Financeiro
---

# 🗺️ Superfície de código — Financeiro

> ⚙️ **Gerado por máquina** (`scripts/governance/module-surface.mjs`). NÃO edite à mão — a próxima geração sobrescreve.
> Regenerar: `node scripts/governance/module-surface.mjs Financeiro --write`. Validar frescor: `--check` (exit 1 se a árvore mudou e isto não foi regenerado).
>
> **O que isto é:** os artefatos reconhecidos pelo classificador dentro de `Modules/Financeiro/**` + `resources/js/Pages/Financeiro/**`, separados por papel — inclusive telas e seus componentes sem confundir um com o outro. **O que NÃO é:** manifesto de todo byte da pasta, cobertura/nota/status por tela (donos: `screen-coverage-map.mjs` + `casos-gate`) nem âncoras cross-cutting (bridge em `app/`, FSM) — essas vivem narradas no [BRIEFING](BRIEFING.md), não aqui.

**Total mapeado:** 288 arquivos em 21 papéis.

## Controllers — 25

- [AdvisorAuthController.php](../../../Modules/Financeiro/Http/Controllers/Advisor/AdvisorAuthController.php)
- [AdvisorPortalController.php](../../../Modules/Financeiro/Http/Controllers/Advisor/AdvisorPortalController.php)
- [AdvisorAccessController.php](../../../Modules/Financeiro/Http/Controllers/AdvisorAccessController.php)
- [AssinaturaController.php](../../../Modules/Financeiro/Http/Controllers/AssinaturaController.php)
- [BoletoController.php](../../../Modules/Financeiro/Http/Controllers/BoletoController.php)
- [CaixaController.php](../../../Modules/Financeiro/Http/Controllers/CaixaController.php)
- [CategoriaController.php](../../../Modules/Financeiro/Http/Controllers/CategoriaController.php)
- [CobrancaController.php](../../../Modules/Financeiro/Http/Controllers/CobrancaController.php)
- [ConciliacaoController.php](../../../Modules/Financeiro/Http/Controllers/ConciliacaoController.php)
- [ContaBancariaController.php](../../../Modules/Financeiro/Http/Controllers/ContaBancariaController.php)
- [ContaPagarController.php](../../../Modules/Financeiro/Http/Controllers/ContaPagarController.php)
- [ContaReceberController.php](../../../Modules/Financeiro/Http/Controllers/ContaReceberController.php)
- [CoworkSidebarController.php](../../../Modules/Financeiro/Http/Controllers/CoworkSidebarController.php)
- [DashboardController.php](../../../Modules/Financeiro/Http/Controllers/DashboardController.php)
- [DataController.php](../../../Modules/Financeiro/Http/Controllers/DataController.php)
- [DreController.php](../../../Modules/Financeiro/Http/Controllers/DreController.php)
- [ExtratoController.php](../../../Modules/Financeiro/Http/Controllers/ExtratoController.php)
- [FinanceiroController.php](../../../Modules/Financeiro/Http/Controllers/FinanceiroController.php)
- [FluxoController.php](../../../Modules/Financeiro/Http/Controllers/FluxoController.php)
- [ImpostosController.php](../../../Modules/Financeiro/Http/Controllers/ImpostosController.php)
- [InstallController.php](../../../Modules/Financeiro/Http/Controllers/InstallController.php)
- [PlanoContaController.php](../../../Modules/Financeiro/Http/Controllers/PlanoContaController.php)
- [ProvaVivaController.php](../../../Modules/Financeiro/Http/Controllers/ProvaVivaController.php)
- [RelatoriosController.php](../../../Modules/Financeiro/Http/Controllers/RelatoriosController.php)
- [UnificadoController.php](../../../Modules/Financeiro/Http/Controllers/UnificadoController.php)

## Requests (validação) — 10

- [FluxoFiltroRequest.php](../../../Modules/Financeiro/Http/Requests/FluxoFiltroRequest.php)
- [StoreAccountRequest.php](../../../Modules/Financeiro/Http/Requests/StoreAccountRequest.php)
- [StoreBaixaRequest.php](../../../Modules/Financeiro/Http/Requests/StoreBaixaRequest.php)
- [StoreTituloRequest.php](../../../Modules/Financeiro/Http/Requests/StoreTituloRequest.php)
- [StoreTransactionRequest.php](../../../Modules/Financeiro/Http/Requests/StoreTransactionRequest.php)
- [UpdateAccountRequest.php](../../../Modules/Financeiro/Http/Requests/UpdateAccountRequest.php)
- [UpdateTituloRequest.php](../../../Modules/Financeiro/Http/Requests/UpdateTituloRequest.php)
- [UpdateTransactionRequest.php](../../../Modules/Financeiro/Http/Requests/UpdateTransactionRequest.php)
- [UpsertCategoriaRequest.php](../../../Modules/Financeiro/Http/Requests/UpsertCategoriaRequest.php)
- [UpsertContaBancariaRequest.php](../../../Modules/Financeiro/Http/Requests/UpsertContaBancariaRequest.php)

## Middleware — 1

- [AdvisorViewScope.php](../../../Modules/Financeiro/Http/Middleware/AdvisorViewScope.php)

## Services — 12

- [BoletoOcrService.php](../../../Modules/Financeiro/Services/BoletoOcrService.php)
- [DreService.php](../../../Modules/Financeiro/Services/DreService.php)
- [FinanceiroAuditLogger.php](../../../Modules/Financeiro/Services/FinanceiroAuditLogger.php)
- [FluxoCaixaService.php](../../../Modules/Financeiro/Services/FluxoCaixaService.php)
- [FluxoRealizadoService.php](../../../Modules/Financeiro/Services/FluxoRealizadoService.php)
- [AsaasPixAutomaticoService.php](../../../Modules/Financeiro/Services/Integrations/AsaasPixAutomaticoService.php)
- [PluggyBankSyncService.php](../../../Modules/Financeiro/Services/Integrations/PluggyBankSyncService.php)
- [PluggyClient.php](../../../Modules/Financeiro/Services/Integrations/PluggyClient.php)
- [LinhaDigitavelValidator.php](../../../Modules/Financeiro/Services/LinhaDigitavelValidator.php)
- [TituloAutoService.php](../../../Modules/Financeiro/Services/TituloAutoService.php)
- [TituloService.php](../../../Modules/Financeiro/Services/TituloService.php)
- [UnificadoService.php](../../../Modules/Financeiro/Services/UnificadoService.php)

## Strategies — 1

- [CnabDirectStrategy.php](../../../Modules/Financeiro/Strategies/CnabDirectStrategy.php)

## Models / Entities — 17

- [AccountsLegacyMap.php](../../../Modules/Financeiro/Models/AccountsLegacyMap.php)
- [Advisor.php](../../../Modules/Financeiro/Models/Advisor.php)
- [AdvisorBusinessAccess.php](../../../Modules/Financeiro/Models/AdvisorBusinessAccess.php)
- [AiUsageLog.php](../../../Modules/Financeiro/Models/AiUsageLog.php)
- [BankStatementLine.php](../../../Modules/Financeiro/Models/BankStatementLine.php)
- [BoletoRemessa.php](../../../Modules/Financeiro/Models/BoletoRemessa.php)
- [CaixaMovimento.php](../../../Modules/Financeiro/Models/CaixaMovimento.php)
- [Categoria.php](../../../Modules/Financeiro/Models/Categoria.php)
- [BusinessScope.php](../../../Modules/Financeiro/Models/Concerns/BusinessScope.php)
- [BusinessScopeImpl.php](../../../Modules/Financeiro/Models/Concerns/BusinessScopeImpl.php)
- [ContaBancaria.php](../../../Modules/Financeiro/Models/ContaBancaria.php)
- [ExtratoLancamento.php](../../../Modules/Financeiro/Models/ExtratoLancamento.php)
- [PlanoConta.php](../../../Modules/Financeiro/Models/PlanoConta.php)
- [Titulo.php](../../../Modules/Financeiro/Models/Titulo.php)
- [TituloAnexo.php](../../../Modules/Financeiro/Models/TituloAnexo.php)
- [TituloBaixa.php](../../../Modules/Financeiro/Models/TituloBaixa.php)
- [TituloComment.php](../../../Modules/Financeiro/Models/TituloComment.php)

## Observers — 3

- [CashRegisterObserver.php](../../../Modules/Financeiro/Observers/CashRegisterObserver.php)
- [TransactionObserver.php](../../../Modules/Financeiro/Observers/TransactionObserver.php)
- [TransactionPaymentObserver.php](../../../Modules/Financeiro/Observers/TransactionPaymentObserver.php)

## Jobs — 1

- [CriarTituloDeVendaJob.php](../../../Modules/Financeiro/Jobs/CriarTituloDeVendaJob.php)

## Events / Listeners — 6

- [CashRegisterClosed.php](../../../Modules/Financeiro/Events/CashRegisterClosed.php)
- [TituloCriado.php](../../../Modules/Financeiro/Events/TituloCriado.php)
- [OnCashRegisterClosedCreateFinanceiroTitulo.php](../../../Modules/Financeiro/Listeners/OnCashRegisterClosedCreateFinanceiroTitulo.php)
- [OnCobrancaPagaCreateFinanceiroTitulo.php](../../../Modules/Financeiro/Listeners/OnCobrancaPagaCreateFinanceiroTitulo.php)
- [OnTituloCriadoLog.php](../../../Modules/Financeiro/Listeners/OnTituloCriadoLog.php)
- [ProcessAsaasPixWebhookListener.php](../../../Modules/Financeiro/Listeners/ProcessAsaasPixWebhookListener.php)

## Console / Commands — 7

- [BackfillExtratoOfxCommand.php](../../../Modules/Financeiro/Console/Commands/BackfillExtratoOfxCommand.php)
- [BackfillPlanoContaCommand.php](../../../Modules/Financeiro/Console/Commands/BackfillPlanoContaCommand.php)
- [BridgeExpenseToTitulosCommand.php](../../../Modules/Financeiro/Console/Commands/BridgeExpenseToTitulosCommand.php)
- [FinanceiroHealthCommand.php](../../../Modules/Financeiro/Console/Commands/FinanceiroHealthCommand.php)
- [InstallCommand.php](../../../Modules/Financeiro/Console/Commands/InstallCommand.php)
- [ProvisionSmokeTenantCommand.php](../../../Modules/Financeiro/Console/Commands/ProvisionSmokeTenantCommand.php)
- [ResyncFromCoreCommand.php](../../../Modules/Financeiro/Console/Commands/ResyncFromCoreCommand.php)

## Providers — 2

- [FinanceiroServiceProvider.php](../../../Modules/Financeiro/Providers/FinanceiroServiceProvider.php)
- [RouteServiceProvider.php](../../../Modules/Financeiro/Providers/RouteServiceProvider.php)

## Rotas — 2

- [api.php](../../../Modules/Financeiro/Routes/api.php)
- [web.php](../../../Modules/Financeiro/Routes/web.php)

## Migrations (schema) — 27

- [2026_04_24_140001_create_fin_planos_conta_table.php](../../../Modules/Financeiro/Database/Migrations/2026_04_24_140001_create_fin_planos_conta_table.php)
- [2026_04_24_140002_create_fin_categorias_table.php](../../../Modules/Financeiro/Database/Migrations/2026_04_24_140002_create_fin_categorias_table.php)
- [2026_04_24_140003_create_fin_contas_bancarias_table.php](../../../Modules/Financeiro/Database/Migrations/2026_04_24_140003_create_fin_contas_bancarias_table.php)
- [2026_04_24_140004_create_fin_titulos_table.php](../../../Modules/Financeiro/Database/Migrations/2026_04_24_140004_create_fin_titulos_table.php)
- [2026_04_24_140005_create_fin_titulo_baixas_table.php](../../../Modules/Financeiro/Database/Migrations/2026_04_24_140005_create_fin_titulo_baixas_table.php)
- [2026_04_24_140006_create_fin_caixa_movimentos_table.php](../../../Modules/Financeiro/Database/Migrations/2026_04_24_140006_create_fin_caixa_movimentos_table.php)
- [2026_04_25_140101_create_fin_boleto_remessas_table.php](../../../Modules/Financeiro/Database/Migrations/2026_04_25_140101_create_fin_boleto_remessas_table.php)
- [2026_05_06_000001_add_rb_gateway_credential_to_fin_contas_bancarias.php](../../../Modules/Financeiro/Database/Migrations/2026_05_06_000001_add_rb_gateway_credential_to_fin_contas_bancarias.php)
- [2026_05_06_000002_add_saldo_cached_to_fin_contas_bancarias.php](../../../Modules/Financeiro/Database/Migrations/2026_05_06_000002_add_saldo_cached_to_fin_contas_bancarias.php)
- [2026_05_07_220000_create_fin_extrato_lancamentos_table.php](../../../Modules/Financeiro/Database/Migrations/2026_05_07_220000_create_fin_extrato_lancamentos_table.php)
- [2026_05_09_210000_create_accounts_legacy_map_table.php](../../../Modules/Financeiro/Database/Migrations/2026_05_09_210000_create_accounts_legacy_map_table.php)
- [2026_05_09_210001_add_legacy_columns_to_fin_contas_bancarias.php](../../../Modules/Financeiro/Database/Migrations/2026_05_09_210001_add_legacy_columns_to_fin_contas_bancarias.php)
- [2026_05_18_180000_add_conferido_to_fin_titulos.php](../../../Modules/Financeiro/Database/Migrations/2026_05_18_180000_add_conferido_to_fin_titulos.php)
- [2026_05_18_190000_create_fin_titulo_comments_table.php](../../../Modules/Financeiro/Database/Migrations/2026_05_18_190000_create_fin_titulo_comments_table.php)
- [2026_05_19_220000_create_fin_bank_statement_lines_table.php](../../../Modules/Financeiro/Database/Migrations/2026_05_19_220000_create_fin_bank_statement_lines_table.php)
- [2026_05_19_220001_create_fin_titulo_anexos_table.php](../../../Modules/Financeiro/Database/Migrations/2026_05_19_220001_create_fin_titulo_anexos_table.php)
- [2026_05_19_220002_add_aprovacao_to_fin_titulos.php](../../../Modules/Financeiro/Database/Migrations/2026_05_19_220002_add_aprovacao_to_fin_titulos.php)
- [2026_05_20_140000_create_advisors_table.php](../../../Modules/Financeiro/Database/Migrations/2026_05_20_140000_create_advisors_table.php)
- [2026_05_20_140001_create_advisor_business_access_table.php](../../../Modules/Financeiro/Database/Migrations/2026_05_20_140001_create_advisor_business_access_table.php)
- [2026_05_20_180000_create_ai_usage_log_table.php](../../../Modules/Financeiro/Database/Migrations/2026_05_20_180000_create_ai_usage_log_table.php)
- [2026_05_20_200000_make_titulo_baixa_conta_bancaria_optional.php](../../../Modules/Financeiro/Database/Migrations/2026_05_20_200000_make_titulo_baixa_conta_bancaria_optional.php)
- [2026_05_21_220000_add_caixa_bridge_to_fin_titulos_and_contas.php](../../../Modules/Financeiro/Database/Migrations/2026_05_21_220000_add_caixa_bridge_to_fin_titulos_and_contas.php)
- [2026_05_31_230000_add_conciliacao_cols_to_fin_extrato_lancamentos.php](../../../Modules/Financeiro/Database/Migrations/2026_05_31_230000_add_conciliacao_cols_to_fin_extrato_lancamentos.php)
- [2026_06_01_000000_add_unificacao_cols_to_fin_extrato_lancamentos.php](../../../Modules/Financeiro/Database/Migrations/2026_06_01_000000_add_unificacao_cols_to_fin_extrato_lancamentos.php)
- [2026_06_01_000001_add_external_id_unique_to_fin_extrato_lancamentos.php](../../../Modules/Financeiro/Database/Migrations/2026_06_01_000001_add_external_id_unique_to_fin_extrato_lancamentos.php)
- [2026_06_03_120000_add_forma_pagamento_to_fin_titulos.php](../../../Modules/Financeiro/Database/Migrations/2026_06_03_120000_add_forma_pagamento_to_fin_titulos.php)
- [2026_06_04_120000_add_conta_bancaria_id_to_fin_titulos.php](../../../Modules/Financeiro/Database/Migrations/2026_06_04_120000_add_conta_bancaria_id_to_fin_titulos.php)

## Seeders — 3

- [FinanceiroDatabaseSeeder.php](../../../Modules/Financeiro/Database/Seeders/FinanceiroDatabaseSeeder.php)
- [FinanceiroDemoSeeder.php](../../../Modules/Financeiro/Database/Seeders/FinanceiroDemoSeeder.php)
- [PlanoContasBrSeeder.php](../../../Modules/Financeiro/Database/Seeders/PlanoContasBrSeeder.php)

## Config — 2

- [config.php](../../../Modules/Financeiro/Config/config.php)
- [retention.php](../../../Modules/Financeiro/Config/retention.php)

## Views (Blade) — 3

- 3 arquivos em [Modules/Financeiro/Resources/views/](../../../Modules/Financeiro/Resources/views) — cobertura é do `casos-gate`/`screen-coverage`, não deste índice.

## Telas (Inertia/React) — 21

- [Dashboard.tsx](../../../resources/js/Pages/Financeiro/Advisor/Dashboard.tsx)
- [Login.tsx](../../../resources/js/Pages/Financeiro/Advisor/Login.tsx)
- [AssinaturaAtualizar.tsx](../../../resources/js/Pages/Financeiro/AssinaturaAtualizar.tsx)
- [Index.tsx](../../../resources/js/Pages/Financeiro/Caixa/Index.tsx)
- [Index.tsx](../../../resources/js/Pages/Financeiro/Categorias/Index.tsx)
- [Index.tsx](../../../resources/js/Pages/Financeiro/Cobranca/Index.tsx)
- [Index.tsx](../../../resources/js/Pages/Financeiro/Conciliacao/Index.tsx)
- [Contador.tsx](../../../resources/js/Pages/Financeiro/Configuracoes/Contador.tsx)
- [Index.tsx](../../../resources/js/Pages/Financeiro/ContasBancarias/Index.tsx)
- [Index.tsx](../../../resources/js/Pages/Financeiro/ContasPagar/Index.tsx)
- [Index.tsx](../../../resources/js/Pages/Financeiro/ContasReceber/Index.tsx)
- [Index.tsx](../../../resources/js/Pages/Financeiro/Dashboard/Index.tsx)
- [Index.tsx](../../../resources/js/Pages/Financeiro/Dre/Index.tsx)
- [Index.tsx](../../../resources/js/Pages/Financeiro/Extrato/Index.tsx)
- [Index.tsx](../../../resources/js/Pages/Financeiro/Fluxo/Index.tsx)
- [Index.tsx](../../../resources/js/Pages/Financeiro/Impostos/Index.tsx)
- [Index.tsx](../../../resources/js/Pages/Financeiro/PlanoContas/Index.tsx)
- [ProvaViva.tsx](../../../resources/js/Pages/Financeiro/ProvaViva.tsx)
- [Index.tsx](../../../resources/js/Pages/Financeiro/Relatorios/Index.tsx)
- [Index.tsx](../../../resources/js/Pages/Financeiro/Unificado/Index.tsx)
- [Novo.tsx](../../../resources/js/Pages/Financeiro/Unificado/Novo.tsx)

## Componentes / apoio de tela — 39

- [CategoriaSheet.tsx](../../../resources/js/Pages/Financeiro/Categorias/components/CategoriaSheet.tsx)
- [AiResumoMes.tsx](../../../resources/js/Pages/Financeiro/Cobranca/_components/AiResumoMes.tsx)
- [CheatSheet.tsx](../../../resources/js/Pages/Financeiro/Cobranca/_components/CheatSheet.tsx)
- [DrawerCobranca.tsx](../../../resources/js/Pages/Financeiro/Cobranca/_components/DrawerCobranca.tsx)
- [FunnelStrip.tsx](../../../resources/js/Pages/Financeiro/Cobranca/_components/FunnelStrip.tsx)
- [SheetNovaCobranca.tsx](../../../resources/js/Pages/Financeiro/Cobranca/_components/SheetNovaCobranca.tsx)
- [SheetRemessaRetorno.tsx](../../../resources/js/Pages/Financeiro/Cobranca/_components/SheetRemessaRetorno.tsx)
- [atoms.tsx](../../../resources/js/Pages/Financeiro/Cobranca/_components/atoms.tsx)
- [ConfigurarBoletoSheet.tsx](../../../resources/js/Pages/Financeiro/ContasBancarias/components/ConfigurarBoletoSheet.tsx)
- [BalanceteView.tsx](../../../resources/js/Pages/Financeiro/Dre/_components/BalanceteView.tsx)
- [BalancoView.tsx](../../../resources/js/Pages/Financeiro/Dre/_components/BalancoView.tsx)
- [ClienteCombobox.tsx](../../../resources/js/Pages/Financeiro/Unificado/_components/ClienteCombobox.tsx)
- [FinAgeing.tsx](../../../resources/js/Pages/Financeiro/Unificado/_components/FinAgeing.tsx)
- [FinAnexosPanel.tsx](../../../resources/js/Pages/Financeiro/Unificado/_components/FinAnexosPanel.tsx)
- [FinAnomalyDetector.tsx](../../../resources/js/Pages/Financeiro/Unificado/_components/FinAnomalyDetector.tsx)
- [FinAuditTrail.tsx](../../../resources/js/Pages/Financeiro/Unificado/_components/FinAuditTrail.tsx)
- [FinBaixaSheet.tsx](../../../resources/js/Pages/Financeiro/Unificado/_components/FinBaixaSheet.tsx)
- [FinChecklistFechamento.tsx](../../../resources/js/Pages/Financeiro/Unificado/_components/FinChecklistFechamento.tsx)
- [FinCommentsThread.tsx](../../../resources/js/Pages/Financeiro/Unificado/_components/FinCommentsThread.tsx)
- [FinConferidoToggle.tsx](../../../resources/js/Pages/Financeiro/Unificado/_components/FinConferidoToggle.tsx)
- [FinCrossLinkify.tsx](../../../resources/js/Pages/Financeiro/Unificado/_components/FinCrossLinkify.tsx)
- [FinEditPanel.tsx](../../../resources/js/Pages/Financeiro/Unificado/_components/FinEditPanel.tsx)
- [FinMonthDigest.tsx](../../../resources/js/Pages/Financeiro/Unificado/_components/FinMonthDigest.tsx)
- [FinMonthResume.tsx](../../../resources/js/Pages/Financeiro/Unificado/_components/FinMonthResume.tsx)
- [FinOcrBoletoSheet.tsx](../../../resources/js/Pages/Financeiro/Unificado/_components/FinOcrBoletoSheet.tsx)
- [FinPartyHistory.tsx](../../../resources/js/Pages/Financeiro/Unificado/_components/FinPartyHistory.tsx)
- [FinPeriodBar.tsx](../../../resources/js/Pages/Financeiro/Unificado/_components/FinPeriodBar.tsx)
- [FinPillContaIndefinida.tsx](../../../resources/js/Pages/Financeiro/Unificado/_components/FinPillContaIndefinida.tsx)
- [FinPillFrescor.tsx](../../../resources/js/Pages/Financeiro/Unificado/_components/FinPillFrescor.tsx)
- [FinPresentationMode.tsx](../../../resources/js/Pages/Financeiro/Unificado/_components/FinPresentationMode.tsx)
- [FinTranscriptPDF.tsx](../../../resources/js/Pages/Financeiro/Unificado/_components/FinTranscriptPDF.tsx)
- [FinTroubleshooter.tsx](../../../resources/js/Pages/Financeiro/Unificado/_components/FinTroubleshooter.tsx)
- [PlanoContaCombobox.tsx](../../../resources/js/Pages/Financeiro/Unificado/_components/PlanoContaCombobox.tsx)
- [TituloCreateSheet.tsx](../../../resources/js/Pages/Financeiro/Unificado/_components/TituloCreateSheet.tsx)
- [TituloEditSheet.tsx](../../../resources/js/Pages/Financeiro/Unificado/_components/TituloEditSheet.tsx)
- [useFinFavs.tsx](../../../resources/js/Pages/Financeiro/Unificado/_components/useFinFavs.tsx)
- [FinStatStrip.tsx](../../../resources/js/Pages/Financeiro/_shared/FinStatStrip.tsx)
- [FinanceiroPrimaryButton.tsx](../../../resources/js/Pages/Financeiro/_shared/FinanceiroPrimaryButton.tsx)
- [FinanceiroSubNav.tsx](../../../resources/js/Pages/Financeiro/_shared/FinanceiroSubNav.tsx)

## Charters (lei da tela) — 21

- [Dashboard.charter.md](../../../resources/js/Pages/Financeiro/Advisor/Dashboard.charter.md)
- [Login.charter.md](../../../resources/js/Pages/Financeiro/Advisor/Login.charter.md)
- [AssinaturaAtualizar.charter.md](../../../resources/js/Pages/Financeiro/AssinaturaAtualizar.charter.md)
- [Index.charter.md](../../../resources/js/Pages/Financeiro/Caixa/Index.charter.md)
- [Index.charter.md](../../../resources/js/Pages/Financeiro/Categorias/Index.charter.md)
- [Index.charter.md](../../../resources/js/Pages/Financeiro/Cobranca/Index.charter.md)
- [Index.charter.md](../../../resources/js/Pages/Financeiro/Conciliacao/Index.charter.md)
- [Contador.charter.md](../../../resources/js/Pages/Financeiro/Configuracoes/Contador.charter.md)
- [Index.charter.md](../../../resources/js/Pages/Financeiro/ContasBancarias/Index.charter.md)
- [Index.charter.md](../../../resources/js/Pages/Financeiro/ContasPagar/Index.charter.md)
- [Index.charter.md](../../../resources/js/Pages/Financeiro/ContasReceber/Index.charter.md)
- [Index.charter.md](../../../resources/js/Pages/Financeiro/Dashboard/Index.charter.md)
- [Index.charter.md](../../../resources/js/Pages/Financeiro/Dre/Index.charter.md)
- [Index.charter.md](../../../resources/js/Pages/Financeiro/Extrato/Index.charter.md)
- [Index.charter.md](../../../resources/js/Pages/Financeiro/Fluxo/Index.charter.md)
- [Index.charter.md](../../../resources/js/Pages/Financeiro/Impostos/Index.charter.md)
- [Index.charter.md](../../../resources/js/Pages/Financeiro/PlanoContas/Index.charter.md)
- [ProvaViva.charter.md](../../../resources/js/Pages/Financeiro/ProvaViva.charter.md)
- [Index.charter.md](../../../resources/js/Pages/Financeiro/Relatorios/Index.charter.md)
- [Index.charter.md](../../../resources/js/Pages/Financeiro/Unificado/Index.charter.md)
- [Novo.charter.md](../../../resources/js/Pages/Financeiro/Unificado/Novo.charter.md)

## Casos (contrato UC) — 5

- [Index.casos.md](../../../resources/js/Pages/Financeiro/ContasPagar/Index.casos.md)
- [Index.casos.md](../../../resources/js/Pages/Financeiro/ContasReceber/Index.casos.md)
- [Index.casos.md](../../../resources/js/Pages/Financeiro/Impostos/Index.casos.md)
- [ProvaViva.casos.md](../../../resources/js/Pages/Financeiro/ProvaViva.casos.md)
- [Index.casos.md](../../../resources/js/Pages/Financeiro/Unificado/Index.casos.md)

## Testes (Pest) — 80

- 80 arquivos em [Modules/Financeiro/Tests/Feature/](../../../Modules/Financeiro/Tests/Feature) — cobertura é do `casos-gate`/`screen-coverage`, não deste índice.

## Outros (raiz/misc) — 4

- [BoletoStrategy.php](../../../Modules/Financeiro/Contracts/BoletoStrategy.php)
- [DreExport.php](../../../Modules/Financeiro/Exports/DreExport.php)
- [BaixaRepository.php](../../../Modules/Financeiro/Repositories/BaixaRepository.php)
- [TituloRepository.php](../../../Modules/Financeiro/Repositories/TituloRepository.php)

