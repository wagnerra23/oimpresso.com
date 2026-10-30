import { describe, expect, it } from 'vitest';
import { carregarEdicaoV3, montarEdicaoV3, type FonteEdicaoV3 } from '../../resources/js/Pages/Sells/_components/v3/edicao-dominio';

function fonte(): FonteEdicaoV3 {
  return {
    saleId: 98, headline: { type: 'sell' }, urls: { submit: '/pos/98' },
    permissions: { update: true, editPrice: true, editDiscount: true, payments: false },
    trava: { nota: false, pagamento: false, itens_valores: false, cliente: false, motivo: null,
      campos_livres: ['additional_notes', 'staff_note', 'shipping_details', 'shipping_address', 'delivered_to'] },
    form: {
      transaction: { id: 98, final_total: 180 },
      edicaoV3: { status: 'final', contact_id: 98, location_id: 98, discount_type: 'fixed',
        discount_amount: '0,0000', shipping_charges: '0,0000', tax_rate_id: null,
        pay_term_number: 30, pay_term_type: 'days', staff_note: 'Preservar',
        custom_field_1: 'Pedido externo', additional_expense_value_1: '1,1250' },
      sellDetails: [{ transaction_sell_lines_id: 7, product_id: 10, variation_id: 11,
        product_name: 'Produto fictício', quantity_ordered: '2.0000', unit_price_before_discount: '100.0000',
        sell_price_inc_tax: '90.0000', line_discount_amount: '10.0000', line_discount_type: 'fixed',
        item_tax: '0.0000', enable_stock: 1, product_type: 'single', sell_line_note: 'Serial original' }],
      paymentLines: [{ id: 12, amount: '30.0000', method: 'custom_pay_1', account_id: 9,
        paid_on: '2026-10-30 12:00:00', is_return: 0, transaction_no: 'FICTICIO' },
      { id: 13, amount: '2.0000', method: 'cash', is_return: 1 }],
    },
  };
}

describe('UC-SEDIT-05/06/07/09 · carregamento e envio da edição V3', () => {
  it('aguarda o form deferido sem inicializar com a cena nem alterar a fonte', () => {
    const origem = fonte();
    expect(carregarEdicaoV3({ ...origem, form: undefined })).toBeUndefined();
    const antes = structuredClone(origem);
    const edicao = carregarEdicaoV3(origem)!;
    expect(edicao.itens[0]!).toMatchObject({ sell_line_id: 7, quantity: 2, unit_price: 100, discount: 10, discount_type: 'fixed' });
    edicao.itens[0]!.quantity = 3;
    edicao.pagamentos[0]!.amount = '99';
    expect(origem).toEqual(antes);
    expect(edicao.fonte.form.sellDetails[0]!.quantity_ordered).toBe('2.0000');
  });

  it('não reaplica desconto nem troca pagamento por atalho; preserva prazo e dados não expostos', () => {
    const edicao = carregarEdicaoV3(fonte())!;
    const pedido = montarEdicaoV3(edicao);
    expect(pedido.ok).toBe(true);
    if (!pedido.ok) throw new Error(pedido.motivo);
    expect(pedido).toMatchObject({ method: 'put', url: '/pos/98', payload: {
      calcular_total_no_servidor: 1, is_credit_sale: 1, pay_term_number: 30,
      custom_field_1: 'Pedido externo', additional_expense_value_1: '1,1250',
      products: [{ transaction_sell_lines_id: 7, quantity: '2,0000', unit_price: '100,0000',
        unit_price_inc_tax: '90,0000', line_discount_type: 'fixed', line_discount_amount: '10,0000', sell_line_note: 'Serial original' }],
    } });
    expect(pedido.payload).not.toHaveProperty('payment');
    expect(pedido.payload).not.toHaveProperty('final_total');
    expect(pedido.payload).not.toHaveProperty('current_stage_id');
    expect(edicao.pagamentos).toEqual(fonte().form!.paymentLines);
    // Caminho independente: 2 × (100 − 10) = 180; subtotal enviado 2 × 90 = 180.
    const linha = (pedido.payload.products as Record<string, unknown>[])[0]!;
    const decimal = (n: unknown) => Number(String(n).replace(',', '.'));
    expect(decimal(linha.quantity) * decimal(linha.unit_price_inc_tax)).toBe(180);
  });

  it('muda quantidade sem alterar preço gravado nem calcular total no cliente', () => {
    const edicao = carregarEdicaoV3(fonte())!;
    const pedido = montarEdicaoV3(edicao, { itens: [{ ...edicao.itens[0]!, quantity: 3 }] });
    expect(pedido.ok).toBe(true);
    if (pedido.ok) expect(pedido.payload.products).toMatchObject([{ quantity: '3,0000', unit_price_inc_tax: '90,0000' }]);
  });

  it.each(['editPrice', 'editDiscount', 'update'] as const)('UC-SEDIT-02 · respeita %s', (permissao) => {
    const origem = fonte(); origem.permissions[permissao] = false;
    const edicao = carregarEdicaoV3(origem)!;
    const item = { ...edicao.itens[0]!, ...(permissao === 'editDiscount' ? { discount: 12 } : { unit_price: 110 }) };
    expect(montarEdicaoV3(edicao, { itens: [item] }).ok).toBe(false);
  });

  it('recusa linha alheia, duplicada, variação trocada, quantidade inválida e campo FSM', () => {
    const edicao = carregarEdicaoV3(fonte())!;
    const item = edicao.itens[0]!;
    for (const itens of [[{ ...item, sell_line_id: 999 }], [item, item], [{ ...item, variation_id: 999 }], [{ ...item, quantity: NaN }], []]) {
      expect(montarEdicaoV3(edicao, { itens }).ok).toBe(false);
    }
    expect(montarEdicaoV3(edicao, { campos: { current_stage_id: 1 } as never }).ok).toBe(false);
  });

  it('UC-SEDIT-13/14 · com nota só envia textos livres e situação original', () => {
    const origem = fonte();
    origem.trava = { ...origem.trava, nota: true, itens_valores: true, cliente: true, motivo: 'Cancele a nota.' };
    const edicao = carregarEdicaoV3(origem)!;
    expect(montarEdicaoV3(edicao, { campos: { contact_id: 99 } }).ok).toBe(false);
    expect(montarEdicaoV3(edicao, { itens: [{ ...edicao.itens[0]!, quantity: 3 }] }).ok).toBe(false);
    expect(montarEdicaoV3(edicao, { campos: { sale_note: 'Texto alterado' } })).toMatchObject({ ok: true,
      payload: { status: 'final', sale_note: 'Texto alterado' } });
    const pedido = montarEdicaoV3(edicao, { campos: { sale_note: 'Texto alterado' } });
    if (pedido.ok) expect(Object.keys(pedido.payload).sort()).toEqual(['sale_note', 'status']);
  });

  it('UC-SEDIT-15/16 · cancelada libera; pagamento trava valores e permite cliente/texto', () => {
    const origem = fonte();
    origem.trava = { ...origem.trava, pagamento: true, itens_valores: true, motivo: 'Estorne o pagamento.' };
    const edicao = carregarEdicaoV3(origem)!;
    expect(montarEdicaoV3(edicao, { campos: { shipping_charges: 10 } }).ok).toBe(false);
    expect(montarEdicaoV3(edicao, { campos: { contact_id: 99, staff_note: 'Texto' } }).ok).toBe(true);
    expect(montarEdicaoV3(carregarEdicaoV3(fonte())!, { itens: [{ ...edicao.itens[0]!, quantity: 3 }] }).ok).toBe(true);
  });

  it.each(['subunidade', 'combo', 'reparo'] as const)('recusa %s sem perder dado silenciosamente', (tipo) => {
    const origem = fonte();
    if (tipo === 'subunidade') origem.form!.sellDetails[0]!.sub_unit_id = 123;
    if (tipo === 'combo') origem.form!.sellDetails[0]!.product_type = 'combo';
    if (tipo === 'reparo') origem.form!.reparo = { valor: {} };
    expect(montarEdicaoV3(carregarEdicaoV3(origem)!).ok).toBe(false);
  });
});
