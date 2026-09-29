import { describe, it, expect } from "vitest";
import { paraEstoque, consumoComPerda } from "./conversion";
import { aplicarEntrada, baixarParaOS, sugestaoCompra, type EstadoEstoque } from "./inventoryService";
import type { ProdutoEstoque } from "./types";

// Lona em rolo: compra rolo, estoca e vende em m². 1 rolo = 50 m². Perda 8%.
const lona: ProdutoEstoque = {
  id: "p1", nome: "Lona 440g", unidadeCompra: "rolo", unidadeEstoque: "m2", unidadeVenda: "m2",
  fatorCompraParaEstoque: 50, perdaPct: 8, custoMedioCents: 0,
};
// Cartão: compra/estoca em folha, vende em milheiro (→ peça não se aplica; folha direto)
const cartao: ProdutoEstoque = {
  id: "p2", nome: "Couché 300g", unidadeCompra: "folha", unidadeEstoque: "folha", unidadeVenda: "folha",
  fatorCompraParaEstoque: 1, perdaPct: 3, custoMedioCents: 0,
};

describe("conversão de unidade", () => {
  it("converte rolo → m² pelo fator do cadastro", () => {
    expect(paraEstoque(lona, 2, "rolo")).toBe(100); // 2 rolos = 100 m²
  });
  it("mesma unidade é identidade", () => {
    expect(paraEstoque(lona, 7.5, "m2")).toBe(7.5);
  });
  it("aplica perda no consumo de venda", () => {
    // 10 m² vendidos + 8% perda = 10.8 m² de estoque
    expect(consumoComPerda(lona, 10, "m2")).toBeCloseTo(10.8, 5);
  });
  it("lança em conversão não definida", () => {
    expect(() => paraEstoque(lona, 1, "kg")).toThrow();
  });
});

describe("custo médio ponderado", () => {
  it("recalcula a média ao entrar lotes de custos diferentes", () => {
    let e: EstadoEstoque = { saldo: 0, custoMedioCents: 0, lotes: [] };
    e = aplicarEntrada(e, { produtoId: "p1", tipo: "entrada", quantidade: 50, unidade: "m2", custoUnitCents: 1200, lote: "L1" });
    e = aplicarEntrada(e, { produtoId: "p1", tipo: "entrada", quantidade: 50, unidade: "m2", custoUnitCents: 1600, lote: "L2" });
    expect(e.saldo).toBe(100);
    expect(e.custoMedioCents).toBe(1400); // (50×1200 + 50×1600)/100
    expect(e.lotes).toHaveLength(2);
  });
});

describe("baixa dimensional pela OS", () => {
  it("baixa m² com perda e devolve o custo consumido", () => {
    let e: EstadoEstoque = { saldo: 0, custoMedioCents: 0, lotes: [] };
    e = aplicarEntrada(e, { produtoId: "p1", tipo: "entrada", quantidade: 100, unidade: "m2", custoUnitCents: 1400 });
    // 3 banners de 2 m² = 6 m²; +8% = 6.48 m²
    const r = baixarParaOS(e, lona, 6, "m2");
    expect(r.consumo).toBeCloseTo(6.48, 5);
    expect(r.custoConsumoCents).toBe(Math.round(6.48 * 1400)); // 9072
    expect(r.estado.saldo).toBeCloseTo(93.52, 5);
    expect(r.saldoNegativo).toBe(false);
  });

  it("sinaliza saldo negativo (caminho infeliz)", () => {
    const e: EstadoEstoque = { saldo: 5, custoMedioCents: 1000, lotes: [] };
    const r = baixarParaOS(e, lona, 10, "m2");
    expect(r.saldoNegativo).toBe(true);
  });
});

describe("sugestão de compra", () => {
  it("arredonda para unidades de compra inteiras", () => {
    const e: EstadoEstoque = { saldo: 10, custoMedioCents: 1400, lotes: [] };
    // mínimo 80 m², faltam 70, rolo = 50 m² → 2 rolos
    expect(sugestaoCompra(e, lona, 80)).toBe(2);
  });
  it("não sugere se acima do mínimo", () => {
    const e: EstadoEstoque = { saldo: 100, custoMedioCents: 1400, lotes: [] };
    expect(sugestaoCompra(e, lona, 80)).toBe(0);
  });
});
