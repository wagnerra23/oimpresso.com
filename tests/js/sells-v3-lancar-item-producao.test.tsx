/**
 * Venda V3 — o que o lançamento coleta de produção chega à linha da venda.
 *
 * @covers-us UC-V416
 *
 * Por que é de RENDER e não só de domínio: `camposDeProducao` e `textoDaColuna` têm teste
 * próprio em `colunas-dominio.test.ts`, mas eles passariam com a Page jogando os campos
 * fora — que é exatamente o defeito que este UC fecha (`adicionarLancado` montava a linha
 * sem `executante`, `local` e `impressao`). Só o render prova que a Page usa o recorte.
 *
 * O modal `LancarItem` é trocado por um stub que confirma um item fixo: os selects dele são
 * Radix (não abrem no jsdom), e o que está em prova é o caminho DEPOIS do confirmar.
 */
import { beforeEach, describe, expect, it, vi } from 'vitest';
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
vi.mock('@/Pages/Sells/_components/v3/LancarItem', async (importOriginal) => {
  const original = await importOriginal<typeof import('@/Pages/Sells/_components/v3/LancarItem')>();
  return {
    ...original,
    LancarItem: ({ produto, onConfirmar }: { produto: { sku: string; nome: string; un: string } | null; onConfirmar: (i: unknown) => void }) =>
      produto ? (
        <button
          type="button"
          onClick={() =>
            onConfirmar({
              sku: produto.sku, nome: produto.nome, un: produto.un, medidas: null,
              qtd: '2', preco: '90,00', desc: '0', acr: '0', estoque: null,
              executante: 'Técnico de teste', obsInterna: '', obsProducao: '',
              local: 'Fachada', impressao: 'Digital — UV',
            })
          }
        >
          Confirmar lançamento
        </button>
      ) : null,
  };
});

import SellsCreateV3 from '@/Pages/Sells/CreateV3';
import { CHAVE_LOCALSTORAGE } from '@/Pages/Sells/_components/v3/colunas-dominio';

const cliente = {
  cod: '0001', nome: 'Consumidor final', padrao: true, doc: '—', ie: '—',
  contrib: 'nao' as const, regime: '—', fone: '—', email: '—', emailNfe: '—',
  contato: 'Consumidor final', endereco: '—', cidade: 'Cidade de teste', uf: 'SC',
  grupo: '—', prazo: 'À vista', tabela: null,
};

const cena = {
  cliente,
  clientes: [cliente],
  itens: [],
  catalogo: [
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

function lancarInstalacao() {
  render(<SellsCreateV3 businessId={1} cena={cena} />);
  fireEvent.click(screen.getByRole('button', { name: 'Consultar produto… F3' }));
  const consulta = screen.getByRole('dialog', { name: 'Consulta de produtos e serviços' });
  fireEvent.click(within(consulta).getByRole('button', { name: 'Lançar Instalação em fachada' }));
  fireEvent.click(screen.getByRole('button', { name: 'Confirmar lançamento' }));
}

describe('UC-V416 · lançamento leva os campos de produção à linha da venda', () => {
  beforeEach(() => window.localStorage.clear());

  it('UC-V416 · funcionário e tipo de impressão do lançamento aparecem nas colunas da linha', () => {
    window.localStorage.setItem(
      CHAVE_LOCALSTORAGE,
      JSON.stringify(['produto', 'qtd', 'preco', 'total', 'impressao', 'executante']),
    );
    lancarInstalacao();

    expect(screen.getByRole('cell', { name: 'Digital — UV' })).toBeTruthy();
    expect(screen.getByRole('cell', { name: 'Técnico de teste' })).toBeTruthy();
  });

  it('UC-V416 · controle: com as colunas desligadas, o grid não mostra os campos de produção', () => {
    window.localStorage.setItem(CHAVE_LOCALSTORAGE, JSON.stringify(['produto', 'qtd', 'preco', 'total']));
    lancarInstalacao();

    expect(screen.queryByRole('cell', { name: 'Digital — UV' })).toBeNull();
    expect(screen.queryByRole('cell', { name: 'Técnico de teste' })).toBeNull();
  });
});
