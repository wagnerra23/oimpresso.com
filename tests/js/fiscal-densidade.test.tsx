/**
 * A densidade da tabela é preferência do OPERADOR, não da tela.
 *
 * @covers-us UC-FNFE-14 UC-FNFSE-05
 *
 * POR QUE ESTE TESTE É DE RENDER (e não estático): o contrato é "escolhi compacto
 * na lista de NF-e, abro a NFS-e e ela já vem compacta". Um assert procurando o
 * nome do componente nos `.tsx` provaria que o import foi ESCRITO — presença, não
 * comportamento (LC-11), e ficaria verde no instante em que alguém digitasse a
 * linha. Aqui a segunda tela é montada DEPOIS de a primeira ser desmontada, e a
 * asserção lê a classe que o CSS de fato consome. Se a preferência não atravessar,
 * o caso fica vermelho.
 *
 * O ESTADO QUE ISTO CORRIGE (medido em origin/main d23bc3df34): o controle existia
 * só no Cockpit e guardava a escolha em `useState('comfort')` — estado efêmero, que
 * morre ao trocar de tela. NF-e e NFS-e não tinham controle nenhum.
 *
 * ÂNCORA: na fonte de design as três telas são a MESMA função (`FxNotasPage`,
 * chamada com `preset` diferente — fiscal-page.jsx:346,541-543), e a escolha
 * persiste em `fxLS("oimpresso.fiscal.densidade")` (:358,363). Lá o compartilhamento
 * é grátis porque há um dono só; aqui a produção separou em três arquivos, então a
 * propriedade precisa ser defendida.
 *
 * LIMITE HONESTO: o jsdom não faz a navegação HTTP entre as duas rotas — o que este
 * arquivo prova é a parte que carrega a preferência (a mesma origem, o mesmo
 * storage, a tela nova lendo o que a anterior gravou). A troca de página real, com
 * o Inertia no meio, é olho humano no smoke (R1).
 */
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
import { cleanup, fireEvent, render, screen } from '@testing-library/react';
import { readFileSync } from 'node:fs';
import { resolve } from 'node:path';

vi.mock('@inertiajs/react', () => ({
  Head: () => null,
  Link: ({ children }: { children: React.ReactNode }) => <a href="#">{children}</a>,
  Deferred: ({ children }: { children: React.ReactNode }) => children,
  router: { visit: vi.fn(), get: vi.fn(), post: vi.fn() },
}));
vi.mock('@/Layouts/AppShellV2', () => ({
  default: ({ children }: { children: React.ReactNode }) => <div>{children}</div>,
}));

import Nfe from '@/Pages/Fiscal/Nfe';
import Nfse from '@/Pages/Fiscal/Nfse';
import { DENSIDADE_STORAGE_KEY } from '@/Pages/Fiscal/_components/DensidadeToggle';

const raiz = (rel: string) => resolve(__dirname, '../..', rel);
const ler = (rel: string) => readFileSync(raiz(rel), 'utf8');

const cenaNfe = {
  filters: { search: '', status: 'todas' as const, tab: 'saida_nfe' as const },
  counts: {
    total: 1, nfe: 1, nfce: 0, autorizadas: 1,
    rejeitadas: 0, processando: 0, canceladas: 0, cancelaveis: 0,
  },
  sefazCodes: { 100: { tone: 'ok' as const, label: 'Autorizada', hint: 'Uso autorizado' } },
  rows: {
    data: [{
      id: 1, num: 8425, serie: 1, modelo: 55, key: '3526' + '0'.repeat(40),
      status: 'autorizada', cstat: 100, motivo: null, value: 1250.4,
      emittedAtIso: '2026-09-02T10:00:00-03:00', when: '02/09 10:00',
      transactionId: null, dest: 'Rota Livre Comercio', cnpj: '12345678000199',
      cpf: null, uf: 'SC', items: 3, cancelavel: false,
    }],
    meta: { current_page: 1, last_page: 1, total: 1, per_page: 50 },
  },
};

const cenaNfse = {
  filters: { search: '', status: 'todas' as const, mes: '2026-09' },
  counts: {
    total: 1, autorizadas: 1, rejeitadas: 0,
    processando: 0, canceladas: 0, faturamento: 980.5,
  },
  rows: {
    data: [{
      id: 1, num: '2026/431', codigoVerificacao: 'A1B2C3', tomador: 'Rota Livre Comercio',
      documentoTomador: '12345678000199', municipio: 'Gravatal', codServico: '14.01',
      aliquotaIss: 3, valueServico: 980.5, valueIss: 29.4, status: 'authorized',
      errorMsg: null, emittedAtIso: '2026-09-02T10:00:00-03:00', when: '02/09 10:00',
    }],
    meta: { current_page: 1, last_page: 1, total: 1, per_page: 50 },
  },
};

/** A classe que o CSS consome: `.fx-density-<x> .fx-table tbody td`. */
const densidadeNaTela = (): string | null => {
  const tabela = document.querySelector('.fx-table');
  const wrapper = tabela?.closest('[class*="fx-density-"]');
  return wrapper?.className.match(/fx-density-([a-z]+)/)?.[1] ?? null;
};

beforeEach(() => localStorage.clear());
afterEach(() => cleanup());

describe('a densidade acompanha o operador entre as telas de notas', () => {
  it('UC-FNFE-14 · escolher Compacto na NF-e faz a NFS-e abrir compacta', () => {
    render(<Nfe {...cenaNfe} />);
    expect(densidadeNaTela(), 'a NF-e não abriu no padrão').toBe('comfort');

    fireEvent.click(screen.getByRole('button', { name: 'Densidade compacta' }));
    expect(densidadeNaTela(), 'o clique não repintou a própria tela').toBe('compact');

    // a primeira tela SAI de cena antes de a segunda entrar — é isso que torna a
    // asserção seguinte uma prova de travessia, e não de estado compartilhado em memória
    cleanup();
    render(<Nfse {...cenaNfse} />);

    expect(densidadeNaTela(), 'a NFS-e ignorou a escolha feita na NF-e').toBe('compact');
  });

  it('UC-FNFSE-05 · escolher Relaxado na NFS-e faz a NF-e abrir relaxada (o caminho de volta)', () => {
    render(<Nfse {...cenaNfse} />);
    fireEvent.click(screen.getByRole('button', { name: 'Densidade relaxada' }));
    expect(densidadeNaTela()).toBe('relax');

    cleanup();
    render(<Nfe {...cenaNfe} />);

    expect(densidadeNaTela(), 'a NF-e ignorou a escolha feita na NFS-e').toBe('relax');
  });

  it('UC-FNFE-14 · a escolha vai parar na chave que a fonte de design declara', () => {
    render(<Nfe {...cenaNfe} />);
    fireEvent.click(screen.getByRole('button', { name: 'Densidade compacta' }));

    const prototipo = ler('prototipo-ui/cowork/fiscal-page.jsx');
    const chaveDoDesign = prototipo.match(/fxLS\(\s*"([^"]*densidade[^"]*)"/)?.[1];

    expect(chaveDoDesign, 'a fonte de design não declara mais chave de densidade').toBeTruthy();
    expect(DENSIDADE_STORAGE_KEY, 'produção e protótipo divergiram na chave').toBe(chaveDoDesign);
    expect(localStorage.getItem(chaveDoDesign as string)).toBe('compact');
  });

  it('UC-FNFSE-05 · storage indisponível não derruba a tela (janela privada)', () => {
    // Em janela privada o próprio getter lança. Uma preferência cosmética não pode
    // levar a lista de notas junto — daí o try/catch no hook.
    const original = Object.getOwnPropertyDescriptor(window, 'localStorage');
    Object.defineProperty(window, 'localStorage', {
      configurable: true,
      get() { throw new DOMException('acesso negado', 'SecurityError'); },
    });

    try {
      expect(() => render(<Nfse {...cenaNfse} />)).not.toThrow();
      expect(densidadeNaTela(), 'sem storage, a tela deve cair no padrão').toBe('comfort');
      expect(() => fireEvent.click(screen.getByRole('button', { name: 'Densidade compacta' }))).not.toThrow();
      expect(densidadeNaTela(), 'a escolha deve valer nesta sessão mesmo sem storage').toBe('compact');
    } finally {
      if (original) Object.defineProperty(window, 'localStorage', original);
    }
  });

  it('UC-FNFE-14 · nenhuma tela de notas mantém densidade própria — inclui o Cockpit', () => {
    // Complemento ESTÁTICO, e o motivo está declarado: os dois casos de render acima
    // cobrem NF-e e NFS-e de ponta a ponta, mas o Cockpit recebe ~15 props de payload
    // e montá-lo aqui custaria uma cena maior que o próprio contrato. O que se perde
    // sem render é a prova do repinte; o que NÃO se perde é a condição que faz a
    // preferência atravessar — e é ela que este caso trava, na tela que era justamente
    // a dona do defeito (o Cockpit guardava a escolha em `useState('comfort')`).
    const comFontePropria = ['Cockpit.tsx', 'Nfe.tsx', 'Nfse.tsx'].filter((tela) => {
      const codigo = ler(`resources/js/Pages/Fiscal/${tela}`);
      return /useState[^;\n]*(compact|comfort|relax)/.test(codigo)
        || codigo.includes(DENSIDADE_STORAGE_KEY);
    });

    expect(comFontePropria, 'estas telas furam a preferência compartilhada').toEqual([]);
  });

  it('UC-FNFSE-05 · todo valor de densidade tem classe correspondente no CSS', () => {
    // Dois arquivos, dois donos: um valor novo no TS sem classe no CSS renderiza
    // tabela sem estilo, e nenhum teste de render pegaria isso sozinho.
    const componente = ler('resources/js/Pages/Fiscal/_components/DensidadeToggle.tsx');
    const css = ler('resources/css/fiscal-cockpit.css');

    const tipo = componente.match(/export type Densidade = ([^;]+);/)?.[1];
    expect(tipo, 'o tipo Densidade sumiu ou mudou de forma').toBeTruthy();

    const valores = [...(tipo as string).matchAll(/'([a-z]+)'/g)].map((m) => m[1]);
    expect(valores.length, 'o tipo Densidade ficou sem valores').toBeGreaterThan(0);

    for (const valor of valores) {
      expect(css, `'${valor}' não tem .fx-density-${valor} em fiscal-cockpit.css`)
        .toContain(`.fx-density-${valor} `);
    }
  });
});
