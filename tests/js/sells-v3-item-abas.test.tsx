/**
 * Venda V3 — cada ação da linha abre o detalhe do item na aba DELA, na tela de verdade.
 *
 * @covers-us UC-V320
 *
 * `item-acoes-dominio.test.ts` prova o mapa ação → aba (`ABA_POR_ACAO`), e passava verde enquanto
 * a tela abria tudo em Geral: o drawer fica montado o tempo todo e só lia `abaInicial` na
 * montagem da página. Este render clica nos botões da linha e lê qual aba ficou selecionada.
 * Sem a gravação (preview), que é o modo em que as abas existem — gravando o detalhe não tem abas
 * (UC-V407).
 */
import { describe, expect, it, vi } from 'vitest';
import { fireEvent, render, screen, within } from '@testing-library/react';

vi.mock('@inertiajs/react', () => ({
  Head: () => null,
  Deferred: ({ children }: { children: React.ReactNode }) => children,
  router: { post: vi.fn(), get: vi.fn() },
  // o `SubNav` das abas lê `usePage().url` (mesmo mock de `item-tributacao.test.tsx`)
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

const linha = (k: number, nome: string) => ({
  k, sku: `SKU-${k}`, nome, un: 'un', medidas: null,
  qtd: '1', preco: '10,00', desc: '0', acr: '0', estoque: 5,
});

/* A cena do preview resumida ao que estes casos leem: duas linhas, para o "Próximo ›" ter destino. */
const cena = {
  cliente,
  clientes: [cliente],
  itens: [linha(1, 'Primeiro item'), linha(2, 'Segundo item')],
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

/** A aba selecionada no detalhe aberto, pelo `aria-selected` da barra de abas. */
function abaSelecionada(): string {
  const abas = screen.getByRole('tablist', { name: 'Detalhe do item' });
  const ativa = within(abas)
    .getAllByRole('tab')
    .filter((t) => t.getAttribute('aria-selected') === 'true');
  expect(ativa).toHaveLength(1);
  return (ativa[0]!.textContent ?? '').replace(/\d+$/, '').trim();
}

const impostosDaLinha = (i: number) => screen.getAllByRole('button', { name: 'Impostos' })[i]!;
const lupaDaLinha = (i: number) => screen.getAllByRole('button', { name: 'Detalhes do item' })[i]!;
const fechar = () => fireEvent.click(screen.getByRole('button', { name: 'Cancelar' }));

describe('UC-V320 · a ação da linha decide a aba em que o detalhe do item abre', () => {
  it('"Impostos" abre direto em Tributação, com os campos fiscais', () => {
    render(<SellsCreateV3 businessId={1} cena={cena} />);
    fireEvent.click(impostosDaLinha(0));

    expect(abaSelecionada()).toBe('Tributação');
    // o conteúdo da Tributação está na tela, não só o rótulo da aba
    expect(screen.getByDisplayValue('39199090')).toBeTruthy();
  });

  it('a lupa abre em Geral', () => {
    render(<SellsCreateV3 businessId={1} cena={cena} />);
    fireEvent.click(lupaDaLinha(0));

    expect(abaSelecionada()).toBe('Geral');
  });

  it('cada abertura segue o botão clicado, mesmo depois de o operador trocar de aba', () => {
    render(<SellsCreateV3 businessId={1} cena={cena} />);

    fireEvent.click(lupaDaLinha(0));
    expect(abaSelecionada()).toBe('Geral');
    fechar();

    fireEvent.click(impostosDaLinha(0));
    expect(abaSelecionada()).toBe('Tributação');

    // O operador muda para Geral e fecha; abrir de novo pelo "Impostos" volta à Tributação.
    const abas = screen.getByRole('tablist', { name: 'Detalhe do item' });
    fireEvent.click(within(abas).getByRole('tab', { name: /^Geral/ }));
    expect(abaSelecionada()).toBe('Geral');
    fechar();

    fireEvent.click(impostosDaLinha(1));
    expect(abaSelecionada()).toBe('Tributação');
  });

  it('navegar para o próximo item com o detalhe aberto mantém a aba', () => {
    render(<SellsCreateV3 businessId={1} cena={cena} />);
    fireEvent.click(impostosDaLinha(0));
    expect(screen.getByText(/Item 1 · Primeiro item/)).toBeTruthy();

    fireEvent.click(screen.getByRole('button', { name: 'Próximo ›' }));

    expect(screen.getByText(/Item 2 · Segundo item/)).toBeTruthy();
    expect(abaSelecionada()).toBe('Tributação');
  });
});
