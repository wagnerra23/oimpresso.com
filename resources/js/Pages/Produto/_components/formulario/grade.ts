// Grade do produto variável no Novo produto (UC-PCAD-32). Decisão do [F] em 2026-11-17 (SDD §10.6):
// o nome vem do modelo de variação da empresa (Cadastros → Variações) e o produto tem uma variação.
//
// Cada valor da grade tem o preço do produto simples (precos.ts): a mesma conta do Blade, linha a
// linha. O envio segue o que `ProductUtil::createVariableProductVariations` lê do
// `variable_product_form_part.blade.php`: `product_variation[0]` com o modelo e os valores, e os
// cinco preços de cada valor no formato que o `Util::num_uf` não lê errado (vírgula, sem milhar).
import { paraNumUf } from '@/Lib/numberPtBR';
import { CASAS_PRECO, arredondar, precoInicial, somarPercentual } from './precos';
import type { PrecoSimples } from './precos';

/** Modelo de variação da empresa, como o `ProductController::modelosDeVariacao` manda. */
export interface ModeloVariacao {
  id: number;
  nome: string;
  valores: { id: number; nome: string }[];
}

export type FormatoSubSku = 'with_out_variation' | 'with_variation';

export interface ValorDaGrade {
  nome: string;
  preco: PrecoSimples;
}

export interface Grade {
  modeloId: number | null;
  formato: FormatoSubSku;
  valores: ValorDaGrade[];
}

export function valorNovo(margemPadrao: number): ValorDaGrade {
  return { nome: '', preco: precoInicial(margemPadrao) };
}

export function gradeInicial(margemPadrao: number): Grade {
  return { modeloId: null, formato: 'with_out_variation', valores: [valorNovo(margemPadrao)] };
}

/** O valor do modelo com o mesmo nome (sem diferença de maiúsculas), como o servidor casa. */
export function valorDoModelo(nome: string, modelo: ModeloVariacao | undefined): number | null {
  const alvo = nome.trim().toLowerCase();
  if (alvo === '' || !modelo) return null;
  return modelo.valores.find((v) => v.nome.trim().toLowerCase() === alvo)?.id ?? null;
}

/** Valores que vão ao servidor: os que têm nome. */
export function valoresPreenchidos(grade: Grade): ValorDaGrade[] {
  return grade.valores.filter((v) => v.nome.trim() !== '');
}

/**
 * Prévia do sub-SKU, com a regra do `ProductUtil::generateSubSku`: SKU do produto + número da
 * variação (com hífen em Code 128 e Code 39) ou SKU + nome do valor sem espaço nem símbolo. Sem SKU
 * digitado o servidor gera o SKU ao salvar, então não há prévia.
 */
export function previaSubSku(sku: string, tipoCodigo: string, formato: FormatoSubSku, nome: string, posicao: number): string {
  const base = sku.trim();
  if (base === '' || nome.trim() === '') return '';
  if (formato === 'with_variation') return base + nome.replace(/[^a-zA-Z0-9]/g, '');
  return ['C128', 'C39'].includes(tipoCodigo) ? `${base}-${posicao}` : `${base}${posicao}`;
}

/** Por que a grade não pode ir ao servidor, ou null. */
export function erroDaGrade(grade: Grade): string | null {
  if (grade.modeloId === null) return 'Escolha o modelo da variação.';
  if (valoresPreenchidos(grade).length === 0) return 'Adicione ao menos um valor da variação.';
  return null;
}

/** O que o produto variável manda além do formulário: o formato do sub-SKU e a grade. */
export function camposDaGrade(grade: Grade, modelo: ModeloVariacao | undefined, aliquota: number) {
  const texto = (n: number) => paraNumUf(arredondar(n), CASAS_PRECO);
  return {
    sku_type: grade.formato,
    product_variation: [
      {
        variation_template_id: grade.modeloId ?? '',
        variations: valoresPreenchidos(grade).map((v) => ({
          value: v.nome.trim(),
          variation_value_id: valorDoModelo(v.nome, modelo) ?? '',
          sub_sku: '',
          default_purchase_price: texto(v.preco.compra),
          dpp_inc_tax: texto(somarPercentual(v.preco.compra, aliquota)),
          profit_percent: texto(v.preco.margem),
          default_sell_price: texto(v.preco.venda),
          sell_price_inc_tax: texto(somarPercentual(v.preco.venda, aliquota)),
        })),
      },
    ],
  };
}
