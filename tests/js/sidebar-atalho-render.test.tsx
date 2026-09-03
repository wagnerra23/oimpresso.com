/**
 * Dica do atalho `G X` no sidebar — a PONTE entre o índice e a tela.
 *
 * Por que este teste é de RENDER e não de domínio: os 10 casos de
 * `tests/sidebarShortcut.spec.ts` provam que o índice descarta sequência em
 * conflito — e passariam todos enquanto o `SidebarMenuItem` renderizasse
 * `item.shortcut` cru, exibindo dica pros dois lados de uma colisão. Índice
 * perfeito, tela surda. Só o render prova que a fiação da prop existe.
 *
 * Mesmo padrão de `colunas-grid.test.tsx`: mock do Inertia, o resto real.
 *
 * Refs: ADR 0180 Fase 8 · UI-0023 (sidebar dark-fixo).
 */
import { describe, expect, it, vi } from 'vitest';
import { render } from '@testing-library/react';

vi.mock('@inertiajs/react', () => ({
  usePage: () => ({ props: { shell: { sidebar_counts: null, shortcuts: null } } }),
  Head: () => null,
  router: { get: vi.fn(), post: vi.fn() },
}));

import { SidebarMenu } from '@/Components/cockpit/Sidebar';
import type { ShellMenuItem } from '@/Components/cockpit/shared';

const item = (label: string, href: string, shortcut?: string): ShellMenuItem => ({
  label,
  href,
  group: 'comercial',
  ...(shortcut ? { shortcut } : {}),
});

const kbds = (c: HTMLElement): string[] =>
  [...c.querySelectorAll('.sb-kbd')].map((el) => el.textContent ?? '');

describe('SidebarMenu · dica do atalho', () => {
  it('mostra a dica do atalho declarado, dentro do slot .sb-item-end', () => {
    const { container } = render(<SidebarMenu items={[item('Compras', '/compras', 'G S')]} />);
    expect(kbds(container)).toEqual(['G S']);
    // O slot precisa envolver a dica — é ele que reserva a célula de grid e
    // impede que a dica empurre o label (spec do protótipo Cowork).
    expect(container.querySelector('.sb-item-end .sb-kbd')).not.toBeNull();
  });

  it('NÃO mostra dica pros dois lados de uma colisão (`G C` real: Financeiro × Crm)', () => {
    const { container } = render(
      <SidebarMenu
        items={[
          item('Cobrança', '/financeiro/cobranca', 'G C'),
          item('Clientes', '/cliente', 'G C'),
        ]}
      />
    );
    expect(kbds(container)).toEqual([]);
  });

  it('não inventa slot em item sem atalho', () => {
    const { container } = render(<SidebarMenu items={[item('Vendas', '/sells')]} />);
    expect(container.querySelector('.sb-item-end')).toBeNull();
  });

  it('mantém o label intacto ao lado da dica', () => {
    const { container } = render(<SidebarMenu items={[item('Compras', '/compras', 'G S')]} />);
    expect(container.querySelector('.sb-item .label')?.textContent).toBe('Compras');
  });
});
