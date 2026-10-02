// Preço unitário de uma linha vinda de `/products/list` (ProductUtil::filterProduct).
//
// Com `price_group` na busca, o backend devolve `variation_group_price` (fixed → o valor;
// percentage → % × sell_price_inc_tax). Sem preço de grupo para a variação, o campo vem
// `null` e vale o preço base (`selling_price` = variations.sell_price_inc_tax).
//
// Regra única do Create.tsx: usada ao ADICIONAR produto e ao TROCAR o grupo. Antes do
// conserto (2026-10-01) só a troca de grupo aplicava o preço do grupo; o produto
// adicionado com um grupo já escolhido entrava pelo preço base.
//
// Preço de grupo 0 (alinhado ao Blade em 2026-10-02, medido no MySQL): o `getProductRow`
// aplica o grupo quando `!empty($price_inc_tax)`. FIXO 0 chega como "0.0000" e vale (linha
// em 0); PERCENTUAL 0 sai de calc_percentage como float 0 e não vale (linha no preço base).
// A regra daqui não precisou mudar: o `filterProduct` devolve NULL no percentual que dá 0,
// e NULL já é "sem preço de grupo". O contrato está em BuscaProdutoPrecoDeGrupoContratoTest.
export interface LinhaDeBusca {
  selling_price?: number | string | null;
  variation_group_price?: number | string | null;
}

export function precoDaBusca(linha: LinhaDeBusca, fallback = 0): number {
  if (linha.variation_group_price !== undefined && linha.variation_group_price !== null) {
    return Number(linha.variation_group_price);
  }
  return Number(linha.selling_price ?? fallback);
}
