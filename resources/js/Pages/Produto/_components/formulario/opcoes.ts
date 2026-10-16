// Opções dos selects do formulário de produto.
//
// O servidor manda algumas listas com uma chave vazia: em produção (medido 2026-10-16),
// `units['']` = "Selecionar" e `taxes['']` = "nenhum". O <SelectItem> do Radix não aceita
// value="" e derruba a tela inteira (Novo e Editar ficavam em branco). Por isso nenhum bloco
// pode mapear a lista crua: passe sempre por `entradasDeOpcoes`.

import type { OptionMap, OptionStringMap } from './tipos';

/** Valor do item "Nenhum" no select de imposto. O Radix não aceita "", então ele vira isto. */
export const IMPOSTO_NENHUM = '__nenhum__';

/** As entradas da lista sem a chave vazia, que é placeholder do servidor e não opção. */
export function entradasDeOpcoes(mapa: OptionMap | OptionStringMap): Array<[string, string]> {
  return Object.entries(mapa as OptionStringMap).filter(([k]) => String(k).trim() !== '');
}

/**
 * O imposto escolhido no select, no formato do formulário. "Nenhum" volta a `''`, que é o que a
 * tela Blade envia para produto sem imposto.
 */
export function impostoDoSelect(valor: string): number | '' {
  return valor === IMPOSTO_NENHUM ? '' : Number(valor);
}

/** O valor do select para o imposto atual do formulário. */
export function selectDoImposto(tax: number | string | null | undefined): string {
  return tax === '' || tax === null || tax === undefined ? IMPOSTO_NENHUM : String(tax);
}
