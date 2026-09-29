import { describe, it, expect } from "vitest";
import { folhasParaItem, planoDeConsumo, type ItemFicha, type SuporteFolha } from "./consumeStock";

const SRA3: SuporteFolha = { larguraMm: 320, alturaMm: 450, margemMm: 10 };

describe("consumo de estoque pela produção", () => {
  it("folder A4 5.000 consome ~2.625 folhas (2 por folha + 5% refugo)", () => {
    const item: ItemFicha = { materialId: "cou150", larguraMm: 210, alturaMm: 297, tiragem: 5000 };
    expect(folhasParaItem(item, SRA3, 5)).toBe(2625);
  });

  it("bate com a imposição do orçamento (mesmo número de folhas)", () => {
    // 1000 cartões 9×5: 24/folha → 42 folhas úteis → 44 c/ 5%
    const cartao: ItemFicha = { materialId: "cou300", larguraMm: 90, alturaMm: 50, tiragem: 1000 };
    const folhas = folhasParaItem(cartao, SRA3, 5);
    expect(folhas).toBeGreaterThan(0);
    expect(folhas).toBe(Math.round(Math.ceil(1000 / 24) * 1.05));
  });

  it("agrega itens do mesmo material em uma baixa só", () => {
    const itens: ItemFicha[] = [
      { materialId: "cou150", larguraMm: 210, alturaMm: 297, tiragem: 5000 },
      { materialId: "cou150", larguraMm: 148, alturaMm: 210, tiragem: 2500 },
      { materialId: "cou300", larguraMm: 90, alturaMm: 50, tiragem: 1000 },
    ];
    const plano = planoDeConsumo(itens, SRA3, 5);
    expect(plano).toHaveLength(2); // cou150 (somado) + cou300
    const cou150 = plano.find((p) => p.materialId === "cou150")!;
    expect(cou150.folhas).toBe(
      folhasParaItem(itens[0], SRA3, 5) + folhasParaItem(itens[1], SRA3, 5),
    );
  });
});
