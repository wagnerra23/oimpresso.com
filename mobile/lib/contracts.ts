/**
 * Contratos canônicos do cliente — espelho de
 * `ref/handoff-3/backend/packages/shared/src/contracts.ts` (fonte única do
 * Wagner). Define o pipeline canônico de pedido (5 etapas), papéis e os shapes
 * de criação/avanço. PT-BR nos rótulos.
 *
 * O servidor TiDB atual ainda usa um enum legado de 4 estados
 * (`novo/aprovado/execucao/entregue`). As funções `statusToEtapa` /
 * `etapaToStatus` fazem o mapeamento na borda de dados (erp-queries), de modo
 * que a UI fala SEMPRE em etapas canônicas.
 */
import { z } from "zod";

// ── Pipeline canônico de pedido (5 etapas, derivado — nunca digitado) ──────
export const etapaPedido = z.enum(["orc", "aprov", "prod", "entrega", "done"]);
export type EtapaPedido = z.infer<typeof etapaPedido>;

export const ETAPA_LABEL: Record<EtapaPedido, string> = {
  orc: "Orçamento",
  aprov: "Aguardando aprovação",
  prod: "Em produção",
  entrega: "Saiu para entrega",
  done: "Concluído",
};

/** Rótulo curto para steppers/chips. */
export const ETAPA_SHORT: Record<EtapaPedido, string> = {
  orc: "Orçamento",
  aprov: "Aprovação",
  prod: "Produção",
  entrega: "Entrega",
  done: "Concluído",
};

export const ETAPA_ORDER: EtapaPedido[] = ["orc", "aprov", "prod", "entrega", "done"];

export function proximaEtapa(atual: EtapaPedido): EtapaPedido {
  const i = ETAPA_ORDER.indexOf(atual);
  return i >= 0 && i < ETAPA_ORDER.length - 1 ? ETAPA_ORDER[i + 1]! : atual;
}

export function etapaIndex(etapa: EtapaPedido): number {
  return Math.max(0, ETAPA_ORDER.indexOf(etapa));
}

// ── Papéis de pessoa (multi-papel) ─────────────────────────────────────────
export const papelPessoa = z.enum([
  "cliente",
  "fornecedor",
  "funcionario",
  "transportadora",
]);
export type PapelPessoa = z.infer<typeof papelPessoa>;

// ── Item estruturado (NÃO string) — base de fiscal/estoque/custo ───────────
export const pedidoItemInput = z.object({
  produtoId: z.string().nullable(),
  qtd: z.number().positive(),
  unidade: z.string().min(1),
  /** formato, gramatura, cores, acabamento… */
  atributos: z.record(z.string(), z.unknown()).optional(),
  precoUnitCents: z.number().int().nonnegative(),
});
export type PedidoItemInput = z.infer<typeof pedidoItemInput>;

export const criarPedidoInput = z.object({
  clienteId: z.string(), // VÍNCULO POR ID
  itens: z.array(pedidoItemInput).min(1, "Adicione ao menos um item"),
  prazoAt: z.string().nullable(), // ISO — data real
  pagamento: z.string().optional(),
  obs: z.string().optional(),
  idempotencyKey: z.string(), // sobrevive a retry/offline
});
export type CriarPedidoInput = z.infer<typeof criarPedidoInput>;

export const avancarEtapaInput = z.object({
  pedidoId: z.string(),
  expectedVersion: z.number().int().nonnegative(), // concorrência otimista
});
export type AvancarEtapaInput = z.infer<typeof avancarEtapaInput>;

// ── Mapeamento legacy (servidor TiDB) ↔ canônico ───────────────────────────
/** Enum legado do servidor atual. */
export type LegacyPedidoStatus = "novo" | "aprovado" | "execucao" | "entregue";

/** Status legado → etapa canônica (para a UI exibir o pipeline de 5). */
export function statusToEtapa(status: LegacyPedidoStatus): EtapaPedido {
  switch (status) {
    case "novo":
      return "orc";
    case "aprovado":
      return "prod";
    case "execucao":
      return "entrega";
    case "entregue":
      return "done";
    default:
      return "orc";
  }
}

/**
 * Etapa canônica → status legado (para gravar no servidor atual ao avançar).
 * As etapas intermediárias `aprov` colapsam no status anterior pois o servidor
 * não tem estado dedicado; `done`/`entrega` mapeiam para os legados finais.
 */
export function etapaToStatus(etapa: EtapaPedido): LegacyPedidoStatus {
  switch (etapa) {
    case "orc":
    case "aprov":
      return "novo";
    case "prod":
      return "aprovado";
    case "entrega":
      return "execucao";
    case "done":
      return "entregue";
    default:
      return "novo";
  }
}
