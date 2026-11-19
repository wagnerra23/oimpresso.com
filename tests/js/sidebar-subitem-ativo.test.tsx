/**
 * Sidebar — o sub-item da tela aberta tem estilo próprio.
 *
 * [W] 2026-11-19: "por que só no Fiscal o submenu fica selecionado o item com cor
 * diferente?". Causa medida: sub-item (filho ou sub-tela) só recebe
 * `aria-current="page"`, e só `.active` tinha regra de cor. Os itens do Fiscal são
 * itens de verdade (`.active`), por isso pareciam diferentes.
 *
 * O jsdom não aplica o cockpit.css, então o teste tem duas pernas: (1) o React marca
 * o sub-item ativo com `aria-current` e mais nenhum; (2) o cockpit.css tem a regra
 * para `.sb-ghost[aria-current="page"]` dentro de `.sb-body`. A cor computada em si
 * é conferida no navegador, depois do deploy.
 */
import { describe, expect, it, vi, beforeEach } from 'vitest';
import { fireEvent, render, screen } from '@testing-library/react';
import { readFileSync } from 'node:fs';
import { resolve } from 'node:path';

vi.mock('@inertiajs/react', () => ({
  usePage: () => ({ url: window.location.pathname, props: { shell: { sidebar_counts: null, shortcuts: null } } }),
  router: { reload: vi.fn(), get: vi.fn() },
}));

import { SidebarMenu } from '@/Components/cockpit/Sidebar';
import type { ShellMenuItem } from '@/Components/cockpit/shared';

const MENU: ShellMenuItem[] = [
  { label: 'Visão geral', href: '/home', group: 'landing' },
  { label: 'Super Admin', group: 'plataforma', children: [
    { label: 'Painel', href: '/superadmin' },
    { label: 'Backup', href: '/backup' },
  ] },
];

beforeEach(() => {
  localStorage.clear();
  window.history.pushState({}, '', '/backup');
});

describe('Sidebar · sub-item ativo', () => {
  it('só o sub-item da tela aberta leva aria-current', () => {
    render(<SidebarMenu items={MENU} />);
    const grupo = screen.queryByText('PLATAFORMA');
    if (grupo && !screen.queryByRole('link', { name: 'Backup' })) fireEvent.click(grupo);
    if (!screen.queryByRole('link', { name: 'Backup' })) {
      fireEvent.click(screen.getByRole('button', { name: /Super Admin/ }));
    }
    expect(screen.getByRole('link', { name: 'Backup' }).getAttribute('aria-current')).toBe('page');
    expect(screen.getByRole('link', { name: 'Painel' }).getAttribute('aria-current')).toBeNull();
  });

  it('o cockpit.css estiliza o sub-item com aria-current', () => {
    const css = readFileSync(resolve(__dirname, '../../resources/css/cockpit.css'), 'utf8');
    const regra = css.match(/\.cockpit \.sb-body \.sb-item\.sb-ghost\[aria-current="page"\]\s*\{([^}]*)\}/);
    expect(regra, 'regra do sub-item ativo ausente').not.toBeNull();
    expect(regra![1]).toContain('background: var(--sb-active)');
  });
});
