/**
 * Venda V3 — o fechamento fala a língua do operador e o resumo da comissão é o da âncora.
 *
 * @covers-us UC-V409
 *
 * Três textos que a tela mostrava e o protótipo não: a nota de bastidor do pagamento
 * (`payment_status=due`, que na âncora mora dentro de `<Meta>` — só no modo de anotação), o
 * parágrafo "fazem parte do porte da comissão — próximo passo" e a condição de pagamento
 * exibida só pelo código ("18"). O resumo da comissão passa a ser o `ComissaoResumo` de
 * `venda-v3.jsx`, com os valores das mesmas funções da gaveta.
 *
 * Mesmo molde de `sells-v3-gravando-sem-cena.test.tsx`: mock do Inertia e do shell, o resto real.
 */
import { describe, expect, it, vi } from 'vitest';
import { fireEvent, render, screen, within } from '@testing-library/react';

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
  catalogo: [],
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

const resumo = () => screen.getByTestId('resumo-comissao');
/* O `brl` separa "R$" do número com espaço que pode ser o não-quebrável: compara sem ele. */
const semEspaco = (s: string | null) => (s ?? '').replace(/\s/g, '');

describe('UC-V409 · o fechamento fala a língua do operador', () => {
  it.each([
    ['sem a gravação', () => render(<SellsCreateV3 businessId={1} cena={cena} />)],
    ['gravando', () => render(<SellsCreateV3 businessId={1} cena={cena} gravacao={gravacao} />)],
  ])('%s, o pagamento e a comissão não mostram anotação de bastidor', (_modo, montar) => {
    montar();
    // Pré-condição anti-vácuo: o bloco de pagamento renderizou (a faixa de saldo está lá).
    expect(screen.getAllByText('Falta receber').length).toBeGreaterThan(0);

    expect(document.body.textContent).not.toContain('payment_status');
    expect(document.body.textContent).not.toContain('caminho normal do balcão');
    expect(document.body.textContent).not.toContain('porte da comissão');
  });

  it('sem beneficiário, o resumo diz que a venda não tem comissão e o botão abre a gaveta', () => {
    render(<SellsCreateV3 businessId={1} cena={cena} />);
    const r = resumo();

    expect(within(r).getByText('Comissão da venda')).toBeTruthy();
    expect(semEspaco(r.textContent)).toMatch(/R\$0,00/);
    expect(within(r).getByText('Nenhum beneficiário — venda sem comissão')).toBeTruthy();
    expect(within(r).getByText('A cada parcela recebida')).toBeTruthy();

    fireEvent.click(within(r).getByRole('button', { name: 'Configurar comissão' }));
    expect(screen.getByRole('heading', { name: 'Comissão desta venda' })).toBeTruthy();
  });

  it('com um beneficiário, o resumo mostra o mesmo valor e percentual da gaveta', () => {
    render(<SellsCreateV3 businessId={1} cena={cena} />);
    fireEvent.click(within(resumo()).getByRole('button', { name: 'Configurar comissão' }));
    fireEvent.click(screen.getByRole('button', { name: '+ Funcionário' }));

    // A gaveta adiciona o funcionário com 3% sobre o líquido: 3% de 10,00 = 0,30.
    const gaveta = semEspaco(screen.getByRole('dialog').textContent);
    expect(gaveta).toMatch(/R\$0,30/);
    expect(gaveta).toContain('3,00%davenda');

    fireEvent.click(within(screen.getByRole('dialog')).getByRole('button', { name: 'Fechar' }));

    const r = semEspaco(resumo().textContent);
    expect(r).toMatch(/Funcionário\(vendedorinterno\)·R\$0,30/);
    expect(r).toContain('·3,00%davenda');
    expect(r).not.toContain('Nenhumbeneficiário');
  });
});

describe('UC-V409 · a condição de pagamento mostra código e nome', () => {
  it('o seletor da gaveta de parcelas mostra "18 — PIX", e a lista traz o nome de cada condição', () => {
    render(<SellsCreateV3 businessId={1} cena={cena} />);
    fireEvent.click(screen.getByRole('button', { name: 'Parcelar…' }));

    const condicao = screen.getByText('18 — PIX').closest('button') as HTMLElement;
    expect(condicao).toBeTruthy();
    fireEvent.keyDown(condicao, { key: 'Enter' });

    expect(screen.getByRole('option', { name: '02 — Boleto 30/60' })).toBeTruthy();
    expect(screen.getByRole('option', { name: '12 — À vista — dinheiro' })).toBeTruthy();
    expect(screen.queryByRole('option', { name: '02' })).toBeNull();
  });
});
