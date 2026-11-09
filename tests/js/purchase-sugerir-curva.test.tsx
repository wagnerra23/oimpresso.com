// "Sugerir pela curva" na tela real (US-VEST-031): a grade recebe o que o servidor mandou e o POST
// leva o que a pessoa deixou. A divisão é provada em CurvaDeCompraContratoTest.
// Cobre UC-PURCRE-16, UC-PURCRE-17 e UC-PURCRE-19 (Purchase/Create.casos.md).

import { afterEach, beforeEach, expect, it, vi } from 'vitest';
import { cleanup, fireEvent, render, screen, waitFor } from '@testing-library/react';
import type { ComponentProps } from 'react';

const postado = vi.fn();

vi.mock('@inertiajs/react', async () => {
  const React = await vi.importActual<typeof import('react')>('react');
  return {
    router: { visit: vi.fn() },
    usePage: () => ({ props: {}, url: '/purchases/create' }),
    useForm: (inicial: Record<string, unknown>) => {
      const [data, setDados] = React.useState(inicial);
      const fn = React.useRef((d: Record<string, unknown>) => d);
      return {
        data, errors: {}, processing: false, setError: vi.fn(),
        setData: (a: string | Record<string, unknown>, b?: unknown) => (typeof a === 'string' ? setDados((d) => ({ ...d, [a]: b })) : setDados(a)),
        transform: (f: typeof fn.current) => { fn.current = f; },
        post: (url: string) => postado(url, fn.current(data)),
      };
    },
  };
});
vi.mock('@/Pages/Purchase/_components/SupplierCombobox', () => ({
  default: ({ onChange }: { onChange: (s: { id: number; label: string }) => void }) => (
    <button type="button" onClick={() => onChange({ id: 5, label: 'F' })}>Escolher fornecedor</button>
  ),
}));

import PurchaseCreate from '@/Pages/Purchase/Create';

const layout = {
  product_id: 7, product_name: 'Camiseta', type: 'variable', mode: 'matrix-1d', unit_cost: 10,
  rows: [{ id: 11, label: 'P' }, { id: 12, label: 'M' }, { id: 13, label: 'G' }],
  cols: [{ id: 'qtd', label: 'Qtd' }],
  cellVariationMap: { '11__qtd': 11, '12__qtd': 12, '13__qtd': 13 },
};
// Números de UC-VCUR-01: vendidas 2 · 6 · 2, total 7 → 2 · 4 · 1.
const sugestao = { base: 'produto', motivo: null, dias: 90, linhas: [[11, 2], [12, 4], [13, 1]].map(([variation_id, sugerida]) => ({ variation_id, sugerida })) };
let curva: unknown;
const urls: string[] = [];

beforeEach(() => {
  curva = sugestao;
  urls.length = 0;
  postado.mockClear();
  vi.stubGlobal('fetch', vi.fn(async (url: string) => {
    urls.push(url);
    return { ok: true, json: async () => (url.startsWith('/vestuario') ? curva : url.startsWith('/purchases/grade') ? layout : []) };
  }));
});
afterEach(() => { cleanup(); vi.unstubAllGlobals(); });

const abrir = (extra: Partial<ComponentProps<typeof PurchaseCreate>> = {}) => render(
  <PurchaseCreate
    business_locations={{ 1: 'Matriz' }} taxes={[]} order_statuses={{ received: 'Recebido' }} default_purchase_status="received"
    payment_types={{}} customer_groups={{}} accounts={{}} common_settings={{}} default_datetime="2026-11-09 10:00:00"
    currency={{ symbol: 'R$', code: 'BRL', thousand_separator: '.', decimal_separator: ',', decimal: 2 }}
    permissions={{ create_supplier: true, create_customer: false, edit_price: true, view_purchase_price: true }}
    produto_inicial={{ product_id: 7, label: 'Camiseta' } as never} curva_compra_disponivel {...extra}
  />
);
const celula = (t: string) => screen.getByLabelText(`Quantidade tamanho ${t}`) as HTMLInputElement;
const qtds = () => ['P', 'M', 'G'].map((t) => Number(celula(t).value));
async function sugerir(total: string) {
  await screen.findByLabelText('Quantidade tamanho P');
  fireEvent.change(screen.getByLabelText('Total de peças'), { target: { value: total } });
  fireEvent.click(screen.getByRole('button', { name: /Sugerir pela curva/ }));
}

it('UC-PURCRE-16 · a grade recebe as quantidades do servidor, a soma bate e nada entra na compra', async () => {
  abrir();
  await sugerir('7');
  await waitFor(() => expect(qtds()).toEqual([2, 4, 1]));
  expect(urls).toContain('/vestuario/curva-compra?product_id=7&total=7');
  expect(screen.getByRole('button', { name: /Adicionar à compra/ }).textContent).toContain('(7 un)');
  expect(screen.getByText('Nenhum item adicionado.')).toBeTruthy();
  expect(postado).not.toHaveBeenCalled();
});

it('UC-PURCRE-16 · sem venda mostra o motivo e não mexe na grade', async () => {
  curva = { base: null, motivo: 'sem_venda_no_produto_nem_na_categoria', dias: 90, linhas: [] };
  abrir();
  await sugerir('7');
  await waitFor(() => expect(screen.getByRole('status').textContent).toContain('não venderam nos últimos 90 dias'));
  expect(qtds()).toEqual([0, 0, 0]);
});

it('UC-PURCRE-17 · quem compra edita a sugestão e o salvar leva a quantidade editada', async () => {
  const { container } = abrir();
  await sugerir('7');
  await waitFor(() => expect(qtds()).toEqual([2, 4, 1]));
  fireEvent.change(celula('M'), { target: { value: '9' } });
  fireEvent.click(screen.getByRole('button', { name: /Adicionar à compra/ }));
  fireEvent.click(screen.getByRole('button', { name: 'Escolher fornecedor' }));
  await screen.findByText('3 itens');
  expect(postado).not.toHaveBeenCalled();
  fireEvent.submit(container.querySelector('form') as HTMLFormElement);
  const corpo = postado.mock.calls[0]?.[1] as { purchases: { variation_id: number; quantity: number }[] };
  expect(corpo.purchases.map((l) => [l.variation_id, l.quantity])).toEqual([[11, 2], [12, 9], [13, 1]]);
});

it('UC-PURCRE-19 · sem o módulo no pacote o botão não aparece', async () => {
  abrir({ curva_compra_disponivel: false });
  await screen.findByLabelText('Quantidade tamanho P');
  expect(screen.queryByRole('button', { name: /Sugerir pela curva/ })).toBeNull();
});
