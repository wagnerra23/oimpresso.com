// ──────────────────────────────────────────────────────────────
// domain/finance · títulos a receber/pagar — lógica PURA.
// Resolve v2 §2 (financeiro ligado à OS por STRING): aqui o título
// referencia pedido/pessoa por ID e o saldo é DERIVADO das baixas,
// nunca um campo digitado. Dinheiro em centavos (sem float).
// ──────────────────────────────────────────────────────────────
import { sumCents } from "@oimpresso/shared/money";

export type StatusTitulo = "aberto" | "parcial" | "quitado" | "vencido";

export interface Parcela {
  numero: number;
  vencimentoAt: Date;
  valorCents: number;
}

/**
 * Gera N parcelas distribuindo o total em centavos SEM perder centavo:
 * a última parcela absorve a sobra do arredondamento.
 */
export function gerarParcelas(totalCents: number, n: number, primeiraVenc: Date, intervaloDias = 30): Parcela[] {
  if (n < 1) throw new Error("Número de parcelas inválido");
  const base = Math.floor(totalCents / n);
  const resto = totalCents - base * n;
  return Array.from({ length: n }, (_, i) => {
    const venc = new Date(primeiraVenc);
    venc.setDate(venc.getDate() + i * intervaloDias);
    return {
      numero: i + 1,
      vencimentoAt: venc,
      // a última parcela recebe o resto, mantendo a soma exata
      valorCents: base + (i === n - 1 ? resto : 0),
    };
  });
}

/** Saldo em aberto = valor do título − soma das baixas (derivado). */
export function saldoEmAberto(valorCents: number, baixasCents: number[]): number {
  return valorCents - sumCents(...baixasCents);
}

/**
 * Status derivado do saldo e do vencimento — nunca digitado.
 * agora é injetável para testabilidade.
 */
export function statusTitulo(
  valorCents: number, baixasCents: number[], vencimentoAt: Date, agora = new Date(),
): StatusTitulo {
  const saldo = saldoEmAberto(valorCents, baixasCents);
  if (saldo <= 0) return "quitado";
  if (baixasCents.length > 0) return "parcial";
  if (vencimentoAt.getTime() < agora.getTime()) return "vencido";
  return "aberto";
}

export interface FaixaAging {
  faixa: "a_vencer" | "1-30" | "31-60" | "61-90" | "90+";
  valorCents: number;
}

/** Aging de recebíveis: agrupa saldos em aberto por faixa de atraso. */
export function aging(
  titulos: { saldoCents: number; vencimentoAt: Date }[], agora = new Date(),
): FaixaAging[] {
  const faixas: Record<FaixaAging["faixa"], number> = { a_vencer: 0, "1-30": 0, "31-60": 0, "61-90": 0, "90+": 0 };
  const DIA = 86400_000;
  for (const t of titulos) {
    if (t.saldoCents <= 0) continue;
    const atraso = Math.floor((agora.getTime() - t.vencimentoAt.getTime()) / DIA);
    const f: FaixaAging["faixa"] =
      atraso <= 0 ? "a_vencer" : atraso <= 30 ? "1-30" : atraso <= 60 ? "31-60" : atraso <= 90 ? "61-90" : "90+";
    faixas[f] += t.saldoCents;
  }
  return (Object.keys(faixas) as FaixaAging["faixa"][]).map((faixa) => ({ faixa, valorCents: faixas[faixa] }));
}
