// ──────────────────────────────────────────────────────────────
// domain/inventory · conversão de unidade.
// Tudo é normalizado para a unidade de ESTOQUE do produto antes de
// movimentar. Conversões dimensionais (rolo→m², folha→m²) usam o
// fator do cadastro; conversões 1:N (milheiro→peça) são conhecidas.
// ──────────────────────────────────────────────────────────────
import type { ProdutoEstoque, Unidade } from "./types";

const FATORES_CONHECIDOS: Partial<Record<`${Unidade}->${Unidade}`, number>> = {
  "milheiro->peca": 1000,
  "peca->milheiro": 1 / 1000,
};

/**
 * Converte `qtd` da unidade `de` para a unidade de ESTOQUE do produto.
 * - compra → estoque usa fatorCompraParaEstoque (ex.: 1 rolo = 50 m²)
 * - mesma unidade = identidade
 * - fatores conhecidos (milheiro↔peça)
 */
export function paraEstoque(produto: ProdutoEstoque, qtd: number, de: Unidade): number {
  const alvo = produto.unidadeEstoque;
  if (de === alvo) return qtd;
  if (de === produto.unidadeCompra) return qtd * produto.fatorCompraParaEstoque;

  const f = FATORES_CONHECIDOS[`${de}->${alvo}`];
  if (f != null) return qtd * f;

  throw new Error(`Conversão não definida: ${de} → ${alvo} (produto ${produto.nome})`);
}

/** Quantidade de estoque consumida ao vender/baixar `qtdVenda`, com perda. */
export function consumoComPerda(produto: ProdutoEstoque, qtdVenda: number, unidadeVenda: Unidade): number {
  const base = paraEstoque(produto, qtdVenda, unidadeVenda);
  return base * (1 + produto.perdaPct / 100);
}
