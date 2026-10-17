// Preço do produto simples na tela Novo produto: a conta e o que vai ao servidor.
//
// A conta é a do cadastro Blade (public/js/product.js + __add_percent / __get_rate de
// public/js/functions.js), para o produto nascer com o mesmo preço pelos dois caminhos:
//   - compra com imposto = compra + alíquota
//   - venda              = compra + % de lucro   (mudar a compra ou o lucro refaz a venda)
//   - % de lucro         = (venda − compra) / compra · 100   (mudar a venda refaz o lucro; compra 0 → 0)
//   - venda com imposto  = venda + alíquota
//
// O estado guarda só os valores SEM imposto. Os dois "com imposto" são derivados da alíquota do
// imposto escolhido, então trocar o imposto refaz os dois, como no Blade.
//
// A conta é EXATA, como a do Blade (Decimal.js): em `number`, 84,75 + 18% dá 100,00499… e
// arredonda para 100,00, enquanto o Blade grava 100,01. Por isso a conta passa por `exato` (BigInt,
// sem dependência nova), vira `number` como o `Decimal.toNumber()`, e arredonda como o
// `accounting.toFixed` do Blade (`Math.round(v · 100) / 100`). Medido em 2026-10-16 contra o Blade
// rodando as próprias funções de functions.js: 2.725 valores, 0 divergências.

import { paraNumUf } from '@/Lib/numberPtBR';

/** Casas exibidas nos campos de preço, as mesmas que vão ao servidor. */
export const CASAS_PRECO = 2;

export interface PrecoSimples {
  compra: number;
  margem: number;
  venda: number;
}

/** O que `ProductController@store` lê para criar a variação do produto simples. */
export interface CamposPrecoServidor {
  single_dpp: string;
  single_dpp_inc_tax: string;
  profit_percent: string;
  single_dsp: string;
  single_dsp_inc_tax: string;
}

/** Casas da divisão exata: as 20 significativas do Decimal.js do Blade, com folga. */
const CASAS_DIVISAO = 30;

/**
 * Um número finito como decimal exato, `m / 10^e`, a partir da forma CURTA que o JS imprime
 * (`String(0.1)` = "0.1"), que é a que o `new Decimal(n)` do Blade lê. Nunca `toFixed(20)`: ele
 * imprime a expansão binária ("0.10000000000000000555").
 */
function exato(n: number): { m: bigint; e: number } {
  if (!Number.isFinite(n)) return { m: 0n, e: 0 };
  const [base = '0', expoente = '0'] = String(n).toLowerCase().split('e');
  const negativo = base.startsWith('-');
  const [inteira = '0', fracao = ''] = (negativo ? base.slice(1) : base).split('.');
  let m = BigInt(inteira + fracao);
  let e = fracao.length - Number(expoente);
  if (e < 0) {
    m *= 10n ** BigInt(-e);
    e = 0;
  }
  return { m: negativo ? -m : m, e };
}

/** `m / 10^e` como `number` — o arredondamento para o double mais próximo do `Decimal.toNumber()`. */
function paraNumero(m: bigint, e: number): number {
  const negativo = m < 0n;
  const digitos = (negativo ? -m : m).toString().padStart(e + 1, '0');
  const texto = e === 0 ? digitos : `${digitos.slice(0, -e)}.${digitos.slice(-e)}`;
  return Number(negativo ? `-${texto}` : texto);
}

/** Arredondamento da tela e do envio: o `accounting.toFixed` do Blade. */
export function arredondar(valor: number): number {
  const fator = 10 ** CASAS_PRECO;
  return Math.round(valor * fator) / fator;
}

/** `valor` + `percentual`% (o `__add_percent` do Blade), em conta exata. */
export function somarPercentual(valor: number, percentual: number): number {
  const v = exato(valor);
  const p = exato(percentual);
  // v · (100 + p) / 100, tudo inteiro: escala de v + escala de p + 2 (o /100).
  const cem = 100n * 10n ** BigInt(p.e);
  return paraNumero(v.m * (cem + p.m), v.e + p.e + 2);
}

/** % de lucro da venda sobre a compra (o `__get_rate` do Blade). Sem compra não há margem. */
export function margemDe(compra: number, venda: number): number {
  if (compra === 0) return 0;
  const c = exato(compra);
  const v = exato(venda);
  const e = Math.max(c.e, v.e);
  const cm = c.m * 10n ** BigInt(e - c.e);
  const vm = v.m * 10n ** BigInt(e - v.e);
  // (v − c) / c · 100, com CASAS_DIVISAO casas truncadas antes de virar número.
  return paraNumero(((vm - cm) * 100n * 10n ** BigInt(CASAS_DIVISAO)) / cm, CASAS_DIVISAO);
}

export function precoInicial(margemPadrao: number): PrecoSimples {
  return { compra: 0, margem: Number.isFinite(margemPadrao) ? margemPadrao : 0, venda: 0 };
}

export function mudarCompra(p: PrecoSimples, compra: number): PrecoSimples {
  return { ...p, compra, venda: somarPercentual(compra, p.margem) };
}

export function mudarMargem(p: PrecoSimples, margem: number): PrecoSimples {
  return { ...p, margem, venda: somarPercentual(p.compra, margem) };
}

export function mudarVenda(p: PrecoSimples, venda: number): PrecoSimples {
  return { ...p, venda, margem: margemDe(p.compra, venda) };
}

/** Alíquota do imposto escolhido, de `taxAttributes` (`{ [id]: { 'data-rate': n } }`). Sem imposto → 0. */
export function aliquotaDoImposto(
  imposto: number | string,
  atributos: Record<number | string, Record<string, unknown>> | null | undefined,
): number {
  if (imposto === '' || imposto == null || !atributos) return 0;
  const taxa = Number(atributos[imposto]?.['data-rate']);
  return Number.isFinite(taxa) ? taxa : 0;
}

/**
 * Os cinco campos de preço como o servidor lê: vírgula decimal e sem milhar (`paraNumUf`), que o
 * `Util::num_uf` não tem como ler errado. Nunca o número cru: `204.99605` foi lido como milhar em
 * 2026-06-05 (proibicoes.md, REGRA MESTRE de valor).
 */
export function camposDePreco(p: PrecoSimples, aliquota: number): CamposPrecoServidor {
  return {
    single_dpp: paraNumUf(arredondar(p.compra), CASAS_PRECO),
    single_dpp_inc_tax: paraNumUf(arredondar(somarPercentual(p.compra, aliquota)), CASAS_PRECO),
    profit_percent: paraNumUf(arredondar(p.margem), CASAS_PRECO),
    single_dsp: paraNumUf(arredondar(p.venda), CASAS_PRECO),
    single_dsp_inc_tax: paraNumUf(arredondar(somarPercentual(p.venda, aliquota)), CASAS_PRECO),
  };
}
