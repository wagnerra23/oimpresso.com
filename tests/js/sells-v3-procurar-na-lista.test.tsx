/**
 * Nova venda V3 — "Procurar na lista de produtos" (UC-V413 · UC-PIDX-53).
 *
 * A lista abre numa aba nova (a venda não guarda rascunho) e devolve o produto por mensagem entre
 * abas (`@/Lib/produto-escolhido`). REGRA MESTRE (valor): o produto volta SEM preço; a venda põe o
 * SKU na própria busca e abre o "Lançar item" com o resultado DELA. A dupla prova abaixo compara
 * os dois caminhos na tela real: clicar no resultado da busca × receber o produto da lista. O
 * lançamento tem de sair idêntico (nome, SKU, preço, unidade, estoque).
 *
 * Mesmo molde de `sells-v3-gravando-sem-cena.test.tsx`: Inertia e shell mockados, o resto real.
 */
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
import { act, cleanup, fireEvent, render, screen, waitFor } from '@testing-library/react';

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
import { achadoDoProdutoEscolhido, urlDaListaDeProdutos } from '@/Pages/Sells/_components/v3/procurar-na-lista';
import { canalValido, enviarProdutoEscolhido, ouvirProdutoEscolhido } from '@/Lib/produto-escolhido';

const cliente = {
  cod: 'CO0001', nome: 'Cliente do balcão', padrao: true, doc: '—', ie: '—',
  contrib: 'nao' as const, regime: '—', fone: '—', email: '—', emailNfe: '—',
  contato: 'Cliente do balcão', endereco: '—', cidade: 'Cidade do Cadastro', uf: 'PR',
  grupo: '—', prazo: 'À vista', tabela: null,
};
const cena = {
  cliente, clientes: [cliente], itens: [], catalogo: [], tabelas: [], fsm: [], papeisDoUsuario: [],
  executantes: [], permissoes: { editarPrecoItem: true }, transportadoras: [],
};
const gravacao = {
  locationId: 1, contactId: 1, clienteNome: 'Cliente do balcão',
  permissoes: { editarPreco: true, editarDesconto: true, descontoMaximo: null, criarCliente: false, registrarPagamento: true },
  prazo: { numero: null, tipo: null },
  config: {
    dataEditavel: false, descontoDaVenda: true, descontoPadrao: 0, impostoDoPedido: true, precoMinimo: false,
    venderSemEstoque: true, comissionado: { tipo: null, obrigatorio: false }, prazoObrigatorio: false,
    rascunho: true, aPrazo: true, recorrente: true, arredondamento: 0, somaNaLinha: false,
  },
  comissionados: [], tabelas: [{ id: 0, nome: 'Preço padrão' }], tabelaPadraoId: null,
};

/** Linhas de /products/list (ProductUtil::filterProduct) — produto 42 simples; 50 com 2 variações. */
const BUSCA: Record<string, unknown[]> = {
  'LON-7': [{ product_id: 42, variation_id: 420, name: 'Lona 440g', variation: 'DUMMY', type: 'single', enable_stock: 1, qty_available: '18.0000', selling_price: '37.5000', sub_sku: 'LON-7', unit: 'un' }],
  'CAM-1': [
    { product_id: 50, variation_id: 501, name: 'Camiseta', variation: 'P', type: 'variable', enable_stock: 1, qty_available: '3', selling_price: '29.9', sub_sku: 'CAM-1-P', unit: 'un' },
    { product_id: 50, variation_id: 502, name: 'Camiseta', variation: 'M', type: 'variable', enable_stock: 1, qty_available: '4', selling_price: '29.9', sub_sku: 'CAM-1-M', unit: 'un' },
  ],
};

let pedidos: string[] = [];
beforeEach(() => {
  pedidos = [];
  // jsdom não implementa `window.focus` (a venda chama para voltar à aba dela).
  vi.spyOn(window, 'focus').mockImplementation(() => {});
  vi.spyOn(globalThis, 'fetch').mockImplementation(async (url: RequestInfo | URL) => {
    const u = String(url);
    pedidos.push(u);
    const termo = new URLSearchParams(u.split('?')[1] ?? '').get('term') ?? '';
    return new Response(JSON.stringify(BUSCA[termo] ?? []), { status: 200 });
  });
});
afterEach(() => {
  cleanup();
  vi.restoreAllMocks();
});

/** O "Lançar item" aberto, como o operador o vê: texto e valores dos campos. */
function lancamento(): { texto: string; campos: string[] } {
  const d = screen.getByRole('dialog');
  return { texto: d.textContent ?? '', campos: Array.from(d.querySelectorAll('input')).map((i) => (i as HTMLInputElement).value) };
}

/** Abre a venda e devolve o canal que ela mandaria para a aba da lista. */
function abrirVenda(): string {
  render(<SellsCreateV3 cena={cena as never} gravacao={gravacao as never} />);
  const abrir = vi.spyOn(window, 'open').mockReturnValue(null);
  fireEvent.click(screen.getByRole('button', { name: 'Procurar na lista de produtos' }));
  const url = String(abrir.mock.calls[0]?.[0] ?? '');
  expect(url.startsWith('/products?')).toBe(true);
  return new URLSearchParams(url.split('?')[1]).get('canal') ?? '';
}

describe('UC-V413 · a lista abre numa aba nova com o caminho de volta e o canal', () => {
  it('a URL leva de, de_rotulo, para e um canal válido', () => {
    expect(urlDaListaDeProdutos('/sells/create-v3?x=1', ' Banner ', 'abc-1')).toBe(
      '/products?de=%2Fsells%2Fcreate-v3%3Fx%3D1&de_rotulo=Nova+venda&canal=abc-1&para=Banner',
    );
    const canal = abrirVenda();
    expect(canalValido(canal)).toBe(canal);
  });
});

describe('UC-V413 · dupla prova do valor: lista × clique na busca', () => {
  it('o produto vindo da lista abre o mesmo lançamento que o clique no resultado', async () => {
    // Caminho 1 — o de sempre: digita o SKU e clica no resultado.
    render(<SellsCreateV3 cena={cena as never} gravacao={gravacao as never} />);
    fireEvent.change(screen.getByPlaceholderText(/Buscar produto por nome/), { target: { value: 'LON-7' } });
    fireEvent.click(await screen.findByRole('button', { name: /Lona 440g/ }, { timeout: 2000 }));
    const peloClique = lancamento();
    expect(peloClique.texto).toContain('Lona 440g');
    cleanup();

    // Caminho 2 — o produto volta da lista de produtos pelo canal.
    const canal = abrirVenda();
    await act(async () => {
      expect(enviarProdutoEscolhido({ canal, produtoId: 42, sku: 'LON-7', nome: 'Lona 440g' })).toBe(true);
    });
    await waitFor(() => expect(screen.queryByRole('dialog')).not.toBeNull(), { timeout: 2000 });
    const pelaLista = lancamento();

    expect(pelaLista).toEqual(peloClique);
    // O preço da tela é o da busca (37,50), e nenhum preço viajou na mensagem.
    expect(pelaLista.campos).toContain('37,50');
    expect(pedidos.some((u) => u.startsWith('/products/list?') && u.includes('term=LON-7'))).toBe(true);
  });

  it('produto com mais de uma variação não abre sozinho: os resultados ficam para escolher', async () => {
    const canal = abrirVenda();
    await act(async () => {
      enviarProdutoEscolhido({ canal, produtoId: 50, sku: 'CAM-1', nome: 'Camiseta' });
    });
    await screen.findByRole('button', { name: /Camiseta - P/ }, { timeout: 2000 });
    // Espera a tela assentar: um lançamento aberto pelo efeito viria depois dos resultados.
    await act(async () => {
      await new Promise((r) => setTimeout(r, 100));
    });
    expect(screen.getByRole('button', { name: /Camiseta - M/ })).toBeTruthy();
    expect(screen.queryByRole('dialog')).toBeNull();
  });

  it('mensagem de outro canal (outra venda aberta) é ignorada', async () => {
    abrirVenda();
    await act(async () => {
      enviarProdutoEscolhido({ canal: 'outra-venda', produtoId: 42, sku: 'LON-7', nome: 'Lona 440g' });
      await new Promise((r) => setTimeout(r, 400));
    });
    // A busca da venda não foi tocada (o pedido à /products/list ainda nem sairia: a busca espera 250 ms).
    expect((screen.getByPlaceholderText(/Buscar produto por nome/) as HTMLInputElement).value).toBe('');
    expect(screen.queryByRole('dialog')).toBeNull();
  });
});

describe('UC-V413 · peças puras', () => {
  it('o achado só sai quando há exatamente uma variação daquele produto', () => {
    const p = (sku: string) => ({ sku, nome: sku, un: 'un', preco: 1, estoque: 1, tipo: 'produto' as const, ean: null, fabrica: null, categoria: null });
    const dono: Record<string, number> = { A: 1, B: 2, C: 2 };
    const de = (s: string) => dono[s];
    expect(achadoDoProdutoEscolhido([p('A'), p('B'), p('C')], de, 1)?.sku).toBe('A');
    expect(achadoDoProdutoEscolhido([p('A'), p('B'), p('C')], de, 2)).toBeNull();
    expect(achadoDoProdutoEscolhido([p('A')], de, 9)).toBeNull();
  });

  it('o canal só aceita id simples, e o ouvinte filtra pelo canal', async () => {
    expect(canalValido('abc-123')).toBe('abc-123');
    for (const ruim of ['', 'a b', 'a/b', '<x>', 'x'.repeat(65), null]) expect(canalValido(ruim as string | null)).toBeNull();
    const recebidos: number[] = [];
    const parar = ouvirProdutoEscolhido('meu', (m) => recebidos.push(m.produtoId));
    enviarProdutoEscolhido({ canal: 'outro', produtoId: 1, sku: null, nome: 'x' });
    enviarProdutoEscolhido({ canal: 'meu', produtoId: 2, sku: null, nome: 'y' });
    await waitFor(() => expect(recebidos).toEqual([2]));
    parar();
  });
});
