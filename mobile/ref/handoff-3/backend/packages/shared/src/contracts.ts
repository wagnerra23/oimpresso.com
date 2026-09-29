// ──────────────────────────────────────────────────────────────
// Contratos compartilhados (client + server) — Zod + tipos.
// Fonte única de verdade dos formatos de entrada/saída.
// PT-BR nos rótulos; validação no servidor antes de qualquer escrita.
// ──────────────────────────────────────────────────────────────
import { z } from "zod";

export const etapaPedido = z.enum(["orc", "aprov", "prod", "entrega", "done"]);
export type EtapaPedido = z.infer<typeof etapaPedido>;

export const ETAPA_LABEL: Record<EtapaPedido, string> = {
  orc: "Orçamento",
  aprov: "Aguardando aprovação",
  prod: "Em produção",
  entrega: "Saiu para entrega",
  done: "Concluído",
};

// Pipeline canônico — avanço derivado, nunca digitado.
export const ETAPA_ORDER: EtapaPedido[] = ["orc", "aprov", "prod", "entrega", "done"];
export function proximaEtapa(atual: EtapaPedido): EtapaPedido {
  const i = ETAPA_ORDER.indexOf(atual);
  return i >= 0 && i < ETAPA_ORDER.length - 1 ? ETAPA_ORDER[i + 1] : atual;
}

export const papelPessoa = z.enum(["cliente", "fornecedor", "funcionario", "transportadora"]);

// Item estruturado (NÃO string) — pré-requisito de fiscal/estoque/custo.
export const pedidoItemInput = z.object({
  produtoId: z.string().uuid().nullable(),
  qtd: z.number().positive(),
  unidade: z.string().min(1),
  atributos: z.record(z.unknown()).optional(),     // formato, gramatura, cores, acabamento
  precoUnitCents: z.number().int().nonnegative(),
});
export type PedidoItemInput = z.infer<typeof pedidoItemInput>;

export const criarPedidoInput = z.object({
  clienteId: z.string().uuid(),                    // VÍNCULO POR ID
  itens: z.array(pedidoItemInput).min(1, "Adicione ao menos um item"),
  prazoAt: z.string().datetime().nullable(),       // ISO timestamptz — data real
  pagamento: z.string().optional(),
  obs: z.string().optional(),
  idempotencyKey: z.string().uuid(),               // sobrevive a retry/offline
});
export type CriarPedidoInput = z.infer<typeof criarPedidoInput>;

export const avancarEtapaInput = z.object({
  pedidoId: z.string().uuid(),
  expectedVersion: z.number().int().nonnegative(), // concorrência otimista
});
export type AvancarEtapaInput = z.infer<typeof avancarEtapaInput>;
