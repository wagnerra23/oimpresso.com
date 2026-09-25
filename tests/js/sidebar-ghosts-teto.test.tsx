/**
 * Sidebar — sub-telas (ghosts): a ativa é promovida pra dentro do teto e o
 * excedente aberto ganha "mostrar menos".
 *
 * Âncora: `prototipo-ui/cowork/Wagner/sidebar.jsx` — `GhostList` (teto 5; ativa
 * além do teto vai pra 5ª vaga; aberto → "⌃ mostrar menos" com
 * `aria-expanded="true"`; "mais N" com `aria-expanded="false"`).
 * Playbook: `prototipo-ui/cowork/Wagner/cowork-inbox/sidebar/playbook/10-ghosts.md`
 * ("8 ghosts e o 7º ativo, visível sem clicar + ida-e-volta do mostrar menos").
 *
 * Rótulos e rotas escritos à mão de propósito (§5 2026-06-05).
 */
import { afterEach, describe, expect, it, vi } from 'vitest';
import { fireEvent, render, screen } from '@testing-library/react';

vi.mock('@inertiajs/react', () => ({
  usePage: () => ({ url: window.location.pathname, props: { shell: { sidebar_counts: null, shortcuts: null } } }),
  router: { reload: vi.fn(), get: vi.fn() },
}));

import { SidebarMenu } from '@/Components/cockpit/Sidebar';
import type { ShellMenuItem } from '@/Components/cockpit/shared';

const OITO = ['Um', 'Dois', 'Tres', 'Quatro', 'Cinco', 'Seis', 'Sete', 'Oito'];

const MENU: ShellMenuItem[] = [
  {
    label: 'Produtos',
    href: '/products',
    group: 'cadastro',
    ghosts: OITO.map((n, i) => ({ key: `g${i + 1}`, label: `Tela ${n}`, href: `/products/g${i + 1}` })),
  },
];

const irPara = (path: string) => window.history.pushState({}, '', path);
const ghostsVisiveis = () =>
  Array.from(document.querySelectorAll('.sb-ghost')).map((a) => a.textContent?.trim());

afterEach(() => {
  localStorage.clear();
  irPara('/');
});

describe('Sidebar · ghosts além do teto (playbook sidebar/10)', () => {
  it('8 ghosts com o 7º ativo: o 7º aparece sem clicar, na 5ª vaga', () => {
    irPara('/products/g7');
    render(<SidebarMenu items={MENU} />);

    expect(ghostsVisiveis()).toEqual(['Tela Um', 'Tela Dois', 'Tela Tres', 'Tela Quatro', 'Tela Sete']);
    const ativo = screen.getByRole('link', { name: 'Tela Sete' });
    expect(ativo.getAttribute('aria-current')).toBe('page');
    // Contador conta o que continua escondido: Cinco, Seis e Oito.
    const mais = screen.getByRole('button', { name: /Mostrar mais 3 tela/ });
    expect(mais.getAttribute('aria-expanded')).toBe('false');
  });

  it('controle: ativa dentro do teto não mexe na ordem', () => {
    irPara('/products/g2');
    render(<SidebarMenu items={MENU} />);

    expect(ghostsVisiveis()).toEqual(['Tela Um', 'Tela Dois', 'Tela Tres', 'Tela Quatro', 'Tela Cinco']);
    expect(screen.getByRole('button', { name: /Mostrar mais 3 tela/ })).toBeTruthy();
  });

  it('ida-e-volta: "mais N" abre tudo e "mostrar menos" volta ao teto', () => {
    irPara('/products/g7');
    render(<SidebarMenu items={MENU} />);

    fireEvent.click(screen.getByRole('button', { name: /Mostrar mais 3 tela/ }));
    expect(ghostsVisiveis()).toHaveLength(8);
    expect(screen.queryByRole('button', { name: /Mostrar mais/ })).toBeNull();
    const menos = screen.getByRole('button', { name: /Mostrar menos/ });
    expect(menos.textContent).toContain('mostrar menos');
    expect(menos.getAttribute('aria-expanded')).toBe('true');

    fireEvent.click(menos);
    expect(ghostsVisiveis()).toEqual(['Tela Um', 'Tela Dois', 'Tela Tres', 'Tela Quatro', 'Tela Sete']);
    expect(screen.queryByRole('button', { name: /Mostrar menos/ })).toBeNull();
  });

  it('controle: até o teto não há "mais" nem "mostrar menos"', () => {
    irPara('/products/g1');
    const cinco: ShellMenuItem[] = [{ ...MENU[0], ghosts: MENU[0].ghosts!.slice(0, 5) }];
    render(<SidebarMenu items={cinco} />);

    expect(ghostsVisiveis()).toHaveLength(5);
    expect(document.querySelector('.sb-ghost-more')).toBeNull();
  });
});
