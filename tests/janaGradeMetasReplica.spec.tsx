// UC-JPAIN-32 — a grade e o card de META são RÉPLICA da `.jm-metas-grid` / `.jm-meta` da âncora:
// 4 colunas por auto-fit, card compacto (gap 8px · padding 12/13) e o valor em mono 20/700.
//
// Âncora: `prototipo-ui/cowork/Wagner/jana-merge.css` — âncora de SÍMBOLO
// (`grep -n "jm-metas-grid" prototipo-ui/cowork/Wagner/jana-merge.css`):
//
//   .jm-metas-grid   grid · repeat(auto-fit, minmax(232px, 1fr)) · gap 10px
//   .jm-meta         flex-column · gap 8px · padding 12px 13px · border-radius 12px
//   .jm-meta-v b     font-family var(--font-mono) · 20px · 700 · tabular-nums
//
// O que divergia, MEDIDO em 2026-09-21 (Chrome, staging autenticado, dark nos dois lados,
// viewport 1440×900, container de grade 1117px IDÊNTICO nos dois — logo a diferença não
// vinha de largura disponível):
//
//   | campo          | âncora              | prod (antes)        |
//   | colunas        | 4 (271,75px)        | 3 (361,66px)        |
//   | gap da grade   | 10px                | 16px (`gap-4`)      |
//   | altura do card | 122px               | 236px               |
//   | padding        | 12px 13px           | 24px 0 (`py-6`)     |
//   | gap interno    | 8px                 | 24px (`gap-6`)      |
//   | valor          | mono 20px/700       | sans 24px/600       |
//
// ⚠️ POR QUE `<Grid fit="sm">` E NÃO a classe crua `minmax(232px,1fr)`. O dono de grade
// auto-fit neste repo é o `Components/layout/grid.tsx` (ADR 0253), e o docblock dele é
// explícito: "largura mínima vem de token (enum), não de px solto no call-site". Escrever
// a classe crua seria mais literal e MENOS correto — é autorar paralelo a um dono que
// existe. O token `sm` é 14rem (224px) contra os 232px da âncora, e `gap={2}` é 8px contra
// 10px. Medido no container de 1117px: a âncora dá 4 colunas de 271,8px e este dá 4 de
// 273,2px — 1,4px de diferença (0,5%), e o NÚMERO DE COLUNAS, que é o que se enxerga, é o
// mesmo. Fechar os 1,4px exigiria token novo no DS, que é decisão [W], não desta tela.
//
// ⚠️ O QUE ESTE UC NÃO TRAVA, e é residual DECLARADO: o card fica em ~198px contra os 122px
// da âncora. A diferença é CONTEÚDO que a âncora não tem, não forma — medido no DOM:
// Sparkline (32px + 8 de gap) e o Badge de unidade no header (22px), ~62 dos ~76px. Removê-los
// é decisão de produto, não de réplica visual, e por isso não entra aqui.
//
// Precedência de FORMA: protótipo > teste > casos > charter > SPEC (ADR UI-0029), sob
// ADR 0388 §D-1 (réplica LOCAL — nada disto é imposto a outra tela).
//
// Método (ADR 0258 — "todo ✅ tem que ter sido visto falhar"): jsdom não computa classe
// Tailwind, então o que se assere é a CLASSE, que é o que o bundle traduz. O `describe` de
// controle negativo prova que os detectores acusam quando a classe ANTIGA está presente.
import * as React from 'react';
import { describe, it, expect, vi, afterEach } from 'vitest';
import { render, cleanup } from '@testing-library/react';

vi.mock('@inertiajs/react', () => ({
  usePage: () => ({ url: '/ia', props: { shell: { menu: [] } } }),
  router: { reload: () => {}, visit: () => {} },
  Link: ({ href, children, ...rest }: any) => (
    <a href={href} {...rest}>
      {children}
    </a>
  ),
}));

// Mesmo stub do `janaMetaCardRodape.spec.tsx`: o cockpit PRECISA repassar `aposKpis` (a
// seção METAS é prop dele, não irmã no JSX) e o `SectionTitle` precisa renderizar children,
// senão os botões do cabeçalho somem e o card não chega a montar.
vi.mock('@/Pages/Jana/_components/JanaCockpit', () => ({
  default: ({ aposKpis }: { aposKpis?: React.ReactNode }) => <div data-stub="cockpit">{aposKpis}</div>,
  SectionTitle: ({ children }: { children?: React.ReactNode }) => (
    <h2 data-stub="section-title">{children}</h2>
  ),
}));
vi.mock('@/Pages/Jana/_components/JanaConfigDrawer', () => ({ default: () => null }));
vi.mock('@/Pages/Jana/_components/JanaMetaDrawer', () => ({ default: () => null }));
vi.mock('@/Pages/Jana/_components/JanaMetaNovaDrawer', () => ({ default: () => null }));
vi.mock('@/Pages/Jana/_components/FabJana', () => ({ default: () => null }));
vi.mock('@/Pages/Jana/_components/JanaAreaHeader', () => ({ JanaAreaHeader: () => <header /> }));
vi.mock('@/Pages/Jana/_components/JanaPlanoBadge', () => ({ JanaPlanoBadge: () => null }));
vi.mock('@/Pages/Jana/_components/useJanaPro', () => ({ useJanaPro: () => false }));

import Dashboard from '@/Pages/Jana/Index';
import type { Meta } from '@/Pages/Jana/_components/metaFormat';

afterEach(cleanup);

/** Unidade `un` de propósito: valor monetário não vai pro git (Tier 0 · `memory/proibicoes.md`). */
const META_BASE: Omit<Meta, 'id' | 'slug' | 'nome' | 'periodo_atual' | 'ultima_apuracao' | 'projecao' | 'farol'> = {
  unidade: 'un',
  tipo_agregacao: 'soma',
  apuracoes_recentes: [],
};

const periodo = { data_ini: '2026-09-01', data_fim: '2026-09-30', valor_alvo: 200, trajetoria: 'linear' };

const METAS: Meta[] = [
  {
    ...META_BASE,
    id: 1,
    slug: 'meta-a',
    nome: 'Meta A',
    farol: 'verde',
    periodo_atual: periodo,
    ultima_apuracao: { data_ref: '2026-09-07', valor_realizado: 64 },
    projecao: { progresso: 0.23, projetado: 278, desvio_pct: 12 },
  },
  {
    ...META_BASE,
    id: 2,
    slug: 'meta-b',
    nome: 'Meta B',
    farol: 'amarelo',
    periodo_atual: periodo,
    ultima_apuracao: { data_ref: '2026-09-07', valor_realizado: 150 },
    projecao: null,
  },
];

function renderPainel(metas: Meta[] = METAS) {
  const { container } = render(
    <Dashboard
      metas={metas}
      sellKpis={undefined as never}
      insightsAggregates={undefined as never}
      coworkAggregates={undefined as never}
      janaContext={{ businessId: 1, businessName: 'WR2 Sistemas', userName: null }}
    />,
  );
  const cards = Array.from(container.querySelectorAll<HTMLElement>('button[aria-label^="Abrir a meta"]'));
  if (cards.length !== metas.length) {
    throw new Error(`esperava ${metas.length} card(s) de meta, renderizou ${cards.length}`);
  }
  return { container, cards };
}

/** A grade é o PAI do primeiro card — resolvida pelo DOM, não por seletor adivinhado. */
function grade(cards: HTMLElement[]): HTMLElement {
  const g = cards[0].parentElement;
  if (!g) throw new Error('card de meta sem elemento pai — a grade sumiu do DOM');
  return g;
}

describe('UC-JPAIN-32 — a grade e o card de meta replicam a âncora', () => {
  it('a grade usa auto-fit por TOKEN do `Grid`, não breakpoint fixo', () => {
    const { cards } = renderPainel();
    const cls = grade(cards).className;
    expect(cls).toContain('grid-cols-[repeat(auto-fit,minmax(14rem,1fr))]');
    // o que havia antes, e que a âncora refuta: breakpoint fixo do Tailwind
    expect(cls).not.toContain('sm:grid-cols-2');
    expect(cls).not.toContain('xl:grid-cols-3');
  });

  it('o gap da grade é 8px (`gap-2`), não os 16px do `gap-4`', () => {
    const { cards } = renderPainel();
    const cls = grade(cards).className;
    expect(cls).toMatch(/\bgap-2\b/);
    expect(cls).not.toMatch(/\bgap-4\b/);
  });

  it('o card troca o `gap-6 py-6` do Card canon por `gap-2 py-3` (`.jm-meta`: gap 8 · padding 12/13)', () => {
    const { cards } = renderPainel();
    const inner = cards[0].firstElementChild as HTMLElement;
    expect(inner.className).toMatch(/\bgap-2\b/);
    expect(inner.className).toMatch(/\bpy-3\b/);
    expect(inner.className).not.toMatch(/\bgap-6\b/);
    expect(inner.className).not.toMatch(/\bpy-6\b/);
    // `rounded-xl` do Card canon já É o `border-radius:12px` da âncora — não se mexe.
    expect(inner.className).toMatch(/\brounded-xl\b/);
  });

  it('header e conteúdo usam o padding-x de 13px da âncora, no lugar do `px-6 pl-5`', () => {
    const { cards } = renderPainel();
    const header = cards[0].querySelector<HTMLElement>('[class*="card-header"]');
    const content = cards[0].querySelector<HTMLElement>('[data-slot="card-content"]')
      ?? (cards[0].querySelector('.space-y-2') as HTMLElement | null);
    expect(header, 'header do card não encontrado').toBeTruthy();
    expect(content, 'conteúdo do card não encontrado').toBeTruthy();
    expect(header!.className).toContain('px-[13px]');
    expect(content!.className).toContain('px-[13px]');
    expect(header!.className).not.toMatch(/\bpl-5\b/);
    expect(content!.className).not.toMatch(/\bpl-5\b/);
  });

  it('o ritmo interno do conteúdo é 8px (`space-y-2`), como o `gap` da `.jm-meta`', () => {
    const { cards } = renderPainel();
    const content = cards[0].querySelector<HTMLElement>('.space-y-2');
    expect(content, 'conteúdo do card não usa space-y-2').toBeTruthy();
    expect(cards[0].querySelector('.space-y-3')).toBeNull();
  });

  it('o wrapper da seção não tem `pt-6` — o ritmo entre seções vem do container', () => {
    const { cards } = renderPainel();
    // Sobe do CARD até o wrapper da seção. Não se procura pelo h2: o mock do
    // `SectionTitle` (acima) não repassa o `data-contract`, então buscar por ele
    // devolveria null e o assert passaria por ausência — falso verde.
    const wrapper = cards[0].closest('div.space-y-6') as HTMLElement | null;
    expect(wrapper, 'wrapper `space-y-6` da seção METAS não encontrado').toBeTruthy();
    // MEDIDO em 2026-09-21: com `pt-6` o trecho KPIs→"METAS ATIVAS" dava 46px contra 24px
    // da âncora. O padding somava por cima do ritmo que o container já aplica.
    expect(wrapper!.className).not.toContain('pt-6');
    expect(cards.length).toBeGreaterThan(0);
  });

  it('o valor é mono 20px/700 (`.jm-meta-v b`), não sans 24px/600', () => {
    const { cards } = renderPainel();
    const valor = cards[0].querySelector<HTMLElement>('.font-mono.text-\\[20px\\]');
    expect(valor, 'o valor do card não está em mono 20px').toBeTruthy();
    expect(valor!.className).toMatch(/\bfont-bold\b/);
    expect(valor!.className).toMatch(/\btabular-nums\b/);
    // o que havia antes
    expect(cards[0].querySelector('.text-2xl')).toBeNull();
    expect(valor!.className).not.toMatch(/\bfont-semibold\b/);
  });
});

// ── CONTROLE NEGATIVO (ADR 0258) ────────────────────────────────────────────────────────
// Prova que os detectores acima ACUSAM quando a classe antiga está presente. Sem isto, um
// assert que nunca viu vermelho é indistinguível de um assert que não mede nada.
describe('UC-JPAIN-32 · controle negativo — os detectores acusam a forma ANTIGA', () => {
  const ANTIGO_GRADE = 'grid gap-4 sm:grid-cols-2 xl:grid-cols-3';
  const ANTIGO_CARD = 'flex flex-col gap-6 rounded-xl border py-6';
  const ANTIGO_VALOR = 'text-2xl font-semibold tabular-nums';

  it('a grade antiga falharia no detector de auto-fit e no de gap', () => {
    expect(ANTIGO_GRADE).not.toContain('grid-cols-[repeat(auto-fit,minmax(14rem,1fr))]');
    expect(ANTIGO_GRADE).toContain('sm:grid-cols-2');
    expect(ANTIGO_GRADE).toMatch(/\bgap-4\b/);
  });

  it('o card antigo falharia no detector de `gap-2 py-3`', () => {
    expect(ANTIGO_CARD).toMatch(/\bgap-6\b/);
    expect(ANTIGO_CARD).toMatch(/\bpy-6\b/);
    expect(ANTIGO_CARD).not.toMatch(/\bpy-3\b/);
  });

  it('o wrapper antigo (`space-y-6 pt-6`) falharia no detector de padding', () => {
    expect('space-y-6 pt-6').toContain('pt-6');
    expect('space-y-6').not.toContain('pt-6');
  });

  it('o valor antigo falharia no detector de mono 20/700', () => {
    expect(ANTIGO_VALOR).toContain('text-2xl');
    expect(ANTIGO_VALOR).not.toContain('font-mono');
    expect(ANTIGO_VALOR).not.toMatch(/\bfont-bold\b/);
  });
});
