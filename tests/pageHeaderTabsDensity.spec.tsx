// UC-JPAIN-26 — a aba da Jana usa a métrica da âncora DELA, sem mover as outras 5 áreas.
//
// Âncoras, e são DUAS porque o componente serve mais de um dono:
//   · default → protótipo do CLIENTES, `.cli-moduletopnav-tab` (`clientes-page.css`),
//     fixado por [W] em 2026-07-14 — `14px/400`, `px-3`.
//   · compact → âncora da JANA, `jana-merge.jsx` §`JmTabs` — `13px/500`, `padding 0 14px`.
//     Divergência medida e registrada em `memory/requisitos/Jana/Index-visual-comparison.md`.
//
// Parâmetro em vez de réplica local: decisão [W] de 2026-09-18, escolhida sobre outras três
// (deixar como está · componente de abas próprio da Jana · rever o protótipo do Clientes).
// O `JanaSubNav` DELEGA inteiramente ao `PageHeaderTabs` e não tem markup de aba próprio,
// então replicar custaria duplicar a barra inteira — diferente do `JanaKpiCard`.
//
// ⚠️ POR QUE ESTE ARQUIVO EXISTE, e é o ponto: o `pageHeaderTabsFidelity.spec.tsx` **NÃO
// cobre a métrica da aba**. Medido em 2026-09-18 por mutação — trocado o default para
// `compact`, aquele spec segue **13/13 verde**. Ele trava radius, underline `--accent`, pill
// e o peso da aba ATIVA; font-size, padding e o peso da INATIVA passavam livres.
//
// Isso REFUTA a justificativa que estava registrada no `Index-visual-comparison.md`
// (*"13×14px fica, fidelidade travada em pageHeaderTabsFidelity.spec"*): o item não estava
// travado por teste nenhum — estava só não-feito. O caso `o DEFAULT não se mexe` abaixo é a
// rede que faltava, e ele é o que impede esta prop de virar porta pra mudar as 6 áreas
// no calado.

import * as React from 'react';
import { describe, it, expect, afterEach } from 'vitest';
import { render, cleanup } from '@testing-library/react';
import PageHeaderTabs from '@/Components/shared/PageHeaderTabs';

afterEach(cleanup);

const GHOSTS = [
  { key: 'painel', label: 'Painel', href: '/ia' },
  { key: 'conversa', label: 'Conversa', href: '/ia/conversa' },
];

/** Classe presente como TOKEN inteiro — `px-3` não pode casar dentro de `px-[14px]`. */
function temClasse(el: HTMLElement, classe: string): boolean {
  return el.className.split(/\s+/).includes(classe);
}

function abas(density?: 'default' | 'compact') {
  const { container } = render(
    <PageHeaderTabs ghosts={GHOSTS} activeGhostKey="painel" density={density} />,
  );
  const por = (selecionada: 'true' | 'false') => {
    const el = container.querySelector<HTMLElement>(
      `[role="tab"][aria-selected="${selecionada}"]`,
    );
    if (!el) throw new Error(`aba aria-selected="${selecionada}" não renderizou`);
    return el;
  };
  return { ativa: por('true'), inativa: por('false') };
}

describe('detector — controle positivo e negativo (ADR 0258)', () => {
  it('SENSIBILIDADE: casa token inteiro, não substring', () => {
    const el = document.createElement('a');
    el.className = 'px-[14px] py-1.5 text-[13px] font-medium';
    expect(temClasse(el, 'px-[14px]')).toBe(true);
    // O risco real: `px-3` casaria por substring dentro de `px-[14px]`? Não deve.
    expect(temClasse(el, 'px-3')).toBe(false);
    expect(temClasse(el, 'text-sm')).toBe(false);
  });
});

describe('UC-JPAIN-26 — density', () => {
  it('o DEFAULT não se mexe — é o protótipo do Clientes, e serve 5 outras áreas', () => {
    // Este é o caso que o `pageHeaderTabsFidelity.spec` NÃO fazia. Sem ele, trocar o
    // default por `compact` passa em TODO o resto da suíte — medido.
    const { ativa, inativa } = abas();
    for (const el of [ativa, inativa]) {
      expect(temClasse(el, 'text-sm'), 'default perdeu text-sm').toBe(true);
      expect(temClasse(el, 'px-3'), 'default perdeu px-3').toBe(true);
      expect(temClasse(el, 'text-[13px]'), 'default virou compact').toBe(false);
      expect(temClasse(el, 'px-[14px]'), 'default virou compact').toBe(false);
    }
    // A inativa do default NÃO carrega peso — quem tem peso é a ativa.
    expect(temClasse(inativa, 'font-medium')).toBe(false);
  });

  it('omitir a prop é idêntico a `default` — nenhuma tela existente muda de forma', () => {
    const semProp = abas().inativa.className;
    cleanup();
    const comDefault = abas('default').inativa.className;
    expect(semProp).toBe(comDefault);
  });

  it('COMPACT entrega a métrica da âncora da Jana: 13px, padding 14px, inativa 500', () => {
    const { inativa } = abas('compact');
    expect(temClasse(inativa, 'text-[13px]')).toBe(true);
    expect(temClasse(inativa, 'px-[14px]')).toBe(true);
    expect(temClasse(inativa, 'font-medium')).toBe(true);
    expect(temClasse(inativa, 'text-sm')).toBe(false);
    expect(temClasse(inativa, 'px-3')).toBe(false);
  });

  it('a aba ATIVA segue font-semibold nas DUAS densidades', () => {
    // Invariante: o peso 600 da ativa é travado pelo `pageHeaderTabsFidelity.spec`
    // (`font-weight: 600 — aba ativa em font-semibold`) e vale para as duas âncoras.
    // `compact` muda a INATIVA (400 → 500), nunca a ativa.
    expect(temClasse(abas('default').ativa, 'font-semibold')).toBe(true);
    cleanup();
    expect(temClasse(abas('compact').ativa, 'font-semibold')).toBe(true);
  });

  it('as duas densidades preservam o que o spec de fidelidade trava', () => {
    // Guarda de vizinhança: `density` mexe em tamanho/padding/peso-da-inativa e em
    // NADA mais. Se um valor novo tentar carregar radius ou cor junto, cai aqui.
    for (const d of ['default', 'compact'] as const) {
      const { ativa, inativa } = abas(d);
      for (const el of [ativa, inativa]) {
        expect(/\brounded(-|\b)/.test(el.className), `${d}: aba ganhou radius`).toBe(false);
        expect(temClasse(el, '-mb-px'), `${d}: perdeu -mb-px`).toBe(true);
        expect(temClasse(el, 'border-b-2'), `${d}: perdeu border-b-2`).toBe(true);
      }
      expect(ativa.style.borderBottomColor, `${d}: underline accent sumiu`).toBe('var(--accent)');
      cleanup();
    }
  });
});
