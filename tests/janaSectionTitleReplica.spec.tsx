// UC-JPAIN-27 — o h2 de seção do Painel é RÉPLICA da `.jc-h2` da âncora, não o h2 do
// golden governance/Dashboard.
//
// Âncora: `.jc-h2` em `prototipo-ui/cowork/Wagner/chat-jana.css` §"── H2 ──" — âncora de
// SÍMBOLO (`grep -n "jc-h2" prototipo-ui/cowork/Wagner/chat-jana.css`):
//
//   .jc-h2              700 11px/1 var(--mono) · uppercase · ls .08em · gap 7px · m 6px 0 10px
//   .jc-h2 .ic          14px
//   .jc-h2 .jm-h2-sub   400 10.5px mono · ls .02em · text-transform none · margin-left auto
//                       (`jana-merge.css:6`)
//
// Precedência de FORMA: protótipo > teste > casos > charter > SPEC (ADR UI-0029), sob
// ADR 0388 §D-1 (réplica LOCAL — o `SectionTitle` não sai do `JanaCockpit`, então nada
// disto é imposto às outras telas, mesmo caminho do `JanaKpiCard`).
//
// O que divergia, medido e registrado em `Index-visual-comparison.md:735`:
//   11px/700/0.88px (âncora)  ×  14px/600/1.4px (prod)
//
// ⚠️ A CAIXA ALTA **nunca** foi divergência — os dois lados usam `text-transform` no CSS.
// A dúvida de 2026-09-04 ("sentence case no código pode estar sendo uppercase no CSS") já
// tinha sido resolvida no doc (`:740-742`): é uppercase nos dois. Este teste trava o
// `uppercase` como INVARIANTE, não como correção.
//
// Método: jsdom não computa classe Tailwind, então o que se assere é a CLASSE — que é o que
// o bundle traduz. Os detectores têm controle positivo e negativo (ADR 0258).

import * as React from 'react';
import { describe, it, expect, afterEach } from 'vitest';
import { render, cleanup } from '@testing-library/react';
import JanaCockpit from '@/Pages/Jana/_components/JanaCockpit';

afterEach(cleanup);

/** Métrica ANTIGA — o h2 do golden, que a réplica substituiu. Nenhuma pode voltar. */
const METRICA_ANTIGA = ['text-sm', 'font-semibold', 'tracking-widest', 'gap-2'];

/** Métrica da ÂNCORA. */
const METRICA_ANCORA = [
  'font-mono',
  'text-[11px]',
  'font-bold',
  'uppercase',
  'tracking-[0.08em]',
  'gap-[7px]',
];

function renderCockpit() {
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
  return container;
}

function h2s(container: HTMLElement): HTMLElement[] {
  const found = Array.from(container.querySelectorAll<HTMLElement>('h2'));
  if (found.length === 0) throw new Error('nenhum <h2> renderizou');
  return found;
}

/** Classe presente como TOKEN inteiro — `gap-2` não pode casar dentro de `gap-[7px]`. */
function temClasse(el: HTMLElement, classe: string): boolean {
  return el.className.split(/\s+/).includes(classe);
}

describe('detector de classe — controle positivo e negativo (ADR 0258)', () => {
  it('SENSIBILIDADE: casa token inteiro e não casa substring', () => {
    const el = document.createElement('h2');
    el.className = 'mt-1.5 gap-[7px] font-mono text-[11px]';

    expect(temClasse(el, 'gap-[7px]')).toBe(true);
    expect(temClasse(el, 'font-mono')).toBe(true);
    // O risco real deste teste: `gap-2` casaria por substring num `gap-[7px]`? Não deve.
    expect(temClasse(el, 'gap-2')).toBe(false);
    expect(temClasse(el, 'text-sm')).toBe(false);
  });
});

describe('UC-JPAIN-27 — o h2 de seção replica a `.jc-h2`', () => {
  it('todo h2 de seção carrega a métrica da âncora', () => {
    for (const h2 of h2s(renderCockpit())) {
      for (const classe of METRICA_ANCORA) {
        expect(temClasse(h2, classe), `h2 "${h2.textContent?.slice(0, 30)}" sem ${classe}`).toBe(
          true,
        );
      }
    }
  });

  it('nenhum h2 mantém a métrica ANTIGA do golden', () => {
    for (const h2 of h2s(renderCockpit())) {
      for (const classe of METRICA_ANTIGA) {
        expect(temClasse(h2, classe), `h2 ainda tem ${classe} (métrica do golden)`).toBe(false);
      }
    }
  });

  it('o ícone é 14px, como `.jc-h2 .ic`', () => {
    for (const h2 of h2s(renderCockpit())) {
      const svg = h2.querySelector('svg');
      expect(svg, 'h2 sem ícone').not.toBeNull();
      // lucide-react emite width/height no atributo a partir de `size`.
      expect(svg?.getAttribute('width')).toBe('14');
    }
  });

  it('o sub-rótulo vai pra DIREITA e é mono 10.5px, como `.jm-h2-sub`', () => {
    const sub = renderCockpit().querySelector<HTMLElement>('h2 span:not(.inline-flex)');
    expect(sub, 'sub-rótulo do h2 não renderizou').not.toBeNull();
    for (const classe of ['ml-auto', 'font-mono', 'text-[10.5px]', 'tracking-[0.02em]']) {
      expect(temClasse(sub!, classe), `sub-rótulo sem ${classe}`).toBe(true);
    }
    // `margin-left: auto` é o que o empurra; `ml-1` colava no título.
    expect(temClasse(sub!, 'ml-1')).toBe(false);
  });
});
