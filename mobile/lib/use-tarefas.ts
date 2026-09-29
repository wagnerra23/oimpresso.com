/**
 * useTarefas — aggregates open work items from real sources into a unified
 * Tarefa shape used by `app/tarefas/[id].tsx`.
 *
 * Mobile app has no `tarefas` table; the inbox is derived:
 *  - Pedidos com status `novo` → origem "OS" (aprovar arte/orçamento)
 *  - Pedidos com status `aprovado` → origem "OS" (liberar produção)
 *  - Pedidos com status `execucao` → origem "MFG" (acompanhar produção)
 *  - Payments com status `pendente` ou `vencido` → origem "FIN"
 *  - Inventory low-stock alerts → origem "MFG"
 *
 * IDs use the format `${origem}-${entityId}` so the detail screen can
 * find the source again. `viewer` maps to the design v3 viewer kind.
 */
import { useMemo } from "react";

import {
  useLowStockAlert,
  usePayments,
  usePedidos,
} from "@/lib/erp-queries";

export type TarefaOrigem = "OS" | "MFG" | "FIN" | "CRM" | "PNT";
export type TarefaViewer =
  | "OsAprovarArte"
  | "FinBoleto"
  | "CrmContato"
  | "MfgLiberar"
  | "OsEntrega"
  | "PntJustificar"
  | "CrmOrcamento"
  | "FinConciliar"
  | "Generic";

export type Tarefa = {
  id: string;
  origem: TarefaOrigem;
  viewer: TarefaViewer;
  title: string;
  subtitle: string;
  cliente: string | null;
  when: string;
  prazo?: string;
  valor?: number;
  urgente: boolean;
  /** Optional reference back to source entity. */
  refKind: "pedido" | "payment" | "stock";
  refId: string;
};

function relative(date?: string | Date | null): string {
  if (!date) return "—";
  const d = typeof date === "string" ? new Date(date) : date;
  if (Number.isNaN(d.getTime())) return "—";
  const diff = Math.max(0, Date.now() - d.getTime());
  const mins = Math.floor(diff / 60000);
  if (mins < 1) return "agora";
  if (mins < 60) return `${mins}m`;
  const hrs = Math.floor(mins / 60);
  if (hrs < 24) return `${hrs}h`;
  const days = Math.floor(hrs / 24);
  return `${days}d`;
}

function urgentByISO(iso?: string | null): boolean {
  if (!iso) return false;
  const d = new Date(iso);
  if (Number.isNaN(d.getTime())) return false;
  return d.getTime() - Date.now() < 24 * 60 * 60 * 1000;
}

export function useTarefas() {
  const pedidosQ = usePedidos();
  const paymentsQ = usePayments();
  const lowStockQ = useLowStockAlert();

  const isLoading =
    pedidosQ.isLoading || paymentsQ.isLoading || lowStockQ.isLoading;

  const tarefas = useMemo<Tarefa[]>(() => {
    const list: Tarefa[] = [];

    for (const p of pedidosQ.data ?? []) {
      if (p.status === "entregue") continue;
      const viewer: TarefaViewer =
        p.status === "novo"
          ? "OsAprovarArte"
          : p.status === "aprovado"
            ? "MfgLiberar"
            : "OsEntrega";
      list.push({
        id: `OS-${p.id}`,
        origem: p.status === "execucao" ? "MFG" : "OS",
        viewer,
        title:
          p.status === "novo"
            ? `Aprovar arte: ${p.produto}`
            : p.status === "aprovado"
              ? `Liberar produção: ${p.produto}`
              : `Acompanhar entrega: ${p.produto}`,
        subtitle: `${p.cliente} · R$ ${p.valor.toLocaleString("pt-BR")}`,
        cliente: p.cliente,
        when: relative(p.data),
        valor: p.valor,
        urgente: p.status === "novo",
        refKind: "pedido",
        refId: p.id,
      });
    }

    for (const pay of paymentsQ.data ?? []) {
      if (pay.status !== "pendente" && pay.status !== "vencido") continue;
      list.push({
        id: `FIN-${pay.id}`,
        origem: "FIN",
        viewer: "FinBoleto",
        title:
          pay.status === "vencido"
            ? `Cobrança vencida — ${pay.customer?.nome ?? "cliente"}`
            : `Cobrança pendente — ${pay.customer?.nome ?? "cliente"}`,
        subtitle: `${pay.descricao ?? "Boleto"} · R$ ${pay.valor.toLocaleString("pt-BR")}`,
        cliente: pay.customer?.nome ?? null,
        when: relative(pay.vencimento ?? pay.updatedAt),
        prazo: pay.vencimento ?? undefined,
        valor: pay.valor,
        urgente: pay.status === "vencido" || urgentByISO(pay.vencimento),
        refKind: "payment",
        refId: pay.id,
      });
    }

    for (const item of lowStockQ.data ?? []) {
      list.push({
        id: `MFG-${item.id}`,
        origem: "MFG",
        viewer: "Generic",
        title: `Estoque baixo: ${item.nome}`,
        subtitle: `${item.quantidade} disponível · mín ${item.estoqueMinimo}`,
        cliente: null,
        when: "agora",
        urgente: item.quantidade <= 0,
        refKind: "stock",
        refId: item.id,
      });
    }

    return list;
  }, [pedidosQ.data, paymentsQ.data, lowStockQ.data]);

  const byId = (id: string): Tarefa | undefined =>
    tarefas.find((t) => t.id === id);

  return { tarefas, isLoading, byId };
}
