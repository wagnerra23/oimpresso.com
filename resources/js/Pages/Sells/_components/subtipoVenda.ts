/**
 * Campos de TIPO de venda que o PDV React precisa enviar ao `SellPosController@store`.
 *
 * O PDV é aberto como reparo por `/pos/create?sub_type=repair` (SellPosController@create,
 * que lê `?sub_type=`). Atenção: `/sells/create` é o SellController, que monta `subType` a
 * partir de `?sale_type=` — por ali o reparo não chega aqui.
 * O controller já devolve `subType` nas props, mas o envio não o carregava: a venda de
 * reparo saía gravada como venda comum, sem `sub_type`, e sumia da listagem do Repair.
 * Decisão [W] 2026-10-01: reparo é um TIPO de venda — mesmo cálculo, mesmo estoque.
 *
 * `sub_type` não entra em nenhum cálculo de valor nem de estoque: só decide em que
 * listagem a venda aparece e qual recibo usa (TransactionUtil, `sub_type == 'repair'`).
 *
 * `print_label` vai junto porque o redirect de reparo no store lê essa chave.
 *
 * Só `repair` é aceito. Qualquer outro valor da query string continua venda comum —
 * nenhum outro tipo foi pedido nem medido.
 */
export type CamposSubtipo = { sub_type: 'repair'; print_label: 0 } | Record<string, never>;

export function camposDeSubtipo(subType: string | null | undefined): CamposSubtipo {
  if (subType === 'repair') {
    return { sub_type: 'repair', print_label: 0 };
  }

  return {};
}
