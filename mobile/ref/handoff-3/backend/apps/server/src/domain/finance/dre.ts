// ──────────────────────────────────────────────────────────────
// domain/finance · DRE gerencial e fluxo de caixa projetado.
// Lógica PURA sobre títulos/baixas (já ligados à OS por id). Resolve
// v1 §14 (sem DRE/fluxo): o dono enxerga RESULTADO e o que vai
// entrar/sair — não descobre a saúde do negócio pelo extrato.
// Tudo em centavos (sem float).
// ──────────────────────────────────────────────────────────────
import { sumCents } from "@oimpresso/shared/money";

// ===== DRE gerencial (competência) =====
export interface LinhaResultado {
  receitaBrutaCents: number;
  deducoesCents: number;     // impostos sobre venda (ISS/ICMS), devoluções
  custoCents: number;        // CPV: material + produção (do custeio da OS)
  despesasCents: number;     // despesas operacionais fixas/variáveis
}

export interface DRE {
  receitaBrutaCents: number;
  deducoesCents: number;
  receitaLiquidaCents: number;
  custoCents: number;
  lucroBrutoCents: number;
  margemBrutaPct: number;
  despesasCents: number;
  lucroOperacionalCents: number;
  margemLiquidaPct: number;
}

/** Monta a DRE a partir dos agregados do período. */
export function calcularDRE(l: LinhaResultado): DRE {
  const receitaLiquida = l.receitaBrutaCents - l.deducoesCents;
  const lucroBruto = receitaLiquida - l.custoCents;
  const lucroOperacional = lucroBruto - l.despesasCents;
  const pct = (n: number) => (l.receitaBrutaCents > 0 ? Math.round((n / l.receitaBrutaCents) * 1000) / 10 : 0);
  return {
    receitaBrutaCents: l.receitaBrutaCents,
    deducoesCents: l.deducoesCents,
    receitaLiquidaCents: receitaLiquida,
    custoCents: l.custoCents,
    lucroBrutoCents: lucroBruto,
    margemBrutaPct: pct(lucroBruto),
    despesasCents: l.despesasCents,
    lucroOperacionalCents: lucroOperacional,
    margemLiquidaPct: pct(lucroOperacional),
  };
}

// ===== Fluxo de caixa projetado (regime de caixa, por vencimento) =====
export interface LancamentoFuturo {
  vencimentoAt: Date;
  valorCents: number;        // sempre positivo
  sentido: "entrada" | "saida";
}

export interface DiaProjetado {
  data: string;              // ISO (YYYY-MM-DD)
  entradasCents: number;
  saidasCents: number;
  saldoDiaCents: number;     // entradas − saídas do dia
  saldoAcumuladoCents: number;
}

const iso = (d: Date) => d.toISOString().slice(0, 10);

/**
 * Projeta o saldo acumulado dia a dia a partir de um saldo inicial,
 * dentro da janela [hoje, hoje+dias]. Marca quando o caixa fica negativo.
 */
export function projetarFluxo(
  saldoInicialCents: number, lancamentos: LancamentoFuturo[], dias = 30, hoje = new Date(),
): { linha: DiaProjetado[]; menorSaldoCents: number; ficaNegativo: boolean } {
  const inicio = new Date(iso(hoje) + "T00:00:00Z");
  const linha: DiaProjetado[] = [];
  let acumulado = saldoInicialCents;
  let menor = saldoInicialCents;

  for (let i = 0; i <= dias; i++) {
    const dia = new Date(inicio);
    dia.setUTCDate(inicio.getUTCDate() + i);
    const k = iso(dia);
    const doDia = lancamentos.filter((l) => iso(l.vencimentoAt) === k);
    const entradas = sumCents(...doDia.filter((l) => l.sentido === "entrada").map((l) => l.valorCents));
    const saidas = sumCents(...doDia.filter((l) => l.sentido === "saida").map((l) => l.valorCents));
    const saldoDia = entradas - saidas;
    acumulado += saldoDia;
    if (acumulado < menor) menor = acumulado;
    linha.push({ data: k, entradasCents: entradas, saidasCents: saidas, saldoDiaCents: saldoDia, saldoAcumuladoCents: acumulado });
  }
  return { linha, menorSaldoCents: menor, ficaNegativo: menor < 0 };
}
