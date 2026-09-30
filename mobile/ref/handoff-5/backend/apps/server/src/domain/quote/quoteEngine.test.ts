import { describe, it, expect } from "vitest";
import { pecasPorFolha, consumoBobina } from "./imposition";
import { calcularOrcamento } from "./quoteEngine";
import type { FichaTecnica, Suporte, ParametrosCusto } from "./types";

describe("imposição — peças por folha", () => {
  it("cartão 9×5 em SRA3 320×450 dá um aproveitamento alto", () => {
    const n = pecasPorFolha(320, 450, 90, 50, 3, 10);
    expect(n).toBeGreaterThanOrEqual(20);
  });

  it("A4 (210×297) em SRA3 (320×450) cabe 2 por folha", () => {
    const n = pecasPorFolha(320, 450, 210, 297, 3, 10);
    expect(n).toBe(2);
  });

  it("bobina: banner 1000mm em rolo de 1400mm → 1 por linha", () => {
    const c = consumoBobina(1400, 20, 1000, 2000, 0, 3);
    expect(c.pecasPorLinha).toBe(1);
    expect(c.metrosLineares).toBeCloseTo(6, 1);
  });
});

const paramsOffset: ParametrosCusto = {
  digital: false, custoChapaCents: 4500, maquinaCentsPorMin: 250, setupMin: 25,
  velocidadeFolhasPorH: 8000, refugoPct: 5, maoDeObraCentsPorMin: 80,
  acabamentoCentsPorPeca: { laminacao: 8, verniz: 5, dobra: 3 },
};
const MARGEM = 45; // do cadastro do produto

describe("quoteEngine — custeio e regressiva", () => {
  const sra3: Suporte = { tipo: "folha", larguraMm: 320, alturaMm: 450, margemMm: 10, custoCents: 38 };
  const folderBase: FichaTecnica = {
    larguraMm: 210, alturaMm: 297, cores: "4/4", papel: "Couché 150g", gramatura: 150,
    acabamentos: ["dobra"], tiragem: 5000,
  };

  it("orça 5.000 folders A4 4/4 com chapa e total positivo", () => {
    const r = calcularOrcamento(folderBase, sra3, paramsOffset, MARGEM);
    expect(r.pecasPorFolha).toBeGreaterThanOrEqual(2);
    expect(r.custoChapaCents).toBe(8 * 4500); // 4/4 = 8 chapas
    expect(r.precoCents).toBeGreaterThan(r.custoTotalCents);
    expect(r.tempoEstimadoMin).toBeGreaterThan(0);
  });

  it("margem do cadastro define o preço (vendedor não altera)", () => {
    const a = calcularOrcamento(folderBase, sra3, paramsOffset, 45);
    const b = calcularOrcamento(folderBase, sra3, paramsOffset, 60);
    expect(b.precoCents).toBeGreaterThan(a.precoCents); // margem maior → preço maior
    expect(Math.round((1 - a.custoTotalCents / a.precoCents) * 100)).toBe(45);
  });

  it("tiragem maior → preço unitário menor (setup amortizado)", () => {
    const a = calcularOrcamento({ ...folderBase, tiragem: 1000 }, sra3, paramsOffset, MARGEM);
    const b = calcularOrcamento({ ...folderBase, tiragem: 10000 }, sra3, paramsOffset, MARGEM);
    expect(b.precoUnitCents).toBeLessThan(a.precoUnitCents);
  });

  it("digital não cobra chapa", () => {
    const r = calcularOrcamento(folderBase, sra3, { ...paramsOffset, digital: true }, MARGEM);
    expect(r.custoChapaCents).toBe(0);
  });
});
