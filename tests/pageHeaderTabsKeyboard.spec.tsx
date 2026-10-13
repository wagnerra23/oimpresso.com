// Teclado + axe do <PageHeaderTabs> — o padrão WAI-ARIA tablist que a thread 03 do playbook
// PageHeader manda o DS copiar (`cowork-inbox/pageheader/playbook/03-ds-a11y.md`).
//
// POR QUE ESTE ARQUIVO EXISTE: o recibo `_saida-03.md` (2026-09-24) afirma que o vivo "já está
// certo" — ←/→/Home/End, `role="tablist"`, `aria-selected` — e cita `PageHeaderTabs.tsx` linha a
// linha. Era LEITURA de código, não prova: nenhum spec do repo exercitava as setas (medido em
// 2026-10-13: `ArrowRight` não aparece em `pageHeaderTabsFidelity` nem em `pageHeaderTabsDensity`).
// Se alguém apagar o `onKeyDown` das abas, os dois specs seguem verdes. Este é o que cai.
//
// O que ele NÃO cobre: foco visual e contraste (jsdom não calcula layout — é o axe em browser
// real, `tests/Browser/CoreScreens/A11yAxeBrowserTest.php`). Aqui é estrutura + comportamento.

import * as React from 'react';
import { describe, it, expect, afterEach } from 'vitest';
import { render, cleanup, fireEvent } from '@testing-library/react';
import axe from 'axe-core';
import PageHeaderTabs from '@/Components/shared/PageHeaderTabs';

afterEach(cleanup);

const GHOSTS = [
  { key: 'a', label: 'Primeira', href: '/x/a' },
  { key: 'b', label: 'Segunda', href: '/x/b' },
  { key: 'c', label: 'Terceira', href: '/x/c' },
  { key: 'd', label: 'Quarta', href: '/x/d' },
];

const IMPACTFUL = new Set(['serious', 'critical']);

async function violacoesGraves(container: HTMLElement) {
  const { violations } = await axe.run(container, { resultTypes: ['violations'] });
  return violations
    .filter((v) => IMPACTFUL.has(v.impact ?? ''))
    .map((v) => `${v.impact}: ${v.id} — ${v.help}`);
}

function montar(activeGhostKey = 'b') {
  const { container } = render(<PageHeaderTabs ghosts={GHOSTS} activeGhostKey={activeGhostKey} />);
  const abas = Array.from(container.querySelectorAll<HTMLElement>('[role="tab"]'));
  // Pré-condição anti-vácuo: sem as 4 abas, todo assert abaixo seria sobre nada.
  expect(abas.map((a) => a.textContent?.trim())).toEqual(['Primeira', 'Segunda', 'Terceira', 'Quarta']);
  return { container, abas };
}

/** Pressiona `key` na aba focada e devolve [índice que ficou focado, se o default foi impedido]. */
function tecla(abas: HTMLElement[], de: number, key: string): [number, boolean] {
  abas[de].focus();
  const naoImpedido = fireEvent.keyDown(abas[de], { key });
  return [abas.indexOf(document.activeElement as HTMLElement), !naoImpedido];
}

describe('PageHeaderTabs — estrutura tablist', () => {
  it('tablist com nome; aba ativa aria-selected=true, as outras false', () => {
    const { container, abas } = montar('b');
    const lista = container.querySelector('[role="tablist"]');
    expect(lista?.getAttribute('aria-label')).toBe('Visão da página');
    expect(abas.map((a) => a.getAttribute('aria-selected'))).toEqual(['false', 'true', 'false', 'false']);
  });

  it('roving tabindex: só a ativa entra no Tab (0), as outras saem (-1)', () => {
    const { abas } = montar('c');
    expect(abas.map((a) => a.getAttribute('tabindex'))).toEqual(['-1', '-1', '0', '-1']);
  });
});

describe('PageHeaderTabs — teclado (←/→/Home/End)', () => {
  it('ArrowRight move o foco para a próxima aba', () => {
    const { abas } = montar();
    expect(tecla(abas, 1, 'ArrowRight')).toEqual([2, true]);
  });

  it('ArrowRight na última volta para a primeira (circular)', () => {
    const { abas } = montar();
    expect(tecla(abas, 3, 'ArrowRight')).toEqual([0, true]);
  });

  it('ArrowLeft move o foco para a anterior', () => {
    const { abas } = montar();
    expect(tecla(abas, 2, 'ArrowLeft')).toEqual([1, true]);
  });

  it('ArrowLeft na primeira volta para a última (circular)', () => {
    const { abas } = montar();
    expect(tecla(abas, 0, 'ArrowLeft')).toEqual([3, true]);
  });

  it('Home vai para a primeira, End para a última', () => {
    const { abas } = montar();
    expect(tecla(abas, 2, 'Home')).toEqual([0, true]);
    expect(tecla(abas, 1, 'End')).toEqual([3, true]);
  });

  it('CONTROLE: tecla fora do padrão (Enter) não move o foco nem impede o default', () => {
    // Sem este caso, um handler que impedisse toda tecla passaria nos de cima.
    const { abas } = montar();
    expect(tecla(abas, 1, 'Enter')).toEqual([1, false]);
  });
});

describe('PageHeaderTabs — axe (jsdom), 0 violações serious/critical', () => {
  it('a barra de abas renderizada', async () => {
    const { container } = montar();
    expect(await violacoesGraves(container)).toEqual([]);
  });

  it('CONTROLE: o detector morde um tablist quebrado (senão o verde acima é vácuo)', async () => {
    const { container } = render(
      <div role="tablist" aria-label="quebrado">
        <span role="tab" aria-selected={'talvez' as unknown as boolean}>x</span>
      </div>,
    );
    expect((await violacoesGraves(container)).length).toBeGreaterThan(0);
  });
});
