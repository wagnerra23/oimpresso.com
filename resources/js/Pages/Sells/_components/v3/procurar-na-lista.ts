// "Procurar na lista de produtos" da Nova venda V3 (UC-V413 · UC-PIDX-53).
//
// A venda não guarda rascunho: sair da página perde o que foi montado. Por isso a lista de
// produtos abre numa ABA NOVA, e o produto escolhido lá volta por mensagem entre abas
// (`@/Lib/produto-escolhido`).
//
// TIER 0 — VALOR (memory/proibicoes.md · REGRA MESTRE)
// O produto volta só com id, SKU e nome. A venda NÃO usa preço vindo de fora: põe o SKU na própria
// busca (`/products/list` com local e tabela da venda, a de sempre) e, se sobrar exatamente uma
// variação daquele produto, abre o "Lançar item" com ESSE resultado da busca — o mesmo objeto que
// o clique no resultado abriria. Preço, unidade e estoque saem do mesmo caminho do clique.

import type { ProdutoCatalogo } from './LancarItem';

/** URL da lista de produtos numa aba nova, com o caminho de volta, o rótulo, o item e o canal. */
export function urlDaListaDeProdutos(volta: string, para: string, canal: string): string {
  const q = new URLSearchParams({ de: volta, de_rotulo: 'Nova venda', canal });
  if (para.trim()) q.set('para', para.trim());
  return `/products?${q.toString()}`;
}

/**
 * O resultado da busca a abrir no "Lançar item": o ÚNICO achado daquele produto. Com mais de uma
 * variação (ou nenhuma), `null` — os resultados ficam na tela para o operador escolher.
 */
export function achadoDoProdutoEscolhido(
  achados: ProdutoCatalogo[],
  produtoIdDoSku: (sku: string) => number | undefined,
  produtoId: number,
): ProdutoCatalogo | null {
  const doProduto = achados.filter((p) => produtoIdDoSku(p.sku) === produtoId);
  return doProduto.length === 1 ? (doProduto[0] ?? null) : null;
}
