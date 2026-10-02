/**
 * Mapas de status de domínio → OiStatus (Onda 3). Substitui os hex fixos em
 * orcamentos.tsx, fiscal.tsx, pagamentos.tsx e estoque/[id].tsx. Um lugar só, igual ao os-status.ts.
 */
import type { OiStatusVariant } from "@/components/oi";

type Tone = { label: string; variant: OiStatusVariant };

export const ORCAMENTO_STATUS: Record<string, Tone> = {
  rascunho: { label: "Rascunho", variant: "neutral" },
  enviado: { label: "Enviado", variant: "info" },
  aprovado: { label: "Aprovado", variant: "ok" },
  rejeitado: { label: "Rejeitado", variant: "danger" },
  convertido: { label: "Convertido", variant: "accent" },
};

export const FISCAL_STATUS: Record<string, Tone> = {
  rascunho: { label: "Rascunho", variant: "neutral" },
  processando: { label: "Processando", variant: "info" },
  autorizado: { label: "Autorizada", variant: "ok" },
  cancelado: { label: "Cancelada", variant: "neutral" },
  rejeitado: { label: "Rejeitada", variant: "danger" },
};

export const PAGAMENTO_STATUS: Record<string, Tone> = {
  pendente: { label: "Pendente", variant: "warn" },
  pago: { label: "Pago", variant: "ok" },
  vencido: { label: "Vencido", variant: "danger" },
  cancelado: { label: "Cancelado", variant: "neutral" },
  estornado: { label: "Estornado", variant: "neutral" },
  falhou: { label: "Falhou", variant: "danger" },
};

export const MOVIMENTO_ESTOQUE: Record<string, Tone & { sign: string }> = {
  entrada: { label: "Entrada", variant: "ok", sign: "+" },
  saida: { label: "Saída", variant: "danger", sign: "−" },
  perda: { label: "Perda", variant: "danger", sign: "−" },
  ajuste: { label: "Ajuste", variant: "info", sign: "=" },
};

/** Nível de estoque → variante (antes colorForStock em hex). */
export function estoqueTone(qtd: number, min: number): OiStatusVariant {
  if (qtd <= min) return "danger";
  if (qtd <= min * 2) return "warn";
  return "ok";
}

export const statusOf = (map: Record<string, Tone>, s: string): Tone => map[s] ?? { label: s, variant: "neutral" };
