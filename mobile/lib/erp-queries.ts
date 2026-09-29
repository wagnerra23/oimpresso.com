import { useQueryClient } from "@tanstack/react-query";
import { useCallback } from "react";

import { trpc } from "@/lib/trpc";
import type { OP, Pedido, Produto, Transacao } from "@/lib/erp-context";

/**
 * Hooks that wrap tRPC + TanStack Query for each ERP entity.
 *
 * Pattern: each entity gets a `list()` query and `useCreate*`, `useUpdate*`,
 * `useDelete*` mutations. Mutations automatically invalidate the list query
 * via `onSuccess` so the UI re-fetches and stays consistent without manual
 * cache surgery.
 *
 * The components shouldn't need to know about tRPC: they import these hooks
 * and consume `{ data, isLoading, ... }` like any other React Query hook.
 */

// ─── PRODUTOS ──────────────────────────────────────────────────────────────

export function useProdutos() {
  return trpc.produtos.list.useQuery();
}

export function useProduto(id: string | undefined) {
  return trpc.produtos.getById.useQuery(
    { id: id ?? "" },
    { enabled: !!id },
  );
}

/**
 * Find the inventory item linked to a produto (1:1 mapping via produtoId).
 * Returns the first match or undefined.
 */
export function useInventoryByProduto(produtoId: string | undefined) {
  const inv = trpc.inventory.list.useQuery();
  return {
    ...inv,
    data: inv.data?.find((i) => i.produtoId === produtoId),
  };
}

export function useCreateProduto() {
  const utils = trpc.useUtils();
  return trpc.produtos.create.useMutation({
    onSuccess: () => {
      utils.produtos.list.invalidate();
    },
  });
}

export function useUpdateProduto() {
  const utils = trpc.useUtils();
  return trpc.produtos.update.useMutation({
    onSuccess: () => {
      utils.produtos.list.invalidate();
    },
  });
}

export function useDeleteProduto() {
  const utils = trpc.useUtils();
  return trpc.produtos.delete.useMutation({
    onSuccess: () => {
      utils.produtos.list.invalidate();
    },
  });
}

// ─── PEDIDOS ───────────────────────────────────────────────────────────────

export function usePedidos() {
  return trpc.pedidos.list.useQuery();
}

export function useCreatePedido() {
  const utils = trpc.useUtils();
  return trpc.pedidos.create.useMutation({
    onSuccess: () => {
      utils.pedidos.list.invalidate();
    },
  });
}

export function useUpdatePedido() {
  const utils = trpc.useUtils();
  return trpc.pedidos.update.useMutation({
    // Optimistic update — see https://tanstack.com/query/v5/docs/framework/react/guides/optimistic-updates
    onMutate: async (input) => {
      await utils.pedidos.list.cancel();
      const previous = utils.pedidos.list.getData();
      utils.pedidos.list.setData(undefined, (old) =>
        old ? old.map((p) => (p.id === input.id ? { ...p, ...input } : p)) : old,
      );
      return { previous };
    },
    onError: (_err, _input, ctx) => {
      if (ctx?.previous) utils.pedidos.list.setData(undefined, ctx.previous);
    },
    onSettled: () => {
      utils.pedidos.list.invalidate();
    },
  });
}

export function useDeletePedido() {
  const utils = trpc.useUtils();
  return trpc.pedidos.delete.useMutation({
    onSuccess: () => {
      utils.pedidos.list.invalidate();
    },
  });
}

// ─── OPS ────────────────────────────────────────────────────────────────────

export function useOps() {
  return trpc.ops.list.useQuery();
}

export function useCreateOP() {
  const utils = trpc.useUtils();
  return trpc.ops.create.useMutation({
    onSuccess: () => {
      utils.ops.list.invalidate();
    },
  });
}

export function useUpdateOP() {
  const utils = trpc.useUtils();
  return trpc.ops.update.useMutation({
    // Optimistic update — see https://tanstack.com/query/v5/docs/framework/react/guides/optimistic-updates
    onMutate: async (input) => {
      await utils.ops.list.cancel();
      const previous = utils.ops.list.getData();
      utils.ops.list.setData(undefined, (old) =>
        old ? old.map((o) => (o.id === input.id ? { ...o, ...input } : o)) : old,
      );
      return { previous };
    },
    onError: (_err, _input, ctx) => {
      if (ctx?.previous) utils.ops.list.setData(undefined, ctx.previous);
    },
    onSettled: () => {
      utils.ops.list.invalidate();
    },
  });
}

export function useDeleteOP() {
  const utils = trpc.useUtils();
  return trpc.ops.delete.useMutation({
    onSuccess: () => {
      utils.ops.list.invalidate();
    },
  });
}

// ─── CUSTOMERS ─────────────────────────────────────────────────────────────

export function useCustomers() {
  return trpc.customers.list.useQuery();
}

export function useCustomer(id: string | undefined) {
  return trpc.customers.getById.useQuery(
    { id: id ?? "" },
    { enabled: !!id },
  );
}

export function useCustomerSearch(q: string) {
  return trpc.customers.search.useQuery({ q });
}

export function useCreateCustomer() {
  const utils = trpc.useUtils();
  return trpc.customers.create.useMutation({
    onSuccess: () => {
      utils.customers.list.invalidate();
    },
  });
}

export function useUpdateCustomer() {
  const utils = trpc.useUtils();
  return trpc.customers.update.useMutation({
    // Optimistic update — matches the pedidos/ops pattern.
    onMutate: async (input) => {
      await utils.customers.list.cancel();
      const previous = utils.customers.list.getData();
      utils.customers.list.setData(undefined, (old) =>
        old
          ? old.map((c) => (c.id === input.id ? { ...c, ...input } : c))
          : old,
      );
      return { previous };
    },
    onError: (_err, _input, ctx) => {
      if (ctx?.previous) utils.customers.list.setData(undefined, ctx.previous);
    },
    onSettled: () => {
      utils.customers.list.invalidate();
    },
  });
}

export function useDeleteCustomer() {
  const utils = trpc.useUtils();
  return trpc.customers.delete.useMutation({
    onSuccess: () => {
      utils.customers.list.invalidate();
    },
  });
}

export function usePedidoItems(pedidoId: string | undefined) {
  return trpc.pedidos.getItems.useQuery(
    { pedidoId: pedidoId ?? "" },
    { enabled: !!pedidoId },
  );
}

// ─── TRANSACOES ─────────────────────────────────────────────────────────────

export function useTransacoes() {
  return trpc.transacoes.list.useQuery();
}

export function useCreateTransacao() {
  const utils = trpc.useUtils();
  return trpc.transacoes.create.useMutation({
    onSuccess: () => {
      utils.transacoes.list.invalidate();
    },
  });
}

export function useUpdateTransacao() {
  const utils = trpc.useUtils();
  return trpc.transacoes.update.useMutation({
    onSuccess: () => {
      utils.transacoes.list.invalidate();
    },
  });
}

export function useDeleteTransacao() {
  const utils = trpc.useUtils();
  return trpc.transacoes.delete.useMutation({
    onSuccess: () => {
      utils.transacoes.list.invalidate();
    },
  });
}

// ─── Helpers exposed for tests / advanced use ──────────────────────────────

/**
 * Hook returning a function that wipes every entity list from the cache.
 * Used on logout to make sure stale data doesn't leak between sessions.
 */
export function useResetERPCache() {
  const qc = useQueryClient();
  return useCallback(() => {
    qc.removeQueries();
  }, [qc]);
}

// Re-export types so screens can import everything from this module if they prefer.
export type { Produto, Pedido, OP, Transacao };

// ─── QUOTES (F2-01 Comunicação Visual) ────────────────────────────────────

export function useQuotes(status?:
  | "rascunho"
  | "enviado"
  | "aprovado"
  | "rejeitado"
  | "convertido") {
  return trpc.quotes.list.useQuery(status ? { status } : undefined);
}

export function useQuote(id: string | undefined) {
  return trpc.quotes.getById.useQuery(
    { id: id ?? "" },
    { enabled: !!id },
  );
}

export function useCreateQuote() {
  const utils = trpc.useUtils();
  return trpc.quotes.create.useMutation({
    onSuccess: () => {
      utils.quotes.list.invalidate();
    },
  });
}

export function useUpdateQuote() {
  const utils = trpc.useUtils();
  return trpc.quotes.update.useMutation({
    onMutate: async (input) => {
      await utils.quotes.list.cancel();
      const previous = utils.quotes.list.getData(undefined);
      // Optimistically patch top-level fields (not items) on the cached list.
      utils.quotes.list.setData(undefined, (old) =>
        old
          ? old.map((q) =>
              q.id === input.id
                ? {
                    ...q,
                    ...(input.titulo !== undefined ? { titulo: input.titulo } : {}),
                    ...(input.status !== undefined ? { status: input.status } : {}),
                    ...(input.validadeDias !== undefined
                      ? { validadeDias: input.validadeDias }
                      : {}),
                    ...(input.observacoes !== undefined
                      ? { observacoes: input.observacoes }
                      : {}),
                  }
                : q,
            )
          : old,
      );
      return { previous };
    },
    onError: (_err, _input, ctx) => {
      if (ctx?.previous) utils.quotes.list.setData(undefined, ctx.previous);
    },
    onSettled: (_data, _err, input) => {
      utils.quotes.list.invalidate();
      if (input?.id) utils.quotes.getById.invalidate({ id: input.id });
    },
  });
}

export function useDeleteQuote() {
  const utils = trpc.useUtils();
  return trpc.quotes.delete.useMutation({
    onSuccess: () => {
      utils.quotes.list.invalidate();
    },
  });
}

export function useGenerateQuotePdf() {
  const utils = trpc.useUtils();
  return trpc.quotes.generatePdf.useMutation({
    onSuccess: () => {
      utils.quotes.list.invalidate();
    },
  });
}

export function useConvertQuoteToPedido() {
  const utils = trpc.useUtils();
  return trpc.quotes.convertToPedido.useMutation({
    onSuccess: () => {
      utils.quotes.list.invalidate();
      utils.pedidos.list.invalidate();
    },
  });
}

export function usePriceTables() {
  return trpc.quotes.listPriceTables.useQuery();
}

export function useCreatePriceTable() {
  const utils = trpc.useUtils();
  return trpc.quotes.createPriceTable.useMutation({
    onSuccess: () => {
      utils.quotes.listPriceTables.invalidate();
    },
  });
}

export function useUpdatePriceTable() {
  const utils = trpc.useUtils();
  return trpc.quotes.updatePriceTable.useMutation({
    onSuccess: () => {
      utils.quotes.listPriceTables.invalidate();
    },
  });
}

export function useDeletePriceTable() {
  const utils = trpc.useUtils();
  return trpc.quotes.deletePriceTable.useMutation({
    onSuccess: () => {
      utils.quotes.listPriceTables.invalidate();
    },
  });
}

// ─── ARTWORKS (F2-02) ──────────────────────────────────────────────────────

export function useArtworksByOp(opId: string | undefined) {
  return trpc.artworks.list.useQuery(
    { opId: opId ?? "" },
    { enabled: !!opId },
  );
}

export function useArtworksByQuote(quoteId: string | undefined) {
  return trpc.artworks.list.useQuery(
    { quoteId: quoteId ?? "" },
    { enabled: !!quoteId },
  );
}

export function useCreateArtwork() {
  const utils = trpc.useUtils();
  return trpc.artworks.create.useMutation({
    onSuccess: () => {
      utils.artworks.list.invalidate();
    },
  });
}

export function useDeleteArtwork() {
  const utils = trpc.useUtils();
  return trpc.artworks.delete.useMutation({
    onSuccess: () => {
      utils.artworks.list.invalidate();
    },
  });
}

export function useRequestArtworkUploadUrl() {
  return trpc.artworks.requestUploadUrl.useMutation();
}

export function useArtworkApprovalHistory(artworkId: string | undefined) {
  return trpc.artworks.getApprovalHistory.useQuery(
    { artworkId: artworkId ?? "" },
    { enabled: !!artworkId },
  );
}

// ─── INVENTORY (F2-07) ─────────────────────────────────────────────────────

export function useInventory(input?: {
  search?: string;
  onlyLowStock?: boolean;
  includeInactive?: boolean;
}) {
  return trpc.inventory.list.useQuery(input);
}

export function useInventoryItem(id: string | undefined) {
  return trpc.inventory.getById.useQuery(
    { id: id ?? "" },
    { enabled: !!id },
  );
}

export function useInventoryMovements(inventoryId: string | undefined) {
  return trpc.inventory.listMovements.useQuery(
    { inventoryId: inventoryId ?? "" },
    { enabled: !!inventoryId },
  );
}

export function useLowStockAlert() {
  return trpc.inventory.lowStockAlert.useQuery();
}

export function useCreateInventory() {
  const utils = trpc.useUtils();
  return trpc.inventory.create.useMutation({
    onSuccess: () => {
      utils.inventory.list.invalidate();
      utils.inventory.lowStockAlert.invalidate();
    },
  });
}

export function useUpdateInventory() {
  const utils = trpc.useUtils();
  return trpc.inventory.update.useMutation({
    onMutate: async (input) => {
      await utils.inventory.list.cancel();
      const previous = utils.inventory.list.getData(undefined);
      utils.inventory.list.setData(undefined, (old) =>
        old
          ? old.map((i) => (i.id === input.id ? { ...i, ...input } : i))
          : old,
      );
      return { previous };
    },
    onError: (_err, _input, ctx) => {
      if (ctx?.previous)
        utils.inventory.list.setData(undefined, ctx.previous);
    },
    onSettled: () => {
      utils.inventory.list.invalidate();
      utils.inventory.lowStockAlert.invalidate();
    },
  });
}

export function useDeleteInventory() {
  const utils = trpc.useUtils();
  return trpc.inventory.softDelete.useMutation({
    onSuccess: () => {
      utils.inventory.list.invalidate();
      utils.inventory.lowStockAlert.invalidate();
    },
  });
}

export function useRestoreInventory() {
  const utils = trpc.useUtils();
  return trpc.inventory.restore.useMutation({
    onSuccess: () => {
      utils.inventory.list.invalidate();
      utils.inventory.lowStockAlert.invalidate();
    },
  });
}

export function useRecordMovement() {
  const utils = trpc.useUtils();
  return trpc.inventory.recordMovement.useMutation({
    onSuccess: (_data, vars) => {
      utils.inventory.list.invalidate();
      utils.inventory.lowStockAlert.invalidate();
      utils.inventory.getById.invalidate({ id: vars.inventoryId });
      utils.inventory.listMovements.invalidate({
        inventoryId: vars.inventoryId,
      });
    },
  });
}

// ─── WHATSAPP (F2-08) ──────────────────────────────────────────────────────

export function useWhatsappMessages(input?: { customerId?: string }) {
  return trpc.whatsapp.messages.list.useQuery(input);
}

export function useSendWhatsappCustom() {
  const utils = trpc.useUtils();
  return trpc.whatsapp.sendCustom.useMutation({
    onSuccess: () => {
      utils.whatsapp.messages.list.invalidate();
    },
  });
}

export function useSendWhatsappTemplate() {
  const utils = trpc.useUtils();
  return trpc.whatsapp.sendTemplate.useMutation({
    onSuccess: () => {
      utils.whatsapp.messages.list.invalidate();
    },
  });
}

// ─── VEHICLES (Mecânica) ────────────────────────────────────────────────────

export function useVehicles() {
  return trpc.vehicles.list.useQuery();
}

export function useVehiclesByCustomer(customerId: string | undefined) {
  return trpc.vehicles.list.useQuery(
    { customerId: customerId ?? "" },
    { enabled: !!customerId },
  );
}

export function useVehicle(id: string | undefined) {
  return trpc.vehicles.getById.useQuery(
    { id: id ?? "" },
    { enabled: !!id },
  );
}

export function useVehicleHistory(vehicleId: string | undefined) {
  return trpc.vehicles.getHistory.useQuery(
    { vehicleId: vehicleId ?? "" },
    { enabled: !!vehicleId },
  );
}

export function useVehicleSearch(q: string) {
  return trpc.vehicles.searchByPlaca.useQuery({ q });
}

export function useCreateVehicle() {
  const utils = trpc.useUtils();
  return trpc.vehicles.create.useMutation({
    onSuccess: () => {
      utils.vehicles.list.invalidate();
    },
  });
}

export function useUpdateVehicle() {
  const utils = trpc.useUtils();
  return trpc.vehicles.update.useMutation({
    onMutate: async (input) => {
      await utils.vehicles.list.cancel();
      const previous = utils.vehicles.list.getData();
      utils.vehicles.list.setData(undefined, (old) =>
        old ? old.map((v) => (v.id === input.id ? { ...v, ...input } : v)) : old,
      );
      return { previous };
    },
    onError: (_err, _input, ctx) => {
      if (ctx?.previous) utils.vehicles.list.setData(undefined, ctx.previous);
    },
    onSettled: () => {
      utils.vehicles.list.invalidate();
    },
  });
}

export function useDeleteVehicle() {
  const utils = trpc.useUtils();
  return trpc.vehicles.delete.useMutation({
    onSuccess: () => {
      utils.vehicles.list.invalidate();
    },
  });
}

// ─── SERVICE ORDERS (Mecânica) ──────────────────────────────────────────────

export type ServiceOrderStatus =
  | "recepcao"
  | "diagnostico"
  | "orcamento"
  | "aguardando_aprovacao"
  | "aguardando_pecas"
  | "em_execucao"
  | "revisao"
  | "pronto"
  | "entregue";

export function useServiceOrders(opts?: {
  status?: ServiceOrderStatus;
  vehicleId?: string;
  customerId?: string;
}) {
  return trpc.serviceOrders.list.useQuery(opts);
}

export function useServiceOrder(id: string | undefined) {
  return trpc.serviceOrders.getById.useQuery(
    { id: id ?? "" },
    { enabled: !!id },
  );
}

export function useCreateServiceOrder() {
  const utils = trpc.useUtils();
  return trpc.serviceOrders.create.useMutation({
    onSuccess: () => {
      utils.serviceOrders.list.invalidate();
    },
  });
}

export function useUpdateServiceOrder() {
  const utils = trpc.useUtils();
  return trpc.serviceOrders.update.useMutation({
    onMutate: async (input) => {
      await utils.serviceOrders.list.cancel();
      const previous = utils.serviceOrders.list.getData();
      utils.serviceOrders.list.setData(undefined, (old) => {
        if (!old) return old;
        // Items shape on the input (validated zod) differs from the wire row
        // shape returned by the server; drop `items` from the optimistic patch
        // so types align — the invalidate in onSettled refreshes truth.
        const { items: _items, ...rest } = input;
        return old.map((s) => (s.id === input.id ? { ...s, ...rest } : s));
      });
      return { previous };
    },
    onError: (_err, _input, ctx) => {
      if (ctx?.previous) utils.serviceOrders.list.setData(undefined, ctx.previous);
    },
    onSettled: (_data, _err, input) => {
      utils.serviceOrders.list.invalidate();
      if (input?.id) utils.serviceOrders.getById.invalidate({ id: input.id });
    },
  });
}

export function useDeleteServiceOrder() {
  const utils = trpc.useUtils();
  return trpc.serviceOrders.delete.useMutation({
    onSuccess: () => {
      utils.serviceOrders.list.invalidate();
    },
  });
}

export function useAdvanceServiceOrderStatus() {
  const utils = trpc.useUtils();
  return trpc.serviceOrders.advanceStatus.useMutation({
    onSuccess: (_data, input) => {
      utils.serviceOrders.list.invalidate();
      utils.serviceOrders.getById.invalidate({ id: input.id });
    },
  });
}

export function useGenerateOSLink() {
  return trpc.serviceOrders.generateOrcamentoLink.useMutation();
}

export function useAddOSItem() {
  const utils = trpc.useUtils();
  return trpc.serviceOrders.addItem.useMutation({
    onSuccess: (_data, input) => {
      utils.serviceOrders.list.invalidate();
      utils.serviceOrders.getById.invalidate({ id: input.serviceOrderId });
    },
  });
}

export function useRemoveOSItem() {
  const utils = trpc.useUtils();
  return trpc.serviceOrders.removeItem.useMutation({
    onSuccess: () => {
      utils.serviceOrders.list.invalidate();
      utils.serviceOrders.getById.invalidate();
    },
  });
}

export function useAddOSPhoto() {
  const utils = trpc.useUtils();
  return trpc.serviceOrders.addPhoto.useMutation({
    onSuccess: (_data, input) => {
      utils.serviceOrders.getById.invalidate({ id: input.serviceOrderId });
    },
  });
}

export function useRemoveOSPhoto() {
  const utils = trpc.useUtils();
  return trpc.serviceOrders.removePhoto.useMutation({
    onSuccess: () => {
      utils.serviceOrders.getById.invalidate();
    },
  });
}

// ─── REPORTS (F3-02) ───────────────────────────────────────────────────────

export function useDre(input: { from?: string; to?: string }) {
  return trpc.reports.dre.useQuery(input);
}

export function useReportVendas(input: {
  from?: string;
  to?: string;
  groupBy?: "dia" | "semana" | "mes";
}) {
  return trpc.reports.vendas.useQuery(input);
}

export function useReportProducao(input: { from?: string; to?: string }) {
  return trpc.reports.producao.useQuery(input);
}

export function useReportEstoque() {
  return trpc.reports.estoque.useQuery();
}

export function useExportReportPdf() {
  return trpc.reports.exportPdf.useMutation();
}

export function useExportReportExcel() {
  return trpc.reports.exportExcel.useMutation();
}


// ─── AI (F3-06 / F3-07) ───────────────────────────────────────────

export function useSuggestDiagnosis() {
  return trpc.ai.suggestDiagnosis.useMutation();
}

export function useSuggestPricing() {
  return trpc.ai.suggestPricing.useMutation();
}

// ─── FISCAL (F3-01) ────────────────────────────────────────────────────────

export function useFiscalStatus() {
  return trpc.fiscal.status.useQuery();
}

export function useCompanySettings() {
  return trpc.fiscal.companySettings.get.useQuery();
}

export function useUpsertCompanySettings() {
  const utils = trpc.useUtils();
  return trpc.fiscal.companySettings.upsert.useMutation({
    onSuccess: () => {
      utils.fiscal.companySettings.get.invalidate();
    },
  });
}

export type FiscalDocumentFilters = {
  status?: "rascunho" | "processando" | "autorizado" | "cancelado" | "rejeitado";
  tipo?: "NFe" | "NFCe" | "NFSe";
  referenciaTipo?: string;
  referenciaId?: string;
};

export function useFiscalDocuments(filters?: FiscalDocumentFilters) {
  return trpc.fiscal.documents.list.useQuery(filters);
}

export function useFiscalDocument(id: string | undefined) {
  return trpc.fiscal.documents.getById.useQuery(
    { id: id ?? "" },
    { enabled: !!id },
  );
}

export function useEmitFiscalDocument() {
  const utils = trpc.useUtils();
  return trpc.fiscal.documents.emit.useMutation({
    onSuccess: () => {
      utils.fiscal.documents.list.invalidate();
    },
  });
}

export function useConsultFiscalStatus() {
  const utils = trpc.useUtils();
  return trpc.fiscal.documents.consultStatus.useMutation({
    onSuccess: (_data, input) => {
      utils.fiscal.documents.list.invalidate();
      utils.fiscal.documents.getById.invalidate({ id: input.id });
    },
  });
}

export function useCancelFiscalDocument() {
  const utils = trpc.useUtils();
  return trpc.fiscal.documents.cancel.useMutation({
    onSuccess: (_data, input) => {
      utils.fiscal.documents.list.invalidate();
      utils.fiscal.documents.getById.invalidate({ id: input.id });
    },
  });
}

export function useFiscalDownloadUrl(id: string | undefined) {
  return trpc.fiscal.documents.downloadUrl.useQuery(
    { id: id ?? "" },
    { enabled: !!id },
  );
}

// ─── PAGAMENTOS (F3-03 Asaas) ─────────────────────────────────────────────

export function useAsaasStatus() {
  return trpc.payments.status.useQuery();
}

type PaymentFilters = {
  status?: "pendente" | "pago" | "vencido" | "cancelado" | "estornado" | "falhou";
  customerId?: string;
  referenciaTipo?: "pedido" | "os" | "quote";
  referenciaId?: string;
};

export function usePayments(filters?: PaymentFilters) {
  return trpc.payments.list.useQuery(filters);
}

export function usePayment(id: string | undefined) {
  return trpc.payments.getById.useQuery(
    { id: id ?? "" },
    { enabled: !!id },
  );
}

export function useCreatePayment() {
  const utils = trpc.useUtils();
  return trpc.payments.create.useMutation({
    onSuccess: () => {
      utils.payments.list.invalidate();
    },
  });
}

export function useCancelPayment() {
  const utils = trpc.useUtils();
  return trpc.payments.cancel.useMutation({
    onSuccess: () => {
      utils.payments.list.invalidate();
      utils.payments.getById.invalidate();
    },
  });
}

export function useRefreshPaymentStatus() {
  const utils = trpc.useUtils();
  return trpc.payments.refreshStatus.useMutation({
    onSuccess: () => {
      utils.payments.list.invalidate();
      utils.payments.getById.invalidate();
    },
  });
}

// ─── COMPANIES (F3-08 Multiempresa) ────────────────────────────────────────

export function useCompanies() {
  return trpc.companies.list.useQuery();
}

export function useCurrentCompany() {
  return trpc.companies.current.useQuery();
}

export function useSwitchCompany() {
  const utils = trpc.useUtils();
  return trpc.companies.switch.useMutation({
    onSuccess: () => {
      // Active company changed — every tenant-scoped query is stale.
      utils.invalidate();
    },
  });
}

export function useCreateCompany() {
  const utils = trpc.useUtils();
  return trpc.companies.create.useMutation({
    onSuccess: () => {
      utils.companies.list.invalidate();
    },
  });
}

export function useUpdateCompany() {
  const utils = trpc.useUtils();
  return trpc.companies.update.useMutation({
    onSuccess: () => {
      utils.companies.list.invalidate();
      utils.companies.current.invalidate();
    },
  });
}

export function useDeleteCompany() {
  const utils = trpc.useUtils();
  return trpc.companies.delete.useMutation({
    onSuccess: () => {
      utils.companies.list.invalidate();
    },
  });
}
