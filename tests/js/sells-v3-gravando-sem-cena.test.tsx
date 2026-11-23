/**
 * Venda V3 gravando — nada da cena de demonstração aparece como dado real.
 *
 * @covers-us UC-V404 UC-V405 UC-V406 UC-V407
 *
 * A cena abaixo é a que o `SellsV3Controller` manda com a gravação ligada (a forma é provada no
 * Pest do UC-V404, `SellsCreateV3GravarContratoTest.php`): o cliente padrão lido do cadastro e as
 * listas de demonstração vazias. Este render prova a outra metade: a tela não preenche o vazio com
 * nada inventado e diz o que ainda não grava. Cada caso tem o controle sem a gravação, para provar
 * que o assert distingue os dois modos e não passa por ausência de render.
 *
 * Mesmo molde de `colunas-grid.test.tsx`: mock do Inertia e do shell, o resto real.
 */
import { beforeEach, describe, expect, it, vi } from 'vitest';
import { fireEvent, render, screen } from '@testing-library/react';

vi.mock('@inertiajs/react', () => ({
  Head: () => null,
  Deferred: ({ children }: { children: React.ReactNode }) => children,
  router: { post: vi.fn(), get: vi.fn() },
  // O `SubNav` das abas do detalhe do item lê `usePage().url` (mesmo mock de
  // `item-tributacao.test.tsx`). Gravando ele não aparece; no controle sem gravação, sim.
  usePage: () => ({ url: '/sells/create-v3', props: {} }),
  Link: ({ children }: { children: React.ReactNode }) => children,
}));
vi.mock('@/Layouts/AppShellV2', () => ({
  default: ({ children }: { children: React.ReactNode }) => <div>{children}</div>,
}));

import { router } from '@inertiajs/react';
import SellsCreateV3 from '@/Pages/Sells/CreateV3';
import { LancarItem } from '@/Pages/Sells/_components/v3/LancarItem';

/* O cliente padrão como o controller o traduz do cadastro (`clientePadrao`): o que a tela não
   lê vai como travessão. Nome e cidade fictícios. */
const clienteDoCadastro = {
  cod: 'CO0001', nome: 'Cliente do balcão', padrao: true, doc: '—', ie: '—',
  contrib: 'nao' as const, regime: '—', fone: '—', email: '—', emailNfe: '—',
  contato: 'Cliente do balcão', endereco: '—', cidade: 'Cidade do Cadastro', uf: 'PR',
  grupo: '—', prazo: 'À vista', tabela: null,
};

/* Uma linha já na venda, como fica depois de lançar pela busca (sem ids: a gravação a recusa
   com motivo, UC-V381 — é o que torna o clique em "Salvar como orçamento" observável aqui). */
const linha = {
  k: 1, sku: 'SKU-1', nome: 'Produto de teste', un: 'un', medidas: null,
  qtd: '1', preco: '10,00', desc: '0', acr: '0', estoque: 5,
};

const cenaGravando = {
  cliente: clienteDoCadastro,
  clientes: [clienteDoCadastro],
  itens: [linha],
  catalogo: [],
  tabelas: [],
  fsm: [],
  papeisDoUsuario: [],
  executantes: [],
  permissoes: { editarPrecoItem: true },
  transportadoras: [],
};

const gravacao = {
  locationId: 1,
  contactId: 1,
  clienteNome: 'Cliente do balcão',
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

/* A cena do preview (sem a gravação): o que o controller manda com a flag desligada, resumido
   ao que estes casos leem. */
const clienteDeCena = { ...clienteDoCadastro, cod: '0001', nome: 'Consumidor final', cidade: 'Termas do Gravatal', uf: 'SC' };
const cenaPreview = {
  ...cenaGravando,
  cliente: clienteDeCena,
  clientes: [clienteDeCena],
  tabelas: ['Balcão — preço padrão'],
  fsm: [
    { key: 'rascunho', label: 'Rascunho', acao: 'Salvar como orçamento', role: 'sell.create', efeitos: [] },
    { key: 'orcamento', label: 'Orçamento', acao: 'Aprovar orçamento', role: 'sell.approve', efeitos: [] },
    { key: 'entregue', label: 'Entregue', acao: null, role: null, efeitos: [] },
  ],
  papeisDoUsuario: ['sell.create'],
  transportadoras: [
    { cod: '014', nome: 'Transportadora de cena', doc: '—', uf: 'SC', cidade: 'Joinville', placa: 'AAA0A00', antt: '—', modal: 'Rodoviário' },
  ],
};

describe('Venda V3 gravando · sem dados de cena', () => {
  beforeEach(() => {
    window.localStorage.clear();
    vi.mocked(router.post).mockClear();
  });

  it('UC-V404 · o cartão do cliente mostra o cliente padrão do cadastro, sem a cidade de demonstração', () => {
    render(<SellsCreateV3 businessId={1} cena={cenaGravando} gravacao={gravacao} />);

    expect(screen.getByText('— · Cidade do Cadastro/PR')).toBeTruthy();
    expect(screen.getByText('Venda nova começa em Cliente do balcão — é o padrão do balcão.')).toBeTruthy();
    expect(document.body.textContent).not.toContain('Termas do Gravatal');
  });

  it('UC-V405 · gravando, a entrega mostra só o valor do frete e diz o que não grava', () => {
    render(<SellsCreateV3 businessId={1} cena={cenaGravando} gravacao={gravacao} />);
    fireEvent.click(screen.getByRole('button', { name: /Entrega e frete/ }));

    expect(screen.getByText('Valor do frete (entra no total)')).toBeTruthy();
    expect(screen.getByText('A venda grava só o valor do frete')).toBeTruthy();
    expect(screen.queryByText('Placa do veículo')).toBeNull();
    expect(screen.queryByText('Endereço de entrega')).toBeNull();
    expect(screen.queryByText('Frete por conta')).toBeNull();
  });

  it('UC-V405 · controle: sem a gravação, o preview segue com transportadora, veículo e endereço', () => {
    render(<SellsCreateV3 businessId={1} cena={cenaPreview} />);
    fireEvent.click(screen.getByRole('button', { name: /Entrega e frete/ }));

    expect(screen.getByText('Placa do veículo')).toBeTruthy();
    expect(screen.getByText('Endereço de entrega')).toBeTruthy();
    expect(screen.queryByText('A venda grava só o valor do frete')).toBeNull();
  });

  it('UC-V405 · serviço sem executantes: o lançamento diz que funcionário e data não gravam, sem select vazio', () => {
    const servico = {
      sku: 'SRV-1', nome: 'Serviço de teste', un: 'h', preco: 10, estoque: null,
      tipo: 'servico' as const, ean: null, fabrica: null, categoria: null,
    };
    const { rerender } = render(
      <LancarItem produto={servico} executantes={[]} podeEditarPreco onFechar={() => {}} onConfirmar={() => {}} />,
    );
    expect(screen.getByText('O funcionário vinculado e a data prevista ainda não são gravados pela nova venda.')).toBeTruthy();
    expect(screen.queryByText('Funcionário vinculado')).toBeNull();

    // Controle: com executantes (a cena do preview), o select volta.
    rerender(
      <LancarItem
        produto={servico}
        executantes={[{ id: 'f-1', nome: 'Pessoa de teste', papel: 'funcionario' }]}
        podeEditarPreco
        onFechar={() => {}}
        onConfirmar={() => {}}
      />,
    );
    expect(screen.getByText('Funcionário vinculado')).toBeTruthy();
  });

  it('UC-V406 · gravando, a situação não mostra o pipeline de cena e o botão grava o orçamento', () => {
    render(<SellsCreateV3 businessId={1} cena={cenaGravando} gravacao={gravacao} />);

    const situacao = screen.getByRole('button', { name: /Situação/ });
    expect(situacao.textContent).toContain('Não gravada');
    fireEvent.click(situacao);
    expect(screen.getByText(/Ainda não gravada\. Ao gravar, a venda entra no fluxo de vendas da empresa/)).toBeTruthy();
    expect(screen.queryByText('Rascunho')).toBeNull();
    expect(screen.queryByText(/Exige o papel/)).toBeNull();
    expect(screen.queryByText('Venda no fim do fluxo')).toBeNull();

    // O botão está ligado à gravação: a linha sem cadastro é recusada com motivo e nada é postado.
    const salvar = screen.getByRole('button', { name: 'Salvar como orçamento' });
    expect((salvar as HTMLButtonElement).disabled).toBe(false);
    fireEvent.click(salvar);
    expect(screen.getByRole('alert').textContent).toBe('Produto de teste não veio do cadastro de produtos.');
    expect(router.post).not.toHaveBeenCalled();
  });

  it('UC-V406 · controle: sem a gravação, a situação segue com as etapas da cena', () => {
    render(<SellsCreateV3 businessId={1} cena={cenaPreview} />);
    fireEvent.click(screen.getByRole('button', { name: /Situação/ }));

    expect(screen.getAllByText('Rascunho').length).toBeGreaterThan(0);
    expect(screen.queryByText(/Ainda não gravada/)).toBeNull();
  });

  it('UC-V407 · gravando, o detalhe do item não traz NCM, CFOP nem alíquota fixos e diz por quê', () => {
    render(<SellsCreateV3 businessId={1} cena={cenaGravando} gravacao={gravacao} />);
    fireEvent.click(screen.getByRole('button', { name: /Impostos/ }));

    expect(screen.getByText('O detalhe do item ainda não está disponível na nova venda')).toBeTruthy();
    expect(screen.queryByDisplayValue('39199090')).toBeNull();
    expect(screen.queryByDisplayValue('5102')).toBeNull();
    expect(screen.queryByText('Confirmar item')).toBeNull();
    expect(screen.queryByText(/pendência/)).toBeNull();
  });

  it('UC-V407 · controle: sem a gravação, o detalhe do item segue com a aba Tributação da cena', () => {
    render(<SellsCreateV3 businessId={1} cena={cenaPreview} />);
    fireEvent.click(screen.getByRole('button', { name: /Impostos/ }));

    expect(screen.getByDisplayValue('39199090')).toBeTruthy();
    expect(screen.getByText('Confirmar item')).toBeTruthy();
    expect(screen.queryByText('O detalhe do item ainda não está disponível na nova venda')).toBeNull();
  });
});
