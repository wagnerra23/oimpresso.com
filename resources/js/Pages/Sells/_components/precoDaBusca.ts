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
// ⚠️ Diferença conhecida com o Blade: `pos.js` e `getProductRow` tratam preço de grupo 0
// como "sem preço de grupo" (teste por truthy / `!empty`). Aqui 0 é preço válido — é a
// regra que o `handlePriceGroupChange` e o dropdown já usavam. Medido em prod 2026-10-01:
// 0 de 3305 linhas de `variation_group_prices` têm preço 0, logo a diferença não altera
// valor hoje. Alinhar é mudança de VALOR (REGRA MESTRE), não arrumação.
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
