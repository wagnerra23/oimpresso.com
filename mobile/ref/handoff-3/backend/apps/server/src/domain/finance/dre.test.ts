import { describe, it, expect } from "vitest";
import { calcularDRE, projetarFluxo, type LancamentoFuturo } from "./dre";

const d = (s: string) => new Date(s + "T12:00:00Z");

describe("DRE gerencial", () => {
  it("calcula resultado e margens em cascata", () => {
    const dre = calcularDRE({
      receitaBrutaCents: 100000, deducoesCents: 6000, custoCents: 54000, despesasCents: 20000,
    });
    expect(dre.receitaLiquidaCents).toBe(94000);
    expect(dre.lucroBrutoCents).toBe(40000);     // 94000 − 54000
    expect(dre.lucroOperacionalCents).toBe(20000); // 40000 − 20000
    expect(dre.margemBrutaPct).toBe(40);
    expect(dre.margemLiquidaPct).toBe(20);
  });
  it("não divide por zero sem receita", () => {
    const dre = calcularDRE({ receitaBrutaCents: 0, deducoesCents: 0, custoCents: 0, despesasCents: 5000 });
    expect(dre.margemLiquidaPct).toBe(0);
    expect(dre.lucroOperacionalCents).toBe(-5000);
  });
});

describe("fluxo de caixa projetado", () => {
  const lanc: LancamentoFuturo[] = [
    { vencimentoAt: d("2026-06-20"), valorCents: 30000, sentido: "entrada" },
    { vencimentoAt: d("2026-06-22"), valorCents: 50000, sentido: "saida" },
    { vencimentoAt: d("2026-06-25"), valorCents: 40000, sentido: "entrada" },
  ];

  it("acumula saldo dia a dia a partir do inicial", () => {
    const r = projetarFluxo(10000, lanc, 10, d("2026-06-18"));
    const last = r.linha[r.linha.length - 1];
    expect(last.saldoAcumuladoCents).toBe(10000 + 30000 - 50000 + 40000); // 30000
  });

  it("detecta quando o caixa fica negativo (alerta)", () => {
    // inicial baixo: após a saída de 50000 o caixa fura
    const r = projetarFluxo(5000, lanc, 10, d("2026-06-18"));
    expect(r.ficaNegativo).toBe(true);
    expect(r.menorSaldoCents).toBeLessThan(0); // 5000+30000−50000 = −15000
  });

  it("permanece positivo com caixa inicial suficiente", () => {
    const r = projetarFluxo(100000, lanc, 10, d("2026-06-18"));
    expect(r.ficaNegativo).toBe(false);
  });
});
