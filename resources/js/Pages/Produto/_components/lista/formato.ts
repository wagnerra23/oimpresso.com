/**
 * Formatadores da lista de produtos (`/products`).
 *
 * Vieram do `catalogo.ts` da Consulta de Produtos (`/products/unificado`), apagada em 2026-11-19:
 * a lista nova já usava estes dois e só eles sobreviveram à exclusão.
 */

const numero = (n: number) =>
  Number.isInteger(n) ? String(n) : n.toLocaleString('pt-BR', { maximumFractionDigits: 3 });

/** Quantidade com unidade ("12 UN", "1,5 KG"). */
export const qtdComUnidade = (qtd: number, unit: string) => `${numero(qtd)} ${unit}`;

/** Valor em reais com 2 casas ("R$ 1.234,50"). */
export const brl = (n: number) => 'R$ ' + n.toLocaleString('pt-BR', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
