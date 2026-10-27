// Regras puras do envio da compra (Create/Edit → PurchaseController@store/update).
//
// Por que existe: o store passa total, desconto, imposto, frete e despesas pelo `num_uf` pt-BR,
// que lê "." seguido de 3 dígitos como milhar (incidente 2026-06-05). Antes de 2026-10-27 a tela
// mandava o float cru: 1,5 × 2,33 = 3.495 chegava como "3.495" e gravava 3495. Aqui todo valor
// sai por `paraNumUf(valor, 2)` — vírgula decimal, sem milhar, 2 casas.
//
// As despesas adicionais seguem a Blade (`public/js/purchase.js`, cálculo do grand_total): até 4
// pares nome/valor, somados ao total final.

import { paraNumUf } from '@/Lib/numberPtBR';

export interface DespesaAdicional {
  nome: string;
  valor: number;
}

export const MAX_DESPESAS = 4;

export const despesasVazias = (): DespesaAdicional[] =>
  Array.from({ length: MAX_DESPESAS }, () => ({ nome: '', valor: 0 }));

export const centavos = (v: number): number => Math.round((Number.isFinite(v) ? v : 0) * 100) / 100;

export const somaDespesas = (despesas: DespesaAdicional[]): number =>
  centavos(despesas.reduce((acc, d) => acc + (d.valor || 0), 0));

/**
 * Os 8 campos que o store lê (`additional_expense_key_N` / `additional_expense_value_N`).
 * Valor zerado vai como '' — o store só grava o par quando o valor não é vazio.
 */
export function camposDespesas(despesas: DespesaAdicional[]): Record<string, string> {
  const campos: Record<string, string> = {};
  for (let i = 0; i < MAX_DESPESAS; i += 1) {
    const d = despesas[i] ?? { nome: '', valor: 0 };
    const temValor = centavos(d.valor) !== 0;
    campos[`additional_expense_key_${i + 1}`] = temValor ? d.nome.trim() : '';
    campos[`additional_expense_value_${i + 1}`] = temValor ? paraNumUf(d.valor, 2) : '';
  }
  return campos;
}

/** Valor monetário no formato que o `num_uf` lê sem ambiguidade. */
export const valorParaEnvio = (v: number): string => paraNumUf(centavos(v), 2);

/** Campos numéricos da linha que o createOrUpdatePurchaseLines passa pelo num_uf. */
const CAMPOS_LINHA = ['quantity', 'pp_without_discount', 'discount_percent', 'purchase_price', 'purchase_price_inc_tax', 'item_tax'] as const;

/**
 * A linha no formato do num_uf: vírgula decimal, até 4 casas (custo unitário e quantidade em kg/m
 * podem ter 3). Antes de 2026-10-27 ia o float cru, e "10.125" gravava 10125.
 */
export function linhaParaEnvio<T extends Partial<Record<(typeof CAMPOS_LINHA)[number], number | string | null>>>(linha: T): T {
  const saida: Record<string, unknown> = { ...linha };
  for (const campo of CAMPOS_LINHA) {
    const v = linha[campo];
    if (typeof v === 'number') saida[campo] = paraNumUf(v, 4);
  }
  return saida as T;
}
