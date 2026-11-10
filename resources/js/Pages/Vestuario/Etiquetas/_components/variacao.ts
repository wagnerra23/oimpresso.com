export interface VariacaoEncontrada {
  product_id: number;
  variation_id: number;
  name: string;
  type?: string;
  variation?: string;
  sub_sku?: string;
}

// `DUMMY` é o nome da variação de produto sem grade no UltimatePOS.
export function rotuloDaVariacao(v: VariacaoEncontrada): string {
  return v.variation && v.variation !== 'DUMMY' ? v.variation : '';
}
