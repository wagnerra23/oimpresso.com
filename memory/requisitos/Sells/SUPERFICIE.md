---
name: "SUPERFÍCIE — Sells"
description: "Índice GERADO dos artefatos do módulo Sells reconhecidos pelo classificador, agrupados por papel. NÃO editar à mão."
type: reference
authority: generated
lifecycle: ativo
module: Sells
tabelas_dominio: ["transactions", "transaction_sell_lines", "transaction_payments"]
---

# 🗺️ Superfície de código — Sells

> ⚙️ **Gerado por máquina** (`scripts/governance/module-surface.mjs`). NÃO edite à mão — a próxima geração sobrescreve.
> Regenerar: `node scripts/governance/module-surface.mjs Sells --write`. Validar frescor: `--check` (exit 1 se a árvore mudou e isto não foi regenerado).
>
> **O que isto é:** o módulo `Sells` é CLASSE B — o código mora no núcleo UltimatePOS (`app/`), sem diretório modular homônimo. A membership vem de uma **semente curada** de paths do core declarada em `module-surface.mjs::CORE_APP_MODULES` (revisável no diff) + `resources/js/Pages/Sells/**`. **O que NÃO é:** cobertura/nota/status (donos: `screen-coverage-map.mjs` + `casos-gate`). As **tabelas do domínio** (`transactions`, `transaction_sell_lines`, `transaction_payments`) são metadado-ÂNCORA declarado, **não** o derivador (derivar por tabela over-inclui — medido 2026-07-21).

**Total mapeado:** 164 arquivos em 8 papéis.

## Controllers — 6

- [SellAuditController.php](../../../app/Http/Controllers/SellAuditController.php)
- [SellCommissionSplitController.php](../../../app/Http/Controllers/SellCommissionSplitController.php)
- [SellController.php](../../../app/Http/Controllers/SellController.php)
- [SellPosController.php](../../../app/Http/Controllers/SellPosController.php)
- [SellReturnController.php](../../../app/Http/Controllers/SellReturnController.php)
- [SellTranscriptPdfController.php](../../../app/Http/Controllers/SellTranscriptPdfController.php)

## Motor (Utils/Domínio) — 32

- [GuardsFsmTransitions.php](../../../app/Domain/Fsm/Concerns/GuardsFsmTransitions.php)
- [SideEffectInterface.php](../../../app/Domain/Fsm/Contracts/SideEffectInterface.php)
- [InvalidActionForCurrentStageException.php](../../../app/Domain/Fsm/Exceptions/InvalidActionForCurrentStageException.php)
- [UnauthorizedActionException.php](../../../app/Domain/Fsm/Exceptions/UnauthorizedActionException.php)
- [ExpireStaleReservationsJob.php](../../../app/Domain/Fsm/Jobs/ExpireStaleReservationsJob.php)
- [SaleProcess.php](../../../app/Domain/Fsm/Models/SaleProcess.php)
- [SaleProcessStage.php](../../../app/Domain/Fsm/Models/SaleProcessStage.php)
- [SaleStageAction.php](../../../app/Domain/Fsm/Models/SaleStageAction.php)
- [SaleStageActionRole.php](../../../app/Domain/Fsm/Models/SaleStageActionRole.php)
- [SaleStageHistory.php](../../../app/Domain/Fsm/Models/SaleStageHistory.php)
- [StockReservation.php](../../../app/Domain/Fsm/Models/StockReservation.php)
- [TransactionDocument.php](../../../app/Domain/Fsm/Models/TransactionDocument.php)
- [TransactionFsmObserver.php](../../../app/Domain/Fsm/Observers/TransactionFsmObserver.php)
- [StageActionPolicy.php](../../../app/Domain/Fsm/Policies/StageActionPolicy.php)
- [ExecuteStageActionService.php](../../../app/Domain/Fsm/Services/ExecuteStageActionService.php)
- [FsmDriftDetector.php](../../../app/Domain/Fsm/Services/FsmDriftDetector.php)
- [InitialStageResolver.php](../../../app/Domain/Fsm/Services/InitialStageResolver.php)
- [CancelarServicoCacamba.php](../../../app/Domain/Fsm/SideEffects/CancelarServicoCacamba.php)
- [CancelarVendaCascade.php](../../../app/Domain/Fsm/SideEffects/CancelarVendaCascade.php)
- [ConcluirServicoCacamba.php](../../../app/Domain/Fsm/SideEffects/ConcluirServicoCacamba.php)
- [ConsumirEstoque.php](../../../app/Domain/Fsm/SideEffects/ConsumirEstoque.php)
- [EmitirNovaAposCancelamento.php](../../../app/Domain/Fsm/SideEffects/EmitirNovaAposCancelamento.php)
- [EnviarCacambaManutencao.php](../../../app/Domain/Fsm/SideEffects/EnviarCacambaManutencao.php)
- [IniciarLocacaoCacamba.php](../../../app/Domain/Fsm/SideEffects/IniciarLocacaoCacamba.php)
- [IniciarServicoCacamba.php](../../../app/Domain/Fsm/SideEffects/IniciarServicoCacamba.php)
- [InutilizarFaixaNfe.php](../../../app/Domain/Fsm/SideEffects/InutilizarFaixaNfe.php)
- [LiberarReserva.php](../../../app/Domain/Fsm/SideEffects/LiberarReserva.php)
- [RecolherCacamba.php](../../../app/Domain/Fsm/SideEffects/RecolherCacamba.php)
- [ReservarEstoque.php](../../../app/Domain/Fsm/SideEffects/ReservarEstoque.php)
- [VoltarCacambaDisponivel.php](../../../app/Domain/Fsm/SideEffects/VoltarCacambaDisponivel.php)
- [FsmAuthorizationFlag.php](../../../app/Domain/Fsm/Support/FsmAuthorizationFlag.php)
- [TransactionUtil.php](../../../app/Utils/TransactionUtil.php)

## Models / Entities — 3

- [Transaction.php](../../../app/Transaction.php)
- [TransactionPayment.php](../../../app/TransactionPayment.php)
- [TransactionSellLine.php](../../../app/TransactionSellLine.php)

## Views (Blade) — 70

- 70 arquivos em [resources/views/sale_pos/](../../../resources/views/sale_pos) — cobertura é do `casos-gate`/`screen-coverage`, não deste índice.

## Telas (Inertia/React) — 8

- [Index.tsx](../../../resources/js/Pages/Sells/Caixa/Index.tsx)
- [Create.tsx](../../../resources/js/Pages/Sells/Create.tsx)
- [Drafts.tsx](../../../resources/js/Pages/Sells/Drafts.tsx)
- [Edit.tsx](../../../resources/js/Pages/Sells/Edit.tsx)
- [Index.tsx](../../../resources/js/Pages/Sells/Index.tsx)
- [Quotations.tsx](../../../resources/js/Pages/Sells/Quotations.tsx)
- [Show.tsx](../../../resources/js/Pages/Sells/Show.tsx)
- [Subscriptions.tsx](../../../resources/js/Pages/Sells/Subscriptions.tsx)

## Componentes / apoio de tela — 35

- [CobrancaChip.tsx](../../../resources/js/Pages/Sells/_components/CobrancaChip.tsx)
- [CobrancaDrawer.tsx](../../../resources/js/Pages/Sells/_components/CobrancaDrawer.tsx)
- [CommissionSplitEditor.tsx](../../../resources/js/Pages/Sells/_components/CommissionSplitEditor.tsx)
- [CriarOsButton.tsx](../../../resources/js/Pages/Sells/_components/CriarOsButton.tsx)
- [CustomerSearchAutocomplete.tsx](../../../resources/js/Pages/Sells/_components/CustomerSearchAutocomplete.tsx)
- [FiscalSection.tsx](../../../resources/js/Pages/Sells/_components/FiscalSection.tsx)
- [FsmActionPanel.tsx](../../../resources/js/Pages/Sells/_components/FsmActionPanel.tsx)
- [PaymentRow.tsx](../../../resources/js/Pages/Sells/_components/PaymentRow.tsx)
- [ProductLineCard.tsx](../../../resources/js/Pages/Sells/_components/ProductLineCard.tsx)
- [ProductSearchAutocomplete.tsx](../../../resources/js/Pages/Sells/_components/ProductSearchAutocomplete.tsx)
- [QuickAddCustomerSheet.tsx](../../../resources/js/Pages/Sells/_components/QuickAddCustomerSheet.tsx)
- [QuickAddVehicleSheet.tsx](../../../resources/js/Pages/Sells/_components/QuickAddVehicleSheet.tsx)
- [QuickPaymentDialog.tsx](../../../resources/js/Pages/Sells/_components/QuickPaymentDialog.tsx)
- [QuickPaymentPopover.tsx](../../../resources/js/Pages/Sells/_components/QuickPaymentPopover.tsx)
- [SaleAiPanel.tsx](../../../resources/js/Pages/Sells/_components/SaleAiPanel.tsx)
- [SaleAuditTrail.tsx](../../../resources/js/Pages/Sells/_components/SaleAuditTrail.tsx)
- [SaleItemComments.tsx](../../../resources/js/Pages/Sells/_components/SaleItemComments.tsx)
- [SaleJourneyStepper.tsx](../../../resources/js/Pages/Sells/_components/SaleJourneyStepper.tsx)
- [SaleLinkifier.tsx](../../../resources/js/Pages/Sells/_components/SaleLinkifier.tsx)
- [SaleMessagePreview.tsx](../../../resources/js/Pages/Sells/_components/SaleMessagePreview.tsx)
- [SaleOrcamentoA4.tsx](../../../resources/js/Pages/Sells/_components/SaleOrcamentoA4.tsx)
- [SalePresentationMode.tsx](../../../resources/js/Pages/Sells/_components/SalePresentationMode.tsx)
- [SaleReciboPrint80mm.tsx](../../../resources/js/Pages/Sells/_components/SaleReciboPrint80mm.tsx)
- [SaleSheet.tsx](../../../resources/js/Pages/Sells/_components/SaleSheet.tsx)
- [SaleTimeline.tsx](../../../resources/js/Pages/Sells/_components/SaleTimeline.tsx)
- [SaleTranscriptPDF.tsx](../../../resources/js/Pages/Sells/_components/SaleTranscriptPDF.tsx)
- [SellsCheatSheet.tsx](../../../resources/js/Pages/Sells/_components/SellsCheatSheet.tsx)
- [SellsDateFilter.tsx](../../../resources/js/Pages/Sells/_components/SellsDateFilter.tsx)
- [SellsTabelaUnificada.tsx](../../../resources/js/Pages/Sells/_components/SellsTabelaUnificada.tsx)
- [SellsTabsVisao.tsx](../../../resources/js/Pages/Sells/_components/SellsTabsVisao.tsx)
- [VdBulkEmitModal.tsx](../../../resources/js/Pages/Sells/_components/VdBulkEmitModal.tsx)
- [VdNextActionPanel.tsx](../../../resources/js/Pages/Sells/_components/VdNextActionPanel.tsx)
- [VdNfeEmitModal.tsx](../../../resources/js/Pages/Sells/_components/VdNfeEmitModal.tsx)
- [VdNfseEmitModal.tsx](../../../resources/js/Pages/Sells/_components/VdNfseEmitModal.tsx)
- [VdSource.tsx](../../../resources/js/Pages/Sells/_components/VdSource.tsx)

## Charters (lei da tela) — 8

- [Index.charter.md](../../../resources/js/Pages/Sells/Caixa/Index.charter.md)
- [Create.charter.md](../../../resources/js/Pages/Sells/Create.charter.md)
- [Drafts.charter.md](../../../resources/js/Pages/Sells/Drafts.charter.md)
- [Edit.charter.md](../../../resources/js/Pages/Sells/Edit.charter.md)
- [Index.charter.md](../../../resources/js/Pages/Sells/Index.charter.md)
- [Quotations.charter.md](../../../resources/js/Pages/Sells/Quotations.charter.md)
- [Show.charter.md](../../../resources/js/Pages/Sells/Show.charter.md)
- [Subscriptions.charter.md](../../../resources/js/Pages/Sells/Subscriptions.charter.md)

## Casos (contrato UC) — 2

- [Create.casos.md](../../../resources/js/Pages/Sells/Create.casos.md)
- [Index.casos.md](../../../resources/js/Pages/Sells/Index.casos.md)

