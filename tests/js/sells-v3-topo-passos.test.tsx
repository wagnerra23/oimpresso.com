/**
 * Venda V3 — o topo e os passos são os da âncora.
 *
 * @covers-us UC-V410
 *
 * Âncora: `VendaV3Create` + `Create` de `prototipo-ui/cowork/Wagner/venda-v3.jsx`. Topo = `PageHeader`
 * com título e subtítulo, sem selo "V3" e, na rota própria da V3, sem botão de voltar. Passos
 * numerados: 1 Cliente · 2 Itens · 3 Fechamento; "Entrega e frete" e "Observações e produção"
 * são gavetas sem número. Gravando, o botão da venda final se chama "Finalizar venda".
 *
 * Fixtures e mocks copiados de `sells-v3-fechamento-prototipo.test.tsx` (UC-V409).
 */
import { describe, expect, it, vi } from 'vitest';
import { render, screen } from '@testing-library/react';

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

/* Os números dos passos: o `Passo` de `primitivos.tsx` é o único span com `size-[18px]`. */
const passos = () =>
  [...document.querySelectorAll('span')]
    .filter((s) => s.className.includes('size-[18px]'))
    .map((s) => (s.parentElement?.textContent ?? '').replace(/\s+/g, ' ').trim());

describe('UC-V410 · o topo e os passos são os da âncora', () => {
  it.each([
    ['sem a gravação', () => render(<SellsCreateV3 businessId={1} cena={cena} />)],
    ['gravando', () => render(<SellsCreateV3 businessId={1} cena={cena} gravacao={gravacao} />)],
  ])('%s, o topo é o PageHeader: título e subtítulo, sem selo V3 e sem voltar', (_modo, montar) => {
    montar();
    expect(screen.getByRole('heading', { level: 1, name: 'Nova venda' })).toBeTruthy();
    expect(screen.getByText('Cliente, itens, pagamento. O resto tem valor padrão.')).toBeTruthy();
    expect(screen.queryByText('V3')).toBeNull();
    expect(screen.queryByRole('button', { name: /Voltar para Vendas/ })).toBeNull();
  });

  it.each([
    ['sem a gravação', () => render(<SellsCreateV3 businessId={1} cena={cena} />)],
    ['gravando', () => render(<SellsCreateV3 businessId={1} cena={cena} gravacao={gravacao} />)],
  ])('%s, os passos são 1 Cliente · 2 Itens · 3 Fechamento, e as gavetas não têm número', (_modo, montar) => {
    montar();
    const p = passos();
    expect(p).toHaveLength(3);
    expect(p[0]).toMatch(/^1 ?Cliente/);
    expect(p[1]).toMatch(/^2 ?Itens/);
    expect(p[2]).toMatch(/^3 ?Fechamento/);
    expect(screen.getByRole('button', { name: /Entrega e frete/ }).textContent).not.toMatch(/^\s*\d/);
    expect(screen.getByRole('button', { name: /Observações e produção/ }).textContent).not.toMatch(/^\s*\d/);
  });

  it('gravando, o botão da venda final se chama "Finalizar venda"', () => {
    render(<SellsCreateV3 businessId={1} cena={cena} gravacao={gravacao} />);
    expect(screen.getByRole('button', { name: 'Finalizar venda' })).toBeTruthy();
    expect(screen.queryByRole('button', { name: 'Gravar venda' })).toBeNull();
  });
});
