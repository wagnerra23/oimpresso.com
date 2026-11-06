// Estado de ERRO da carga deferida — as 5 telas do Patrimônio.
//   UC-PAT-13 (Index) · UC-BENS-15 (Bens) · UC-ALOC-09 (Alocações) · UC-MANU-07 (Manutenções) ·
//   UC-CFG-06 (Configurações)
//
// O QUE MEDE: quando a visita deferida que PEDIU a prop da tela termina e a prop não veio (500,
// 503, rede), a tela troca o esqueleto pelo estado de erro do protótipo (`role="alert"`, título
// "Não consegui carregar …", "Tentar de novo"); "Tentar de novo" pede SÓ aquela prop, sem o
// diálogo de erro do Inertia; quando a prop chega, o erro some; e a falha da carga ORIGINAL não
// abre o modal de erro do Inertia por cima (medido em produção 2026-11-06: abria nas 5 telas).
//
// CONTROLES, para o caso não passar por um harness que nunca renderiza o erro nem a tela:
//   · a mesma visita terminando COM a prop não acende o erro;
//   · visita que pediu OUTRA prop não acende o erro desta tela;
//   · antes de qualquer visita terminar, a tela mostra o esqueleto (sem alerta).
//
// O `router` é falso mas fiel ao que o hook usa do real: `on(nome, cb)` devolve o desinscritor, e
// `finish` chega com `detail.visit.only`. O `<Deferred>` falso mostra o fallback enquanto a prop
// está `undefined` — o mesmo critério do componente real (`@inertiajs/react` 3.6.1).

import { describe, it, expect, afterEach, beforeEach, vi } from 'vitest';
import { render, screen, cleanup, fireEvent, act } from '@testing-library/react';

type Ouvinte = (e: { detail: { visit: { only?: string[] } } }) => boolean | void;
const ouvintes: Record<string, Set<Ouvinte>> = {};
const reload = vi.fn();
const pagina: { props: Record<string, unknown> } = { props: {} };

vi.mock('@inertiajs/react', () => ({
  Deferred: ({ children, data, fallback }: any) => {
    const chaves: string[] = Array.isArray(data) ? data : [data];
    return chaves.every((k) => pagina.props[k] !== undefined) ? <>{children}</> : <>{fallback}</>;
  },
  usePage: () => ({ props: pagina.props }),
  useForm: (inicial: Record<string, unknown>) => ({
    data: inicial, setData: vi.fn(), post: vi.fn(), processing: false, transform: vi.fn(), errors: {},
  }),
  router: {
    on: (nome: string, cb: Ouvinte) => {
      (ouvintes[nome] ??= new Set()).add(cb);
      return () => ouvintes[nome].delete(cb);
    },
    reload: (...a: unknown[]) => reload(...a),
    get: vi.fn(), post: vi.fn(), put: vi.fn(), delete: vi.fn(), visit: vi.fn(),
  },
  Link: ({ href, children, ...rest }: any) => <a href={href} {...rest}>{children}</a>,
}));
vi.mock('@/Layouts/AppShellV2', () => ({ default: ({ children }: any) => <div>{children}</div> }));
vi.mock('@/Pages/Patrimonio/_shared/PatrimonioSubNav', () => ({ default: () => null }));
vi.mock('@/Components/PageHeader', () => ({ PageHeader: () => null }));

import Index from '@/Pages/Patrimonio/Index';
import Bens from '@/Pages/Patrimonio/Bens';
import Alocacoes from '@/Pages/Patrimonio/Alocacoes';
import Manutencoes from '@/Pages/Patrimonio/Manutencoes';
import Configuracoes from '@/Pages/Patrimonio/Configuracoes';

const emitir = (nome: 'start' | 'finish', only?: string[]) =>
  act(() => {
    ouvintes[nome]?.forEach((cb) => cb({ detail: { visit: { only } } }));
  });

// O core do Inertia abre o modal de erro se NENHUM ouvinte devolver `false` (`onGlobalEvent`
// chama `preventDefault()` nesse caso, e `handleNonInertiaResponse` só mostra o diálogo se o
// evento não foi cancelado). Aqui: `true` = o modal abriria.
const modalAbriria = (nome: 'httpException' | 'networkError') =>
  ![...(ouvintes[nome] ?? [])].some((cb) => cb({ detail: { visit: {} } }) === false);

const vazio = { data: [], total: 0, current_page: 1, last_page: 1, from: 0, to: 0, links: [] };

interface Tela {
  uc: string;
  nome: string;
  chaves: string[];
  /** A carga DESTA tela, para o caso que mostra o erro sumindo quando a prop chega. */
  carga: Record<string, unknown>;
  titulo: string;
  montar: () => React.ReactElement;
}

const TELAS: Tela[] = [
  {
    uc: 'UC-PAT-13',
    nome: 'Index (Painel)',
    chaves: ['kpis', 'porCategoria', 'garantia', 'manutencoes', 'meusBens'],
    carga: { kpis: null, porCategoria: [], garantia: [], manutencoes: [], meusBens: null },
    titulo: 'Não consegui carregar o patrimônio',
    montar: () => (
      <Index
        {...({ is_admin: false, pode: { ver: false, criar: false }, apurado_em: '2026-11-05T09:00:00-03:00' } as any)}
        {...(pagina.props as any)}
      />
    ),
  },
  {
    uc: 'UC-BENS-15',
    nome: 'Bens',
    chaves: ['bens'],
    carga: { bens: vazio },
    titulo: 'Não consegui carregar os bens',
    montar: () => (
      <Bens
        bens={pagina.props.bens as any}
        filtros={{}}
        opcoes={{ locais: {}, categorias: {}, tipos_compra: {} }}
        formato_data="d/m/Y"
        permissoes={{ criar: false, editar: false, excluir: false, manutencao: false }}
      />
    ),
  },
  {
    uc: 'UC-ALOC-09',
    nome: 'Alocações',
    chaves: ['alocacoes'],
    carga: { alocacoes: vazio },
    titulo: 'Não consegui carregar as alocações',
    montar: () => (
      <Alocacoes
        {...({
          alocacoes: pagina.props.alocacoes,
          filtros: { situacao: 'ativas' },
          permissoes: { alocar: false, editar: false, excluir: false, devolver: false },
          formato_data: 'd/m/Y',
          hora_12: false,
        } as any)}
      />
    ),
  },
  {
    uc: 'UC-MANU-07',
    nome: 'Manutenções',
    chaves: ['manutencoes'],
    carga: { manutencoes: vazio },
    titulo: 'Não consegui carregar as manutenções',
    montar: () => (
      <Manutencoes
        {...({
          manutencoes: pagina.props.manutencoes,
          filtros: {},
          opcoes: { status: {}, prioridades: {}, responsaveis: {} },
          permissoes: { vejo_todas: true },
        } as any)}
      />
    ),
  },
  {
    uc: 'UC-CFG-06',
    nome: 'Configurações',
    chaves: ['usuarios'],
    carga: { usuarios: [] },
    titulo: 'Não consegui carregar os destinatários',
    montar: () => (
      <Configuracoes
        settings={{
          asset_code_prefix: 'AST', allocation_code_prefix: 'ALO', revoke_code_prefix: 'DEV', asset_maintenance_prefix: 'MAN',
          send_for_maintenence_recipients: [], enable_asset_send_for_maintenance_email: false, enable_asset_assigned_for_maintenance_email: false,
        }}
        templates={{ send_for_maintenance: { subject: '', email_body: '' }, assigned_for_maintenance: { subject: '', email_body: '' } }}
        usuarios={pagina.props.usuarios as any}
        tags={{ send_for_maintenance: [], assigned_for_maintenance: [] }}
      />
    ),
  },
];

beforeEach(() => {
  pagina.props = {};
  reload.mockReset();
  for (const k of Object.keys(ouvintes)) delete ouvintes[k];
});
afterEach(() => cleanup());

describe.each(TELAS)('$uc · $nome — a carga deferida que falha vira estado de erro', (tela) => {
  it(`${tela.uc}: antes da carga terminar, esqueleto e nenhum alerta (controle)`, () => {
    render(tela.montar());
    expect(screen.queryByText(tela.titulo)).toBeNull();
    expect(document.querySelector('[data-contract="estado-erro"]')).toBeNull();
  });

  it(`${tela.uc}: a visita que pediu a prop terminou sem ela → alerta "${tela.titulo}" com Tentar de novo`, () => {
    render(tela.montar());
    emitir('finish', tela.chaves);

    const alerta = screen.getByRole('alert');
    expect(alerta.getAttribute('data-contract')).toBe('estado-erro');
    expect(alerta.textContent).toContain(tela.titulo);
    expect(screen.getByRole('button', { name: 'Tentar de novo' })).not.toBeNull();
  });

  it(`${tela.uc}: Tentar de novo pede só a prop da tela, sem o diálogo de erro do Inertia`, () => {
    render(tela.montar());
    emitir('finish', tela.chaves);

    fireEvent.click(screen.getByRole('button', { name: 'Tentar de novo' }));

    expect(reload).toHaveBeenCalledTimes(1);
    const opcoes = reload.mock.calls[0][0] as { only: string[]; onHttpException: () => unknown; onNetworkError: () => unknown };
    expect(opcoes.only).toEqual(tela.chaves);
    expect(opcoes.onHttpException()).toBe(false);
    expect(opcoes.onNetworkError()).toBe(false);
  });

  it(`${tela.uc}: quando a nova tentativa traz a prop, o erro some`, () => {
    const { rerender } = render(tela.montar());
    emitir('finish', tela.chaves);
    expect(screen.queryByRole('alert')).not.toBeNull();

    emitir('start', tela.chaves);
    pagina.props = { ...tela.carga };
    rerender(tela.montar());
    emitir('finish', tela.chaves);

    expect(document.querySelector('[data-contract="estado-erro"]')).toBeNull();
  });

  it(`${tela.uc}: a visita terminando COM a prop não acende o erro (controle)`, () => {
    pagina.props = { ...tela.carga };
    render(tela.montar());
    emitir('finish', tela.chaves);
    expect(document.querySelector('[data-contract="estado-erro"]')).toBeNull();
  });

  it(`${tela.uc}: a carga desta tela que falha não abre o modal de erro do Inertia por cima`, () => {
    render(tela.montar());
    emitir('start', tela.chaves);

    expect(modalAbriria('httpException')).toBe(false);
    expect(modalAbriria('networkError')).toBe(false);

    emitir('finish', tela.chaves);
    expect(screen.getByRole('alert').textContent).toContain(tela.titulo);
  });

  it(`${tela.uc}: fora da carga desta tela o modal do Inertia segue como era (controle)`, () => {
    render(tela.montar());
    expect(modalAbriria('httpException')).toBe(true);

    emitir('start', ['bem_detalhe']);
    expect(modalAbriria('httpException')).toBe(true);

    emitir('start', tela.chaves);
    emitir('finish', tela.chaves);
    expect(modalAbriria('httpException')).toBe(true);
    expect(modalAbriria('networkError')).toBe(true);
  });

  it(`${tela.uc}: visita que pediu OUTRA prop não acende o erro desta tela (controle)`, () => {
    render(tela.montar());
    emitir('finish', ['bem_detalhe']);
    expect(document.querySelector('[data-contract="estado-erro"]')).toBeNull();
  });
});
