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

**Total mapeado:** 184 arquivos em 9 papéis.

## Controllers — 6

- [SellAuditController.php](../../../app/Http/Controllers/SellAuditController.php)
- [SellCommissionSplitController.php](../../../app/Http/Controllers/SellCommissionSplitController.php)
- [SellController.php](../../../app/Http/Controllers/SellController.php)
- [SellPosController.php](../../../app/Http/Controllers/SellPosController.php)
- [SellReturnController.php](../../../app/Http/Controllers/SellReturnController.php)
- [SellTranscriptPdfController.php](../../../app/Http/Controllers/SellTranscriptPdfController.php)

## Motor (Utils/Domínio) — 33

- [GuardsFsmTransitions.php](../../../app/Domain/Fsm/Concerns/GuardsFsmTransitions.php)
- [SideEffectInterface.php](../../../app/Domain/Fsm/Contracts/SideEffectInterface.php)
- [InvalidActionForCurrentStageException.php](../../../app/Domain/Fsm/Exceptions/InvalidActionForCurrentStageException.php)
- [UnauthorizedActionException.php](../../../app/Domain/Fsm/Exceptions/UnauthorizedActionException.php)
- [ExpireStaleReservationsJob.php](../../../app/Domain/Fsm/Jobs/ExpireStaleReservationsJob.php)
- [ResetFsmAuthorizationFlag.php](../../../app/Domain/Fsm/Listeners/ResetFsmAuthorizationFlag.php)
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

- [create.blade.php](../../../resources/views/sale_pos/create.blade.php)
- [create_old.blade.php](../../../resources/views/sale_pos/create_old.blade.php)
- [draft.blade.php](../../../resources/views/sale_pos/draft.blade.php)
- [edit.blade.php](../../../resources/views/sale_pos/edit.blade.php)
- [edit_old.blade.php](../../../resources/views/sale_pos/edit_old.blade.php)
- [index.blade.php](../../../resources/views/sale_pos/index.blade.php)
- [activity_row.blade.php](../../../resources/views/sale_pos/partials/activity_row.blade.php)
- [configure_search_modal.blade.php](../../../resources/views/sale_pos/partials/configure_search_modal.blade.php)
- [edit_discount_modal.blade.php](../../../resources/views/sale_pos/partials/edit_discount_modal.blade.php)
- [edit_order_tax_modal.blade.php](../../../resources/views/sale_pos/partials/edit_order_tax_modal.blade.php)
- [edit_shipping_modal.blade.php](../../../resources/views/sale_pos/partials/edit_shipping_modal.blade.php)
- [featured_products.blade.php](../../../resources/views/sale_pos/partials/featured_products.blade.php)
- [guest_payment_form.blade.php](../../../resources/views/sale_pos/partials/guest_payment_form.blade.php)
- [invoice_url_modal.blade.php](../../../resources/views/sale_pos/partials/invoice_url_modal.blade.php)
- [keyboard_shortcuts.blade.php](../../../resources/views/sale_pos/partials/keyboard_shortcuts.blade.php)
- [keyboard_shortcuts_details.blade.php](../../../resources/views/sale_pos/partials/keyboard_shortcuts_details.blade.php)
- [mobile_product_suggestions.blade.php](../../../resources/views/sale_pos/partials/mobile_product_suggestions.blade.php)
- [payment_modal.blade.php](../../../resources/views/sale_pos/partials/payment_modal.blade.php)
- [payment_row.blade.php](../../../resources/views/sale_pos/partials/payment_row.blade.php)
- [payment_row_form.blade.php](../../../resources/views/sale_pos/partials/payment_row_form.blade.php)
- [payment_type_details.blade.php](../../../resources/views/sale_pos/partials/payment_type_details.blade.php)
- [pos_details.blade.php](../../../resources/views/sale_pos/partials/pos_details.blade.php)
- [pos_form.blade.php](../../../resources/views/sale_pos/partials/pos_form.blade.php)
- [pos_form_actions.blade.php](../../../resources/views/sale_pos/partials/pos_form_actions.blade.php)
- [pos_form_edit.blade.php](../../../resources/views/sale_pos/partials/pos_form_edit.blade.php)
- [pos_form_totals.blade.php](../../../resources/views/sale_pos/partials/pos_form_totals.blade.php)
- [pos_sidebar.blade.php](../../../resources/views/sale_pos/partials/pos_sidebar.blade.php)
- [product_list.blade.php](../../../resources/views/sale_pos/partials/product_list.blade.php)
- [product_list_box.blade.php](../../../resources/views/sale_pos/partials/product_list_box.blade.php)
- [product_list_paginator.blade.php](../../../resources/views/sale_pos/partials/product_list_paginator.blade.php)
- [recent_transactions.blade.php](../../../resources/views/sale_pos/partials/recent_transactions.blade.php)
- [recent_transactions_box.blade.php](../../../resources/views/sale_pos/partials/recent_transactions_box.blade.php)
- [recent_transactions_modal.blade.php](../../../resources/views/sale_pos/partials/recent_transactions_modal.blade.php)
- [recurring_invoice_modal.blade.php](../../../resources/views/sale_pos/partials/recurring_invoice_modal.blade.php)
- [right_div.blade.php](../../../resources/views/sale_pos/partials/right_div.blade.php)
- [row_edit_product_price_modal.blade.php](../../../resources/views/sale_pos/partials/row_edit_product_price_modal.blade.php)
- [sale_line_details.blade.php](../../../resources/views/sale_pos/partials/sale_line_details.blade.php)
- [sale_table_javascript.blade.php](../../../resources/views/sale_pos/partials/sale_table_javascript.blade.php)
- [sales_table.blade.php](../../../resources/views/sale_pos/partials/sales_table.blade.php)
- [service_staff_availability_modal.blade.php](../../../resources/views/sale_pos/partials/service_staff_availability_modal.blade.php)
- [service_staff_replacement_modal.blade.php](../../../resources/views/sale_pos/partials/service_staff_replacement_modal.blade.php)
- [show_invoice.blade.php](../../../resources/views/sale_pos/partials/show_invoice.blade.php)
- [subscriptions_table.blade.php](../../../resources/views/sale_pos/partials/subscriptions_table.blade.php)
- [subscriptions_table_javascript.blade.php](../../../resources/views/sale_pos/partials/subscriptions_table_javascript.blade.php)
- [suspend_note_modal.blade.php](../../../resources/views/sale_pos/partials/suspend_note_modal.blade.php)
- [suspended_sales_modal.blade.php](../../../resources/views/sale_pos/partials/suspended_sales_modal.blade.php)
- [weighing_scale_modal.blade.php](../../../resources/views/sale_pos/partials/weighing_scale_modal.blade.php)
- [product_row.blade.php](../../../resources/views/sale_pos/product_row.blade.php)
- [quotations.blade.php](../../../resources/views/sale_pos/quotations.blade.php)
- [classic.blade.php](../../../resources/views/sale_pos/receipts/classic.blade.php)
- [columnize-taxes.blade.php](../../../resources/views/sale_pos/receipts/columnize-taxes.blade.php)
- [delivery_note.blade.php](../../../resources/views/sale_pos/receipts/delivery_note.blade.php)
- [detailed.blade.php](../../../resources/views/sale_pos/receipts/detailed.blade.php)
- [elegant.blade.php](../../../resources/views/sale_pos/receipts/elegant.blade.php)
- [elegant_modified.blade.php](../../../resources/views/sale_pos/receipts/elegant_modified.blade.php)
- [packing_slip.blade.php](../../../resources/views/sale_pos/receipts/packing_slip.blade.php)
- [common_repair_invoice.blade.php](../../../resources/views/sale_pos/receipts/partial/common_repair_invoice.blade.php)
- [slim.blade.php](../../../resources/views/sale_pos/receipts/slim.blade.php)
- [slim2.blade.php](../../../resources/views/sale_pos/receipts/slim2.blade.php)
- [show.blade.php](../../../resources/views/sale_pos/show.blade.php)
- [subscriptions.blade.php](../../../resources/views/sale_pos/subscriptions.blade.php)
- [create.blade.php](../../../resources/views/sell/create.blade.php)
- [edit.blade.php](../../../resources/views/sell/edit.blade.php)
- [index.blade.php](../../../resources/views/sell/index.blade.php)
- [edit_shipping.blade.php](../../../resources/views/sell/partials/edit_shipping.blade.php)
- [media_table.blade.php](../../../resources/views/sell/partials/media_table.blade.php)
- [payment_status.blade.php](../../../resources/views/sell/partials/payment_status.blade.php)
- [sell_list_filters.blade.php](../../../resources/views/sell/partials/sell_list_filters.blade.php)
- [shipments.blade.php](../../../resources/views/sell/shipments.blade.php)
- [view_media.blade.php](../../../resources/views/sell/view_media.blade.php)

## Telas (Inertia/React) — 9

- [Index.tsx](../../../resources/js/Pages/Sells/Caixa/Index.tsx)
- [Create.tsx](../../../resources/js/Pages/Sells/Create.tsx)
- [CreateV3.tsx](../../../resources/js/Pages/Sells/CreateV3.tsx)
- [Drafts.tsx](../../../resources/js/Pages/Sells/Drafts.tsx)
- [Edit.tsx](../../../resources/js/Pages/Sells/Edit.tsx)
- [Index.tsx](../../../resources/js/Pages/Sells/Index.tsx)
- [Quotations.tsx](../../../resources/js/Pages/Sells/Quotations.tsx)
- [Show.tsx](../../../resources/js/Pages/Sells/Show.tsx)
- [Subscriptions.tsx](../../../resources/js/Pages/Sells/Subscriptions.tsx)

## Componentes / apoio de tela — 41

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
- [ComissaoDrawer.tsx](../../../resources/js/Pages/Sells/_components/v3/ComissaoDrawer.tsx)
- [EntregaFrete.tsx](../../../resources/js/Pages/Sells/_components/v3/EntregaFrete.tsx)
- [ItemDetalhe.tsx](../../../resources/js/Pages/Sells/_components/v3/ItemDetalhe.tsx)
- [LancarItem.tsx](../../../resources/js/Pages/Sells/_components/v3/LancarItem.tsx)
- [ParcelasDrawer.tsx](../../../resources/js/Pages/Sells/_components/v3/ParcelasDrawer.tsx)
- [primitivos.tsx](../../../resources/js/Pages/Sells/_components/v3/primitivos.tsx)

## Charters (lei da tela) — 9

- [Index.charter.md](../../../resources/js/Pages/Sells/Caixa/Index.charter.md)
- [Create.charter.md](../../../resources/js/Pages/Sells/Create.charter.md)
- [CreateV3.charter.md](../../../resources/js/Pages/Sells/CreateV3.charter.md)
- [Drafts.charter.md](../../../resources/js/Pages/Sells/Drafts.charter.md)
- [Edit.charter.md](../../../resources/js/Pages/Sells/Edit.charter.md)
- [Index.charter.md](../../../resources/js/Pages/Sells/Index.charter.md)
- [Quotations.charter.md](../../../resources/js/Pages/Sells/Quotations.charter.md)
- [Show.charter.md](../../../resources/js/Pages/Sells/Show.charter.md)
- [Subscriptions.charter.md](../../../resources/js/Pages/Sells/Subscriptions.charter.md)

## Casos (contrato UC) — 4

- [Create.casos.md](../../../resources/js/Pages/Sells/Create.casos.md)
- [CreateV3.casos.md](../../../resources/js/Pages/Sells/CreateV3.casos.md)
- [Index.casos.md](../../../resources/js/Pages/Sells/Index.casos.md)
- [Show.casos.md](../../../resources/js/Pages/Sells/Show.casos.md)

## Demais arquivos (manifestos, docs, assets e misc) — 9

- [Create.design-spec.json](../../../resources/js/Pages/Sells/Create.design-spec.json)
- [PaymentRow.test-pending.md](../../../resources/js/Pages/Sells/_components/PaymentRow.test-pending.md)
- [dropdownEntries.ts](../../../resources/js/Pages/Sells/_components/dropdownEntries.ts)
- [calculo-item.ts](../../../resources/js/Pages/Sells/_components/v3/calculo-item.ts)
- [comissao-dominio.ts](../../../resources/js/Pages/Sells/_components/v3/comissao-dominio.ts)
- [entrega-dominio.ts](../../../resources/js/Pages/Sells/_components/v3/entrega-dominio.ts)
- [item-fiscal-dominio.ts](../../../resources/js/Pages/Sells/_components/v3/item-fiscal-dominio.ts)
- [numeros.ts](../../../resources/js/Pages/Sells/_components/v3/numeros.ts)
- [parcelas-dominio.ts](../../../resources/js/Pages/Sells/_components/v3/parcelas-dominio.ts)
