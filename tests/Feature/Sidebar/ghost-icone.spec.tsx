/**
 * Sidebar — ícone por sub-tela (thread 14 do playbook · [W] 2026-09-25).
 *
 * Âncora: `prototipo-ui/cowork/Wagner/sidebar.jsx` → `GhostList` renderiza cada ghost pelo
 * `ItemRow`, com o ícone declarado no dado (`data.jsx`: `{ id, icon, label }`). Sem ícone →
 * linha sem ícone. Nome fora do mapa → também sem ícone (NÃO cai no `Hash` do menu principal).
 *
 * Rótulos e rotas escritos à mão de propósito (§5 2026-06-05).
 */
import { afterEach, describe, expect, it, vi } from 'vitest';
import { render, screen } from '@testing-library/react';

vi.mock('@inertiajs/react', () => ({
  usePage: () => ({ url: window.location.pathname, props: { shell: { sidebar_counts: null, shortcuts: null } } }),
  router: { reload: vi.fn(), get: vi.fn() },
}));

import { SidebarMenu } from '@/Components/cockpit/Sidebar';
import type { ShellMenuItem } from '@/Components/cockpit/shared';

const MENU: ShellMenuItem[] = [
  {
    label: 'Vendas',
    href: '/sells',
    group: 'comercial',
    ghosts: [
      { key: 'pedidos', label: 'Pedido de venda', href: '/sells/pedidos', icon: 'orders' },
      { key: 'nova', label: 'Adicionar venda', href: '/sells/create', icon: 'plus' },
      { key: 'sem-icone', label: 'Sem ícone', href: '/sells/sem-icone' },
      { key: 'desconhecido', label: 'Nome desconhecido', href: '/sells/x', icon: 'nao-existe' },
    ],
  },
];

const irPara = (path: string) => window.history.pushState({}, '', path);
const iconeDe = (nome: string) => screen.getByRole('link', { name: nome }).querySelector('svg');

afterEach(() => {
  localStorage.clear();
  irPara('/');
});

describe('Sidebar · ícone por sub-tela (playbook sidebar/14)', () => {
  it('ghost com ícone declarado mostra um svg antes do rótulo', () => {
    irPara('/sells/pedidos');
    render(<SidebarMenu items={MENU} />);

    const pedidos = iconeDe('Pedido de venda');
    expect(pedidos).not.toBeNull();
    expect(pedidos?.getAttribute('aria-hidden')).toBe('true');
    expect(iconeDe('Adicionar venda')).not.toBeNull();
  });

  it('ícones diferentes no dado viram ícones diferentes na tela', () => {
    irPara('/sells/pedidos');
    render(<SidebarMenu items={MENU} />);

    const a = iconeDe('Pedido de venda')?.getAttribute('class');
    const b = iconeDe('Adicionar venda')?.getAttribute('class');
    expect(a).toBeTruthy();
    expect(a).not.toBe(b);
  });

  it('controle: ghost sem ícone e ghost com nome desconhecido ficam SEM svg', () => {
    irPara('/sells/pedidos');
    render(<SidebarMenu items={MENU} />);

    expect(iconeDe('Sem ícone')).toBeNull();
    expect(iconeDe('Nome desconhecido')).toBeNull();
  });
});
