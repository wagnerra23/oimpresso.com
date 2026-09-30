import { describe, it, expect } from "vitest";
import { gerarParcelas, saldoEmAberto, statusTitulo, aging } from "./titulos";

const d = (s: string) => new Date(s + "T12:00:00Z");

describe("gerarParcelas — sem perder centavo", () => {
  it("divide 3× e a última absorve a sobra", () => {
    const p = gerarParcelas(10000, 3, d("2026-01-10"));
    expect(p.map((x) => x.valorCents)).toEqual([3333, 3333, 3334]); // soma = 10000
    expect(p.reduce((s, x) => s + x.valorCents, 0)).toBe(10000);
  });
  it("vencimentos a cada 30 dias", () => {
    const p = gerarParcelas(30000, 3, d("2026-01-10"));
    expect(p[0].vencimentoAt.getUTCDate()).toBe(10);
    expect(p[1].vencimentoAt.getUTCMonth()).toBe(1); // fevereiro
  });
});

describe("saldo e status DERIVADOS (nunca digitados)", () => {
  it("saldo = valor − baixas", () => {
    expect(saldoEmAberto(10000, [3000, 2000])).toBe(5000);
  });
  it("quitado quando baixas cobrem o total", () => {
    expect(statusTitulo(10000, [10000], d("2026-01-01"), d("2026-02-01"))).toBe("quitado");
  });
  it("parcial com baixa incompleta", () => {
    expect(statusTitulo(10000, [4000], d("2026-12-01"), d("2026-06-17"))).toBe("parcial");
  });
  it("vencido quando passou do prazo sem baixa", () => {
    expect(statusTitulo(10000, [], d("2026-01-01"), d("2026-06-17"))).toBe("vencido");
  });
  it("aberto quando ainda no prazo", () => {
    expect(statusTitulo(10000, [], d("2026-12-01"), d("2026-06-17"))).toBe("aberto");
  });
});

describe("aging de recebíveis", () => {
  it("classifica saldos por faixa de atraso", () => {
    const hoje = d("2026-06-17");
    const r = aging([
      { saldoCents: 1000, vencimentoAt: d("2026-07-01") }, // a vencer
      { saldoCents: 2000, vencimentoAt: d("2026-06-01") }, // 16 dias → 1-30
      { saldoCents: 4000, vencimentoAt: d("2026-04-01") }, // ~77 dias → 61-90
      { saldoCents: 8000, vencimentoAt: d("2026-01-01") }, // >90
    ], hoje);
    const get = (f: string) => r.find((x) => x.faixa === f)!.valorCents;
    expect(get("a_vencer")).toBe(1000);
    expect(get("1-30")).toBe(2000);
    expect(get("61-90")).toBe(4000);
    expect(get("90+")).toBe(8000);
  });
  it("ignora saldos quitados", () => {
    const r = aging([{ saldoCents: 0, vencimentoAt: d("2026-01-01") }], d("2026-06-17"));
    expect(r.every((f) => f.valorCents === 0)).toBe(true);
  });
});
