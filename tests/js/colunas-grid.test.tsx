/**
 * Colunas do grid — a PONTE entre a preferência e a tela.
 *
 * @covers-us UC-V367 UC-V368
 *
 * Por que este teste é de RENDER e não de domínio: os 21 casos de
 * `colunas-dominio.test.ts` provam que a preferência é saneada, ordenada e
 * persistida — e passavam todos enquanto o grid renderizava seis colunas
 * literais e ignorava a preferência inteira. Domínio perfeito, tela surda.
 * Só o render prova que escolher no modal muda o que o operador vê.
 *
 * Mesmo padrão de `backup-index.test.tsx`: mock do Inertia e do shell, o resto real.
 */
import { beforeEach, describe, expect, it, vi } from 'vitest';
import { render, screen, within } from '@testing-library/react';

vi.mock('@inertiajs/react', () => ({
  Head: () => null,
  Deferred: ({ children }: { children: React.ReactNode }) => children,
  router: { post: vi.fn(), get: vi.fn() },
}));
vi.mock('@/Layouts/AppShellV2', () => ({
  default: ({ children }: { children: React.ReactNode }) => <div>{children}</div>,
}));

import SellsCreateV3 from '@/Pages/Sells/CreateV3';
import { CHAVE_LOCALSTORAGE, colunasPadrao } from '@/Pages/Sells/_components/v3/colunas-dominio';

const cliente = {
  cod: '0001', nome: 'Consumidor final', padrao: true, doc: '—', ie: 'ISENTO',
  contrib: 'nao' as const, regime: 'Simples Nacional', fone: '—', email: '—', emailNfe: '—',
  contato: '—', endereco: 'Venda no balcão', cidade: 'Termas do Gravatal', uf: 'SC',
  grupo: 'Varejo', prazo: 'À vista', tabela: null,
};

const cena = {
  cliente,
  clientes: [cliente],
  itens: [
    {
      k: 1, sku: 'LON-440-BR', nome: 'Lona 440g branca fosca', un: 'm²',
      medidas: '5× 0,50×5,00m', qtd: '12,50', preco: '68,90', desc: '0', acr: '0', estoque: 412.5,
    },
  ],
  catalogo: [
    {
      sku: 'LON-440-BR', nome: 'Lona 440g branca fosca', un: 'm²', preco: 68.9, estoque: 412.5,
      tipo: 'produto' as const, ean: '7899123400015', fabrica: 'LN440-BR-FO', categoria: 'Lonas',
    },
  ],
  tabelas: ['Balcão — preço padrão'],
  fsm: [
    { key: 'rascunho', label: 'Rascunho', acao: 'Salvar como orçamento', role: 'sell.create', efeitos: [] },
    { key: 'orcamento', label: 'Orçamento', acao: null, role: null, efeitos: [] },
  ],
  papeisDoUsuario: ['sell.create'],
  executantes: [],
  permissoes: { editarPrecoItem: true },
  transportadoras: [],
};

/** Cabeçalhos do grid de itens, na ordem em que a tela os desenhou. */
function cabecalhosDoGrid(): string[] {
  const grid = screen.getByRole('table');
  return within(grid)
    .getAllByRole('columnheader')
    .map((th) => th.textContent?.trim() ?? '');
}

function montarCom(preferencia: string[]) {
  window.localStorage.setItem(CHAVE_LOCALSTORAGE, JSON.stringify(preferencia));
  render(<SellsCreateV3 businessId={1} cena={cena} />);
}

describe('grid de itens · a preferência de colunas chega na tela', () => {
  beforeEach(() => {
    window.localStorage.clear();
    vi.restoreAllMocks();
  });

  it('UC-V367 · desenha exatamente as colunas escolhidas, na ordem escolhida', () => {
    montarCom(['produto', 'qtd', 'preco', 'total', 'categoria']);

    // "Ações" fecha o grid e não entra na preferência — é operação, não informação.
    expect(cabecalhosDoGrid()).toEqual([
      'Produto / serviço', 'Quant.', 'R$ Valor', 'R$ Total', 'Categoria', 'Ações',
    ]);
  });

  it('UC-V367 · trocar a ordem da preferência troca a ordem do cabeçalho', () => {
    montarCom(['produto', 'total', 'qtd', 'preco']);

    expect(cabecalhosDoGrid()).toEqual([
      'Produto / serviço', 'R$ Total', 'Quant.', 'R$ Valor', 'Ações',
    ]);
  });

  it('UC-V367 · o grid herda o saneamento: coluna FIXA omitida volta, na frente', () => {
    // `preco` é fixa e foi deixada de fora — `sanearColunas` reinsere (UC-V364).
    // O que este caso prova é que o GRID obedece esse saneamento, não a preferência crua:
    // sem isso a tela abriria sem a coluna de valor.
    montarCom(['produto', 'qtd', 'total']);

    expect(cabecalhosDoGrid()).toEqual([
      'R$ Valor', 'Produto / serviço', 'Quant.', 'R$ Total', 'Ações',
    ]);
  });

  it('UC-V367 · coluna fora da preferência NÃO aparece', () => {
    montarCom(['produto', 'qtd', 'preco', 'total']);

    const cabecalhos = cabecalhosDoGrid();
    expect(cabecalhos).not.toContain('Desc. %');
    expect(cabecalhos).not.toContain('Acrésc. %');
  });

  it('UC-V367 · a coluna ligada mostra o dado da LINHA, não o rótulo sozinho', () => {
    montarCom(['produto', 'qtd', 'total', 'categoria', 'ean']);

    // categoria e EAN vêm do catálogo, casados por SKU
    expect(screen.getByRole('cell', { name: 'Lonas' })).toBeTruthy();
    expect(screen.getByRole('cell', { name: '7899123400015' })).toBeTruthy();
  });

  it('UC-V368 · coluna sem fonte na venda mostra travessão, não texto vazio', () => {
    // `ncm` está no catálogo de colunas mas a linha da venda ainda não carrega o dado
    montarCom(['produto', 'qtd', 'total', 'ncm']);

    expect(cabecalhosDoGrid()).toContain('NCM');
    expect(screen.getByRole('cell', { name: '—' })).toBeTruthy();
  });

  it('sem preferência salva, o grid abre no padrão — e o TOTAL fecha a linha', () => {
    render(<SellsCreateV3 businessId={1} cena={cena} />);

    // A ordem importa e é contrato: o total da linha é o fim da conta. Ver o aviso no
    // topo de `COLUNAS` — agrupar as fixas primeiro punha `R$ Total` no 4º lugar, e foi
    // isso que a regressão de pixel de `Sells/CreateV3` (2,63%) denunciou.
    expect(cabecalhosDoGrid()).toEqual([
      'Produto / serviço', 'Quant.', 'R$ Valor', 'Desc. %', 'Acrésc. %', 'R$ Total', 'Ações',
    ]);
    // controle: o padrão do domínio é o que a tela desenhou (menos a coluna de Ações)
    expect(colunasPadrao()).toHaveLength(cabecalhosDoGrid().length - 1);
  });

  it('a célula editável continua editável — e é ela que carrega o rótulo acessível', () => {
    montarCom(['produto', 'qtd', 'total']);

    const qtd = screen.getByLabelText('Quant. — Lona 440g branca fosca') as HTMLInputElement;
    expect(qtd.value).toBe('12,50');
    expect(qtd.readOnly).toBe(false);
  });
});
