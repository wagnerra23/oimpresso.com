/**
 * Campos de TIPO de venda que o PDV React precisa enviar ao `SellPosController@store`.
 *
 * O PDV é aberto como reparo por `/pos/create?sub_type=repair` (SellPosController@create,
 * que lê `?sub_type=`). `/sells/create?sub_type=repair` (SellController, que lê `?sale_type=`)
 * redireciona para lá — sem isso, o reparo aberto por ali gravava como venda comum.
 * O controller já devolve `subType` nas props, mas o envio não o carregava: a venda de
 * reparo saía gravada como venda comum, sem `sub_type`, e sumia da listagem do Repair.
 * Decisão [W] 2026-10-01: reparo é um TIPO de venda — mesmo cálculo, mesmo estoque.
 *
 * `sub_type` não entra em nenhum cálculo de valor nem de estoque: só decide em que
 * listagem a venda aparece e qual recibo usa (TransactionUtil, `sub_type == 'repair'`).
 *
 * `print_label` vai junto porque o redirect de reparo no store lê essa chave.
 *
 * Só `repair` é aceito da query string. Qualquer outro valor continua venda comum.
 *
 * Bonificação (P-154, Sabor Brasil) é marcada na própria tela, não na URL: a venda vai
 * com `sub_type=bonificacao`, mesmo cálculo e mesma baixa de estoque. Quem lê o tipo é
 * o Financeiro (não cria título) e a NF-e (CFOP 5910/6910). Reparo vence: venda de
 * reparo não é bonificação. O servidor aceita só estes dois valores (SellPosController@store).
 */
export type CamposSubtipo =
  | { sub_type: 'repair'; print_label: 0 }
  | { sub_type: 'bonificacao' }
  | Record<string, never>;

export function camposDeSubtipo(
  subType: string | null | undefined,
  bonificacao = false,
): CamposSubtipo {
  if (subType === 'repair') {
    return { sub_type: 'repair', print_label: 0 };
  }

  if (bonificacao) {
    return { sub_type: 'bonificacao' };
  }

  return {};
}
