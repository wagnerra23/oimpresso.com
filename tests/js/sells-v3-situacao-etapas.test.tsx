/**
 * Venda V3 — gravando, a Situação mostra as etapas do processo em que a venda vai entrar.
 *
 * @covers-us UC-V414
 *
 * Âncora: `SituacaoVenda` de `prototipo-ui/cowork/Wagner/venda-v3.jsx` (lista as etapas do fluxo).
 * As etapas vêm do servidor (`gravacao.etapas`, SellsV3Controller::etapasDaVenda — o processo de
 * IniciarPipelineVendaV3). A venda ainda não existe, então nenhuma etapa fica marcada.
 *
 * Fixtures e mocks copiados de `sells-v3-fechamento-prototipo.test.tsx` (UC-V409).
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

const etapas = [
  { key: 'quote_draft', label: 'Orçamento em rascunho' },
  { key: 'invoiced', label: 'Faturada' },
  { key: 'paid', label: 'Paga' },
];
const abrirSituacao = () => fireEvent.click(screen.getByRole('button', { name: /Situação/ }));

describe('UC-V414 · a Situação mostra as etapas do processo', () => {
  it('gravando, lista as etapas na ordem que o servidor mandou, sem marcar nenhuma', () => {
    render(<SellsCreateV3 businessId={1} cena={cena} gravacao={{ ...gravacao, etapas }} />);
    abrirSituacao();

    const lista = screen.getByTestId('situacao-etapas');
    expect(within(lista).getAllByText(/./).map((e) => e.textContent)).toEqual(etapas.map((e) => e.label));
    expect(lista.querySelector('.bg-primary, .bg-success')).toBeNull();
    expect(screen.getByText(/Ao gravar, a venda entra neste fluxo/)).toBeTruthy();
    expect(screen.getAllByText('Não gravada').length).toBeGreaterThan(0);
  });

  it('gravando sem etapas (processo desativado ou servidor antigo), fica só o texto', () => {
    render(<SellsCreateV3 businessId={1} cena={cena} gravacao={gravacao} />);
    abrirSituacao();

    expect(screen.queryByTestId('situacao-etapas')).toBeNull();
    expect(screen.getByText(/no fluxo de vendas da empresa \(se ele estiver ativo\)/)).toBeTruthy();
  });
});
