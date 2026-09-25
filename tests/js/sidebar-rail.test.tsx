/**
 * Sidebar — rail (56px): ícone do grupo, grupo ativo e dica em camada fixa.
 *
 * Âncora: `prototipo-ui/cowork/Wagner/sidebar.jsx` — `SidebarMenuRail` (o botão
 * do grupo usa `meta.icon`, o ícone do grupo; ganha `.active` quando a rota
 * atual está no grupo) e `Sidebar` (a dica é `.sb-rail-tip`, `position:fixed`,
 * não pseudo-elemento).
 * Playbook: `prototipo-ui/cowork/Wagner/cowork-inbox/sidebar/playbook/11-rail.md`.
 *
 * Rótulos, rotas e nomes de ícone escritos à mão de propósito (§5 2026-06-05).
 */
import { afterEach, describe, expect, it, vi } from 'vitest';
import { fireEvent, render, screen } from '@testing-library/react';

vi.mock('@inertiajs/react', () => ({
  usePage: () => ({ url: window.location.pathname, props: { shell: { sidebar_counts: null, shortcuts: null } } }),
  router: { reload: vi.fn(), get: vi.fn() },
}));

import { SidebarMenu } from '@/Components/cockpit/Sidebar';
import type { ShellMenuItem } from '@/Components/cockpit/shared';

const MENU: ShellMenuItem[] = [
  { label: 'Produtos', href: '/products', group: 'cadastro' },
  { label: 'Vendas', href: '/sells', group: 'comercial' },
];

const irPara = (path: string) => window.history.pushState({}, '', path);
const botaoGrupo = (nome: string) =>
  screen.getByRole('button', { name: nome }) as HTMLButtonElement;
const classesDoIcone = (el: Element) => el.querySelector('svg')?.getAttribute('class') ?? '';

afterEach(() => {
  localStorage.clear();
  irPara('/');
});

describe('Sidebar · rail (playbook sidebar/11)', () => {
  it('o botão do grupo usa o ícone do GRUPO, não o do 1º item', () => {
    render(<SidebarMenu items={MENU} mode="rail" />);

    // CADASTRO = BookOpen e COMERCIAL = ShoppingCart (os do cabeçalho do grupo).
    // O 1º item de cada um (Produtos, Vendas) tem ícone próprio e diferente.
    expect(classesDoIcone(botaoGrupo('CADASTRO'))).toContain('lucide-book-open');
    expect(classesDoIcone(botaoGrupo('COMERCIAL'))).toContain('lucide-shopping-cart');
  });

  it('o grupo que contém a tela atual ganha .active, o outro não', () => {
    irPara('/products');
    render(<SidebarMenu items={MENU} mode="rail" />);

    expect(botaoGrupo('CADASTRO').classList.contains('active')).toBe(true);
    expect(botaoGrupo('COMERCIAL').classList.contains('active')).toBe(false);
  });

  it('controle: fora de qualquer grupo, nenhum botão fica .active', () => {
    irPara('/lugar-nenhum');
    render(<SidebarMenu items={MENU} mode="rail" />);

    expect(document.querySelectorAll('.sb-rail-group.active')).toHaveLength(0);
  });

  it('a dica é uma camada fixa que aparece no hover/foco e some ao sair', () => {
    render(<SidebarMenu items={MENU} mode="rail" />);
    expect(document.querySelector('.sb-rail-tip')).toBeNull();

    fireEvent.mouseOver(botaoGrupo('COMERCIAL'));
    const tip = document.querySelector('.sb-rail-tip') as HTMLElement;
    expect(tip?.textContent).toBe('COMERCIAL');
    expect(tip.getAttribute('role')).toBe('presentation');

    fireEvent.mouseOut(botaoGrupo('COMERCIAL'));
    expect(document.querySelector('.sb-rail-tip')).toBeNull();

    fireEvent.focusIn(botaoGrupo('CADASTRO'));
    expect(document.querySelector('.sb-rail-tip')?.textContent).toBe('CADASTRO');
  });

  it('a dica some quando o flyout do grupo abre', () => {
    render(<SidebarMenu items={MENU} mode="rail" />);
    const btn = botaoGrupo('COMERCIAL');
    fireEvent.mouseOver(btn);
    fireEvent.click(btn);

    expect(document.querySelector('.sb-rail-flyout')).not.toBeNull();
    expect(document.querySelector('.sb-rail-tip')).toBeNull();
  });
});
