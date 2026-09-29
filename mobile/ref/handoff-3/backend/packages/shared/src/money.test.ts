import { describe, it, expect } from "vitest";
import {
  reaisToCents, parseBRLToCents, sumCents, mulCents,
  applyDiscountPct, formatBRL,
} from "./money";

describe("money — dinheiro em centavos (sem float)", () => {
  it("converte reais → centavos arredondando uma vez", () => {
    expect(reaisToCents(248)).toBe(24800);
    expect(reaisToCents(0.1 + 0.2)).toBe(30); // 0.30000000000000004 → 30
  });

  it("parseia string PT-BR", () => {
    expect(parseBRLToCents("1.234,56")).toBe(123456);
    expect(parseBRLToCents("0,99")).toBe(99);
    expect(() => parseBRLToCents("abc")).toThrow();
  });

  it("soma sem erro de ponto flutuante", () => {
    // 0,10 + 0,20 em reais quebraria; em centavos é exato
    expect(sumCents(10, 20)).toBe(30);
    const cem = Array.from({ length: 100 }, () => 1); // 100 × R$0,01
    expect(sumCents(...cem)).toBe(100);
  });

  it("multiplica por quantidade e arredonda", () => {
    expect(mulCents(24800, 3)).toBe(74400);
    expect(mulCents(333, 3)).toBe(999);
  });

  it("aplica desconto percentual", () => {
    expect(applyDiscountPct(10000, 10)).toBe(9000);
  });

  it("formata em BRL", () => {
    expect(formatBRL(123456)).toMatch(/1\.234,56/);
  });
});
