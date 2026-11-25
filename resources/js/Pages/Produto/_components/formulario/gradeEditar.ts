// Grade do produto variável na tela Editar (UC-PEDIT-32): o estado e o que vai ao servidor.
//
// Cada linha é uma variação gravada, com o preço do produto simples (precos.ts, a conta do Blade).
// O envio segue a regra do preço do simples (UC-PEDIT-16): só vai a linha que a pessoa mexeu, porque
// devolver o gravado arredondado a 2 casas reescreveria custo/venda de 4 casas sem ninguém pedir
// (REGRA MESTRE de valor). Trocar o imposto refaz os "com imposto" de todas, então aí vão todas.
//
// O servidor lê `variacoes_editadas[<id da variação>]` (ProductController::gravarGradeEditada): a
// linha que não vem fica como está e nenhuma variação é apagada. Não é o `product_variation_edit` do
// Blade, que reescreve a grade inteira e apaga a variação que falta.
import { paraNumUf } from '@/Lib/numberPtBR';
import { CASAS_PRECO, arredondar, somarPercentual } from './precos';
import type { PrecoSimples } from './precos';

/** Uma variação gravada, como o `ProductController::gradeDoProdutoVariavel` manda. */
export interface LinhaGradeGravada {
  variacaoId: number;
  valor: string;
  subSku: string;
  compra: number;
  margem: number;
  venda: number;
}

export interface GradeGravada {
  modelo: string;
  linhas: LinhaGradeGravada[];
}

export interface LinhaGradeEditada {
  variacaoId: number;
  subSku: string;
  preco: PrecoSimples;
}

export interface CamposDaLinhaServidor {
  dpp: string;
  dpp_inc_tax: string;
  profit_percent: string;
  dsp: string;
  dsp_inc_tax: string;
  sub_sku?: string;
}

export function gradeEditadaInicial(gravada: GradeGravada): LinhaGradeEditada[] {
  return gravada.linhas.map((l) => ({
    variacaoId: l.variacaoId,
    subSku: l.subSku,
    preco: { compra: l.compra, margem: l.margem, venda: l.venda },
  }));
}

function precoMudou(g: LinhaGradeGravada, a: LinhaGradeEditada): boolean {
  return a.preco.compra !== g.compra || a.preco.margem !== g.margem || a.preco.venda !== g.venda;
}

/** Os cinco preços da linha como o servidor lê: vírgula decimal e sem milhar (`paraNumUf`). */
function precosDaLinha(p: PrecoSimples, aliquota: number): CamposDaLinhaServidor {
  return {
    dpp: paraNumUf(arredondar(p.compra), CASAS_PRECO),
    dpp_inc_tax: paraNumUf(arredondar(somarPercentual(p.compra, aliquota)), CASAS_PRECO),
    profit_percent: paraNumUf(arredondar(p.margem), CASAS_PRECO),
    dsp: paraNumUf(arredondar(p.venda), CASAS_PRECO),
    dsp_inc_tax: paraNumUf(arredondar(somarPercentual(p.venda, aliquota)), CASAS_PRECO),
  };
}

/**
 * O que a tela Editar manda da grade. Nada, se a pessoa não mexeu em linha nenhuma nem no imposto.
 * Mexeu só no sub-SKU: vai o sub-SKU, sem preço (o servidor preserva o preço que não vem). Sub-SKU
 * apagado não vai: o servidor não grava sub-SKU vazio, como no Blade.
 */
export function camposDaGradeEditada(
  gravada: GradeGravada | null,
  atual: LinhaGradeEditada[] | null,
  aliquota: number,
  impostoMudou: boolean,
): { variacoes_editadas?: Record<number, Partial<CamposDaLinhaServidor>> } {
  if (!gravada || !atual) return {};
  const envio: Record<number, Partial<CamposDaLinhaServidor>> = {};
  for (const a of atual) {
    const g = gravada.linhas.find((l) => l.variacaoId === a.variacaoId);
    if (!g) continue;
    const linha: Partial<CamposDaLinhaServidor> = precoMudou(g, a) || impostoMudou ? precosDaLinha(a.preco, aliquota) : {};
    const subSku = a.subSku.trim();
    if (subSku !== '' && subSku !== g.subSku) linha.sub_sku = subSku;
    if (Object.keys(linha).length > 0) envio[a.variacaoId] = linha;
  }
  return Object.keys(envio).length > 0 ? { variacoes_editadas: envio } : {};
}
