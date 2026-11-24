/**
 * Venda V3 — "Consultar produto… F3" abre a consulta em tabela e lança o item escolhido.
 *
 * @covers-us UC-V412
 *
 * Âncora: `ConsultaProduto` de `prototipo-ui/cowork/Wagner/venda-v3.jsx`. A consulta não tem
 * busca própria: usa a do passo 2 (catálogo da cena; gravando, `/products/list`). Gravando,
 * EAN, código de fábrica e categoria saem com travessão, porque a busca do servidor não os
 * devolve (dívida declarada no casos.md).
 *
 * Fixtures e mocks copiados de `sells-v3-fechamento-prototipo.test.tsx` (UC-V409), com um
 * catálogo de cena preenchido.
 */
import { afterEach, describe, expect, it, vi } from 'vitest';
import { act, fireEvent, render, screen, within } from '@testing-library/react';

vi.mock('@inertiajs/react', () => ({
  Head: () => null,
  Deferred: ({ children }: { children: React.ReactNode }) => children,
  router: { post: vi.fn(), get: vi.fn() },
  usePage: () => ({ url: '/sells/create-v3', props: {} }),
  Link: ({ children }: { children: React.ReactNode }) => children,
}));
vi.mock('@/Layouts/AppShellV2', () => ({
  default: ({ children }: { children: React.ReactNode }) => <div>{children}</div>,
}));

import SellsCreateV3 from '@/Pages/Sells/CreateV3';

const cliente = {
  cod: '0001', nome: 'Consumidor final', padrao: true, doc: '—', ie: '—',
  contrib: 'nao' as const, regime: '—', fone: '—', email: '—', emailNfe: '—',
  contato: 'Consumidor final', endereco: '—', cidade: 'Cidade de teste', uf: 'SC',
  grupo: '—', prazo: 'À vista', tabela: null,
};

/* Uma linha de 10,00: com o desconto zerado, o líquido da comissão é 10,00. */
const linha = {
  k: 1, sku: 'SKU-1', nome: 'Produto de teste', un: 'un', medidas: null,
  qtd: '1', preco: '10,00', desc: '0', acr: '0', estoque: 5,
};

const cena = {
  cliente,
  clientes: [cliente],
  itens: [linha],
  catalogo: [
    { sku: 'LONA-440', nome: 'Lona front-light 440g', un: 'm²', preco: 38, estoque: 120, tipo: 'produto' as const,
      ean: '7890000000011', fabrica: 'FL-440', categoria: 'Lonas' },
    { sku: 'INST-01', nome: 'Instalação em fachada', un: 'h', preco: 90, estoque: null, tipo: 'servico' as const,
      ean: null, fabrica: null, categoria: 'Serviços' },
  ],
  tabelas: ['Balcão — preço padrão'],
  fsm: [
    { key: 'rascunho', label: 'Rascunho', acao: 'Salvar como orçamento', role: 'sell.create', efeitos: [] },
    { key: 'entregue', label: 'Entregue', acao: null, role: null, efeitos: [] },
  ],
  papeisDoUsuario: ['sell.create'],
  executantes: [],
  permissoes: { editarPrecoItem: true },
  transportadoras: [],
};

const gravacao = {
  locationId: 1,
  contactId: 1,
  clienteNome: 'Consumidor final',
  permissoes: { editarPreco: true, editarDesconto: true, descontoMaximo: null, criarCliente: false, registrarPagamento: true },
  prazo: { numero: null, tipo: null },
  config: {
    dataEditavel: false, descontoDaVenda: true, descontoPadrao: 0, impostoDoPedido: true, precoMinimo: false,
    venderSemEstoque: true, comissionado: { tipo: null, obrigatorio: false }, prazoObrigatorio: false,
    rascunho: true, aPrazo: true, recorrente: true, arredondamento: 0, somaNaLinha: false,
  },
  comissionados: [],
  tabelas: [{ id: 0, nome: 'Preço padrão' }],
  tabelaPadraoId: null,
};

afterEach(() => vi.unstubAllGlobals());

const consulta = () => screen.getByRole('dialog', { name: 'Consulta de produtos e serviços' });

describe('UC-V412 · consultar produto em tabela', () => {
  it('sem gravar, o botão abre a consulta com o catálogo inteiro e as colunas da âncora', () => {
    render(<SellsCreateV3 businessId={1} cena={cena} />);
    fireEvent.click(screen.getByRole('button', { name: 'Consultar produto… F3' }));

    const d = consulta();
    for (const c of ['SKU', 'Produto / serviço', 'Cód. EAN', 'Cód. fábrica', 'Categoria', 'Tipo', 'Unid.', 'Tabela', 'Estoque']) {
      expect(within(d).getByRole('columnheader', { name: c })).toBeTruthy();
    }
    expect(within(d).getByText('7890000000011')).toBeTruthy();
    expect(within(d).getByText('FL-440')).toBeTruthy();
    expect(within(d).getByText('Lonas')).toBeTruthy();
    expect(within(d).getByText('serviço')).toBeTruthy();
  });

  it('F3 abre a consulta e tira o F3 do navegador', async () => {
    render(<SellsCreateV3 businessId={1} cena={cena} />);
    expect(screen.queryByRole('dialog', { name: 'Consulta de produtos e serviços' })).toBeNull();
    const ev = new KeyboardEvent('keydown', { key: 'F3', cancelable: true, bubbles: true });
    act(() => {
      window.dispatchEvent(ev);
    });
    expect(ev.defaultPrevented).toBe(true);
    expect(await screen.findByRole('dialog', { name: 'Consulta de produtos e serviços' })).toBeTruthy();
  });

  it('escolher na consulta abre o mesmo lançamento da busca do passo 2', () => {
    render(<SellsCreateV3 businessId={1} cena={cena} />);
    fireEvent.click(screen.getByRole('button', { name: 'Consultar produto… F3' }));
    fireEvent.click(within(consulta()).getByRole('button', { name: 'Lançar Lona front-light 440g' }));

    expect(screen.queryByRole('dialog', { name: 'Consulta de produtos e serviços' })).toBeNull();
    expect(screen.getByRole('heading', { name: 'Lançar Lona front-light 440g' })).toBeTruthy();
  });

  it('gravando, busca no cadastro a partir de 2 letras e mostra travessão no que o servidor não devolve', async () => {
    const fetchFalso = vi.fn().mockResolvedValue({
      ok: true,
      json: async () => [
        { product_id: 7, variation_id: 70, name: 'Banner lona', variation: 'DUMMY', type: 'single', enable_stock: 1,
          qty_available: 4, selling_price: 25, sub_sku: 'BAN-1', unit: 'un' },
      ],
    });
    vi.stubGlobal('fetch', fetchFalso);
    render(<SellsCreateV3 businessId={1} cena={cena} gravacao={gravacao} />);
    fireEvent.click(screen.getByRole('button', { name: 'Consultar produto… F3' }));

    expect(within(consulta()).getByText('Digite pelo menos 2 letras para buscar no cadastro.')).toBeTruthy();

    fireEvent.change(within(consulta()).getByRole('textbox', { name: 'Buscar produto' }), { target: { value: 'ban' } });
    const linha = (await within(consulta()).findByText('Banner lona')).closest('tr') as HTMLElement;
    expect(String(fetchFalso.mock.calls[0][0])).toContain('/products/list?');
    // SKU · nome · EAN · fábrica · categoria — as três do meio sem dado do servidor.
    const celulas = within(linha).getAllByRole('cell').map((c) => c.textContent);
    expect(celulas.slice(2, 5)).toEqual(['—', '—', '—']);
  });
});
