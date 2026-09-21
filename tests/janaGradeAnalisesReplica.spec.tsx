// UC-JPAIN-31 — a grade das análises do Painel é RÉPLICA da `.jc-grid` da âncora:
// 3 colunas acima de 1100px, gap 12px, e os breakpoints DELA (1100/760), não os do Tailwind.
//
// Âncora: `.jc-grid` em `prototipo-ui/cowork/Wagner/chat-jana.css` §"── Análises ──" —
// âncora de SÍMBOLO (`grep -n "jc-grid" prototipo-ui/cowork/Wagner/chat-jana.css`):
//
//   .jc-grid                       grid · repeat(3, 1fr) · gap 12px · margin-bottom 18px
//   @media (max-width: 1100px)     repeat(2, 1fr)
//   @media (max-width:  760px)     1fr
//
// E `.jc-acoes` §"── Ações sugeridas ──": `padding: 0` + `overflow: hidden` — sem o respiro
// de 24px que o `Card` canon traz de fábrica (`ui/card.tsx:29`, `py-6 gap-6`).
//
// O que divergia, MEDIDO em 2026-09-21 (Chrome, mesma janela, viewport 2560, dark nos dois
// lados, container 2237px idêntico — logo a diferença não vinha de largura disponível):
//
//   | campo   | âncora            | prod              |
//   | colunas | 3 (737.66px × 3)  | 2 (1110.5px × 2)  |
//   | gap     | 12px              | 16px              |
//   | ações   | padding 0         | padding 24px 0    |
//
// ⚠️ Por que `min-[761px]`/`min-[1101px]` e não `lg:`/`xl:`: a âncora quebra em 1100px e
// 760px; `lg:` é 1024px e `xl:` é 1280px. Com `lg:` a prod parava em 2 colunas no monitor
// de 1280px da ROTA LIVRE, onde a âncora já mostra 3. Aproximar num breakpoint É a
// divergência, não uma tradução dela.
//
// ⚠️ O `margin-bottom` da âncora (18px) NÃO é travado aqui: medido, o 16px da prod vem do
// `space-y-4` do container da página — a className da grade não declara margem —, logo ele
// rege TODAS as seções (KPIs, Metas, Ações). É ritmo vertical da tela, não da grade.
//
// Precedência de FORMA: protótipo > teste > casos > charter > SPEC (ADR UI-0029), sob
// ADR 0388 §D-1 (réplica LOCAL — nada disto é imposto a outra tela).
//
// Método: jsdom não computa classe Tailwind, então o que se assere é a CLASSE — que é o que
// o bundle traduz. Os detectores têm controle positivo e negativo (ADR 0258).

import * as React from 'react';
import { describe, it, expect, afterEach } from 'vitest';
import { render, cleanup } from '@testing-library/react';
import JanaCockpit from '@/Pages/Jana/_components/JanaCockpit';

afterEach(cleanup);

/** Métrica ANTIGA — o que a réplica substituiu. Nenhuma pode voltar. */
const GRADE_ANTIGA = ['lg:grid-cols-2', 'gap-4'];

/** Métrica da ÂNCORA. */
const GRADE_ANCORA = ['grid', 'grid-cols-1', 'gap-3', 'min-[761px]:grid-cols-2', 'min-[1101px]:grid-cols-3'];

function renderCockpit(props: Record<string, unknown> = {}) {
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
      {...props}
    />,
  );
  return container;
}

/** Classe presente como TOKEN inteiro — `gap-3` não pode casar dentro de `gap-3.5`. */
function temClasse(el: HTMLElement, classe: string): boolean {
  return el.className.split(/\s+/).includes(classe);
}

/** A grade das análises: o `div` que segue o h2 "Análises principais". */
function grade(container: HTMLElement): HTMLElement {
  const h2 = Array.from(container.querySelectorAll<HTMLElement>('h2')).find((e) =>
    /análises/i.test(e.textContent ?? ''),
  );
  if (!h2) throw new Error('h2 "Análises principais" não renderizou');
  let el = h2.nextElementSibling as HTMLElement | null;
  while (el && !temClasse(el, 'grid')) el = el.nextElementSibling as HTMLElement | null;
  if (!el) throw new Error('grade das análises não renderizou depois do h2');
  return el;
}

describe('detector de classe — controle positivo e negativo (ADR 0258)', () => {
  it('SENSIBILIDADE: casa token inteiro e não casa substring', () => {
    const el = document.createElement('div');
    el.className = 'grid grid-cols-1 gap-3 min-[761px]:grid-cols-2 min-[1101px]:grid-cols-3';

    expect(temClasse(el, 'min-[1101px]:grid-cols-3')).toBe(true);
    expect(temClasse(el, 'gap-3')).toBe(true);
    // O risco real: `grid-cols-2` casaria por substring dentro de `min-[761px]:grid-cols-2`?
    // Não deve — senão o assert da métrica antiga passaria a acusar a nova.
    expect(temClasse(el, 'grid-cols-2')).toBe(false);
    expect(temClasse(el, 'lg:grid-cols-2')).toBe(false);
    expect(temClasse(el, 'gap-4')).toBe(false);
  });
});

describe('UC-JPAIN-31 — a grade das análises replica a `.jc-grid`', () => {
  it('carrega a métrica da âncora, incluindo os DOIS breakpoints dela', () => {
    const el = grade(renderCockpit());
    for (const classe of GRADE_ANCORA) {
      expect(temClasse(el, classe), `grade sem ${classe}`).toBe(true);
    }
  });

  it('não mantém a métrica ANTIGA (2 colunas no `lg` e gap 16px)', () => {
    const el = grade(renderCockpit());
    for (const classe of GRADE_ANTIGA) {
      expect(temClasse(el, classe), `grade ainda tem ${classe}`).toBe(false);
    }
  });

  it('as 5 análises do Pro entram na grade — o contrato é a 3ª coluna, não menos cards', () => {
    // Se o container mudar e os cards pararem de ser filhos DIRETOS, 3 colunas viram
    // decoração: a grade distribui filhos, não descendentes.
    expect(grade(renderCockpit()).children.length).toBe(5);
  });

  it('o bloco de Ações zera o respiro do `Card` canon, como a `.jc-acoes`', () => {
    const container = renderCockpit();
    const h2 = Array.from(container.querySelectorAll<HTMLElement>('h2')).find((e) =>
      /ações que/i.test(e.textContent ?? ''),
    );
    expect(h2, 'h2 "Ações que Jana sugere" não renderizou').not.toBeNull();
    const card = h2!.nextElementSibling as HTMLElement | null;
    expect(card, 'bloco de ações não renderizou depois do h2').not.toBeNull();
    // `py-0` é o que de fato tira os 24px (o `gap-6` é inerte: o Card tem UM filho).
    expect(temClasse(card!, 'py-0'), 'Card das ações sem py-0 — volta o respiro de 24px').toBe(
      true,
    );
    expect(temClasse(card!, 'gap-0'), 'Card das ações sem gap-0').toBe(true);
  });

  it('no Grátis não há grade — o upsell ocupa o lugar, e o h2 fica', () => {
    // Guarda o ramo que meu diff NÃO toca: 3 colunas não podem vazar para o estado
    // sem Pro, onde a âncora mostra o card de upsell.
    const container = renderCockpit({ pro: false });
    const h2 = Array.from(container.querySelectorAll<HTMLElement>('h2')).find((e) =>
      /análises/i.test(e.textContent ?? ''),
    );
    expect(h2, 'o h2 de Análises deve ficar nos dois planos').not.toBeNull();
    expect(() => grade(container)).toThrow();
  });
});
