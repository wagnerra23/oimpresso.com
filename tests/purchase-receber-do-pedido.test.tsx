// ReceberDoPedido — US-COM-013 · UC-PURCRE-13 (resources/js/Pages/Purchase/Create.casos.md).
//
// Contrato (SPEC Compras US-COM-013, decisão coordenação 2026-10-26): o pedido escolhido vira linhas
// da compra com a quantidade que FALTA receber, os preços do pedido e a ligação com a linha do
// pedido; o bloco só aparece quando o fornecedor tem pedido aberto ainda não trazido.

import { describe, it, expect, afterEach, vi } from 'vitest';
import { render, screen, cleanup, waitFor } from '@testing-library/react';

import ReceberDoPedido from '@/Pages/Purchase/_components/ReceberDoPedido';
import { linhasDoPedido, type PedidoAberto } from '@/Pages/Purchase/_components/pedidoAberto';

afterEach(() => {
  cleanup();
  vi.unstubAllGlobals();
});

const pedido: PedidoAberto = {
  id: 7,
  ref_no: 'PO-0007',
  transaction_date: '2026-10-20 10:00:00',
  delivery_date: null,
  status: 'partial',
  linhas: [
    {
      purchase_order_line_id: 31,
      product_id: 5,
      variation_id: 9,
      product_name: 'Lona 440g',
      unit_name: 'm²',
      pedido: 10,
      recebido: 4,
      a_receber: 6,
      pp_without_discount: 12.5,
      discount_percent: 0,
      purchase_price: 12.5,
      purchase_price_inc_tax: 12.5,
      item_tax: 0,
      tax_id: null,
    },
  ],
};

function stubFetch(pedidos: PedidoAberto[]) {
  vi.stubGlobal(
    'fetch',
    vi.fn(async () => ({ ok: true, json: async () => ({ pedidos }) })),
  );
}

describe('linhasDoPedido', () => {
  it('a linha entra com o que falta receber, o preço do pedido e a ligação com a linha do pedido', () => {
    const [l] = linhasDoPedido(pedido);
    expect(l.quantity).toBe(6);
    expect(l.purchase_order_line_id).toBe(31);
    expect(l.variation_id).toBe(9);
    expect(l.purchase_price_inc_tax).toBe(12.5);
    expect(l.po_pedido).toBe(10);
    expect(l.po_recebido).toBe(4);
  });
});

describe('ReceberDoPedido', () => {
  it('mostra o bloco quando o fornecedor tem pedido aberto', async () => {
    stubFetch([pedido]);
    render(<ReceberDoPedido contactId={3} locationId={1} jaUsados={[]} onPick={() => {}} />);
    await waitFor(() => screen.getByText('Receber de um pedido de compra'));
    expect(screen.getByRole('button', { name: 'Trazer itens' })).toBeTruthy();
  });

  it('esconde o bloco quando o único pedido já foi trazido para esta compra', async () => {
    stubFetch([pedido]);
    render(<ReceberDoPedido contactId={3} locationId={1} jaUsados={[7]} onPick={() => {}} />);
    await waitFor(() => expect(fetch).toHaveBeenCalled());
    expect(screen.queryByText('Receber de um pedido de compra')).toBeNull();
  });

  it('não busca nada sem fornecedor escolhido', () => {
    stubFetch([pedido]);
    render(<ReceberDoPedido contactId={null} locationId={1} jaUsados={[]} onPick={() => {}} />);
    expect(fetch).not.toHaveBeenCalled();
    expect(screen.queryByText('Receber de um pedido de compra')).toBeNull();
  });
});
