// UC-JPAIN-33 — o ritmo vertical entre as seções do Painel é o da âncora: 18px entre
// seções, e 6px na ÚNICA que foge (METAS), que foge na âncora também.
//
// Âncora: `chat-jana.css` — o 18px não vem de um container, vem de cada seção
// (`grep -n "margin-bottom: 18px" prototipo-ui/cowork/Wagner/chat-jana.css` → 4 hits:
// `.jc-brief`, `.jc-kpis`, `.jc-grid`, `.jc-acoes`). Aqui fica no `space-y` do cockpit,
// que produz o mesmo espaçamento com uma declaração em vez de quatro.
//
// MEDIDO em 2026-09-21 (Chrome, 2560, dark, os dois lados na mesma janela), comparando o
// espaço VISUAL entre blocos consecutivos — `top` do próximo menos `bottom` do atual, não
// a propriedade isolada, que engana quando há padding no meio:
//
//   de → para              âncora   prod (antes)
//   brief → kpis             18        16
//   kpis → metas             18        16
//   metas → h2 Análises       6        16      ← vai pro OUTRO lado
//   h2 → grade               10        10  ✅
//   grade → h2 Ações         18        16
//   h2 → ações               10        10  ✅
//
// ⚠️ Por que METAS precisa de `mb` próprio: sem ele, o `space-y-[18px]` levaria a seção de
// 16 → 18px e trocaria um erro de 10px por um de 12px, no sentido oposto. A correção
// "óbvia" (só trocar o container) piora essa transição.
//
// ⚠️ Por que os `h2` continuam em 10px e isso NÃO é descuido: o `space-y` gera
// `:where(.space-y-* > :not(:last-child))`, de especificidade **0** (medido no CSS servido
// em produção), então o `mb-2.5` do `SectionTitle` vence sem `!important` — exatamente como
// a `.jc-h2` (`margin: 6px 0 10px`) vence o ritmo do `.jc-page` na âncora.
//
// Precedência de FORMA: protótipo > teste > casos > charter > SPEC (ADR UI-0029), sob
// ADR 0388 §D-1 (réplica LOCAL).
//
// Método: jsdom não computa classe Tailwind, então o que se assere é a CLASSE — que é o
// que o bundle traduz. Detectores com controle positivo e negativo (ADR 0258).

import * as React from 'react';
import { describe, it, expect, vi, afterEach } from 'vitest';
import { render, cleanup } from '@testing-library/react';

afterEach(cleanup);

/** Classe presente como TOKEN inteiro — `space-y-4` não pode casar dentro de `space-y-[18px]`. */
function temClasse(el: HTMLElement, classe: string): boolean {
  return el.className.split(/\s+/).includes(classe);
}

describe('detector de classe — controle positivo e negativo (ADR 0258)', () => {
  it('SENSIBILIDADE: casa token inteiro e não casa substring', () => {
    const el = document.createElement('div');
    el.className = 'space-y-[18px]';
    expect(temClasse(el, 'space-y-[18px]')).toBe(true);
    // O risco real: `space-y-4` casaria por substring? Não deve — senão o assert da
    // métrica antiga passaria a acusar a nova.
    expect(temClasse(el, 'space-y-4')).toBe(false);

    const metas = document.createElement('div');
    metas.className = 'space-y-6 pt-6 mb-1.5';
    expect(temClasse(metas, 'mb-1.5')).toBe(true);
    expect(temClasse(metas, 'mb-1')).toBe(false); // `mb-1` dentro de `mb-1.5`
  });
});

describe('UC-JPAIN-33 — o cockpit usa o ritmo de 18px da âncora', () => {
  it('o container das seções é `space-y-[18px]`, não o `space-y-4` de antes', async () => {
    // ⚠️ `importActual`, não `import`: o `vi.mock` do fim deste arquivo sofre HOISTING e
    // vale para o módulo inteiro, então um `import` normal aqui receberia o STUB do
    // cockpit — e o assert mediria a className da `<div data-stub>`, não a do componente.
    // Custou uma rodada vermelha para aparecer; sem isto o caso é decorativo.
    const { default: JanaCockpit } = await vi.importActual<
      typeof import('@/Pages/Jana/_components/JanaCockpit')
    >('@/Pages/Jana/_components/JanaCockpit');
    const { container } = render(
      <JanaCockpit
        sellKpis={{ total: 1, paid: 0, due: 1, partial: 0, overdue: 1 }}
        insightsAggregates={{
          overdueCount: 1,
          overdueValue: 0,
          ageingBuckets: { '0-30d': 0, '30-90d': 0, '90-365d': 0, '>365d': 0 },
          methodsAgg: [],
          topClientes: [],
          topDevedor: null,
          ticketMedio: 0,
          totalAReceber: 0,
          churnOuro: [],
        }}
      />,
    );
    // O container é a raiz do cockpit — o mesmo pai de brief/KPIs/grade/ações.
    const raiz = container.firstElementChild as HTMLElement | null;
    expect(raiz, 'o cockpit não renderizou').not.toBeNull();
    expect(temClasse(raiz!, 'space-y-[18px]'), 'container sem o ritmo de 18px da âncora').toBe(
      true,
    );
    expect(temClasse(raiz!, 'space-y-4'), 'container ainda com o ritmo antigo de 16px').toBe(
      false,
    );
  });
});

// O wrapper de METAS vive no `Index.tsx` (entra no cockpit como `aposKpis`), então este
// bloco precisa da Page — mesmo stub de `janaMetaCardRodape.spec.tsx`, que já resolve as
// dependências pesadas. O stub do cockpit renderiza `aposKpis`, que é o que se mede aqui.
vi.mock('@/Pages/Jana/_components/JanaCockpit', () => ({
  default: ({ aposKpis }: { aposKpis?: React.ReactNode }) => <div data-stub="cockpit">{aposKpis}</div>,
  SectionTitle: ({ children }: { children?: React.ReactNode }) => (
    <h2 data-stub="section-title">{children}</h2>
  ),
}));
vi.mock('@/Pages/Jana/_components/JanaConfigDrawer', () => ({ default: () => null }));
vi.mock('@/Pages/Jana/_components/JanaMetaDrawer', () => ({ default: () => null }));
vi.mock('@/Pages/Jana/_components/FabJana', () => ({ default: () => null }));
vi.mock('@/Pages/Jana/_components/JanaAreaHeader', () => ({ JanaAreaHeader: () => <header /> }));
vi.mock('@/Pages/Jana/_components/JanaPlanoBadge', () => ({ JanaPlanoBadge: () => null }));
vi.mock('@/Pages/Jana/_components/useJanaPro', () => ({ useJanaPro: () => false }));

describe('UC-JPAIN-33 — METAS foge do ritmo, como foge na âncora', () => {
  it('o wrapper de METAS declara `mb-1.5` (6px) e vence o container', async () => {
    const { default: Dashboard } = await import('@/Pages/Jana/Index');
    const { container } = render(
      <Dashboard
        metas={[]}
        sellKpis={undefined as never}
        insightsAggregates={undefined as never}
        coworkAggregates={undefined as never}
        janaContext={{ businessId: 1, businessName: 'WR2 Sistemas', userName: null }}
      />,
    );
    const stub = container.querySelector<HTMLElement>('[data-stub="cockpit"]');
    expect(stub, 'o stub do cockpit não renderizou').not.toBeNull();
    const metas = stub!.firstElementChild as HTMLElement | null;
    expect(metas, '`aposKpis` (METAS) não renderizou dentro do cockpit').not.toBeNull();
    expect(
      temClasse(metas!, 'mb-1.5'),
      'METAS sem `mb-1.5` — o `space-y-[18px]` a levaria a 18px, onde a âncora quer 6px',
    ).toBe(true);
  });
});
