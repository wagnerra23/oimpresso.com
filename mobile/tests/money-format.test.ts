import { describe, expect, it } from "vitest";

import { BRL, BRLcompact, buildTimeline, formatDuration, formatPrazo, formatTimestamp } from "../lib/format";
import {
  applyDiscountPct,
  formatBRL,
  formatBRLcompact,
  gerarParcelasCents,
  mulCents,
  parseBRLToCents,
  reaisToCents,
  sumCents,
} from "../lib/money";

// `toLocaleString` separa "R$" do número com espaço não quebrável (U+00A0).
const plain = (s: string) => s.replace(/ /g, " ");

describe("money — centavos inteiros", () => {
  it("converte reais para centavos arredondando uma única vez", () => {
    expect(reaisToCents(12.34)).toBe(1234);
    expect(reaisToCents(0.1 + 0.2)).toBe(30);
    expect(reaisToCents(19.999)).toBe(2000);
  });

  it("lê valores digitados no formato brasileiro", () => {
    expect(parseBRLToCents("1.234,56")).toBe(123456);
    expect(parseBRLToCents(" 45,9 ")).toBe(4590);
    expect(parseBRLToCents("10")).toBe(1000);
    expect(() => parseBRLToCents("abc")).toThrow(/inválido/);
  });

  it("soma, multiplica e aplica desconto sem erro de ponto flutuante", () => {
    expect(sumCents(10, 20, 30)).toBe(60);
    expect(sumCents()).toBe(0);
    expect(mulCents(333, 3)).toBe(999);
    expect(mulCents(4500, 2.5)).toBe(11250);
    expect(applyDiscountPct(10000, 10)).toBe(9000);
    expect(applyDiscountPct(999, 33)).toBe(669);
  });

  it("parcela sem perder centavo — a última parcela absorve a sobra", () => {
    const parcelas = gerarParcelasCents(10000, 3);
    expect(parcelas).toEqual([3333, 3333, 3334]);
    expect(sumCents(...parcelas)).toBe(10000);
    for (const [total, n] of [[1, 4], [99999, 7], [123456, 12]] as const) {
      const p = gerarParcelasCents(total, n);
      expect(p).toHaveLength(n);
      expect(sumCents(...p)).toBe(total);
    }
  });

  it("formata centavos como moeda", () => {
    expect(plain(formatBRL(123456))).toBe("R$ 1.234,56");
    expect(plain(formatBRL(0))).toBe("R$ 0,00");
    expect(formatBRLcompact(1_250_00)).toBe("R$ 1,3k");
    expect(formatBRLcompact(3_400_000_00)).toBe("R$ 3,4M");
    expect(plain(formatBRLcompact(999_99))).toBe("R$ 999,99");
  });
});

describe("format — valores em reais", () => {
  it("BRL trata nulo, NaN e infinito como zero", () => {
    expect(plain(BRL(1234.5))).toBe("R$ 1.234,50");
    expect(plain(BRL(null))).toBe("R$ 0,00");
    expect(plain(BRL(undefined))).toBe("R$ 0,00");
    expect(plain(BRL(Number.NaN))).toBe("R$ 0,00");
    expect(plain(BRL(Infinity))).toBe("R$ 0,00");
  });

  it("BRLcompact abrevia milhares e milhões, inclusive negativos", () => {
    expect(BRLcompact(1200)).toBe("R$ 1,2k");
    expect(BRLcompact(-2500)).toBe("R$ -2,5k");
    expect(BRLcompact(1_300_000)).toBe("R$ 1,3M");
    expect(plain(BRLcompact(950))).toBe("R$ 950,00");
  });
});

describe("format — prazos e tempo", () => {
  const now = new Date("2026-09-29T12:00:00");
  const inMin = (m: number) => new Date(now.getTime() + m * 60_000).toISOString();

  it("formatDuration usa min, h e dias", () => {
    expect(formatDuration(0)).toBe("0min");
    expect(formatDuration(40)).toBe("40min");
    expect(formatDuration(70)).toBe("1h10");
    expect(formatDuration(120)).toBe("2h");
    expect(formatDuration(60 * 24 * 3 + 5)).toBe("3d");
    expect(formatDuration(-15)).toBe("0min");
  });

  it("formatPrazo pinta atrasado e < 1h de vermelho, < 24h de amarelo", () => {
    expect(formatPrazo(null, now)).toMatchObject({ tone: "neutral", label: "Sem prazo" });
    expect(formatPrazo(inMin(-40), now)).toMatchObject({ tone: "danger", label: "Atrasado 40min" });
    expect(formatPrazo(inMin(30), now)).toMatchObject({ tone: "danger", label: "Vence em 30min" });
    expect(formatPrazo(inMin(70), now)).toMatchObject({ tone: "warn", label: "Vence em 1h10" });
    expect(formatPrazo(inMin(60 * 50), now)).toMatchObject({ tone: "ok", label: "Em 2d" });
  });

  it("formatTimestamp diz Hoje / Ontem / dd/mm", () => {
    expect(formatTimestamp(undefined, now)).toBe("—");
    expect(formatTimestamp(new Date("2026-09-29T08:10:00"), now)).toBe("Hoje 08:10");
    expect(formatTimestamp(new Date("2026-09-28T14:30:00"), now)).toBe("Ontem 14:30");
    expect(formatTimestamp(new Date("2026-05-21T09:00:00"), now)).toBe("21/05 09:00");
  });

  it("buildTimeline marca etapas feitas, a atual e as futuras", () => {
    const tl = buildTimeline({
      pipeline: ["Fila", "Impressão", "Acabamento", "Entrega"],
      currentStage: "Acabamento",
      openedAt: "2026-09-29T08:00:00.000Z",
      operatorPerStage: { Impressão: "Jorge" },
    });
    expect(tl.map((e) => [e.stage, e.done, e.current])).toEqual([
      ["Fila", true, false],
      ["Impressão", true, false],
      ["Acabamento", false, true],
      ["Entrega", false, false],
    ]);
    expect(tl[0].at).toBe("2026-09-29T08:00:00.000Z");
    expect(tl[1].at).toBe("2026-09-29T09:30:00.000Z");
    expect(tl[1].operator).toBe("Jorge");
    expect(tl[3].at).toBeUndefined();
  });
});
