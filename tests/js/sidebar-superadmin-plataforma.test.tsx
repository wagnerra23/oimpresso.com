/**
 * Sidebar — itens de admin de plataforma dentro do "Super Admin" da PLATAFORMA.
 *
 * [W] 2026-11-19: "o super admin do submenu foi para plataforma [...] eu acho que
 * isso tudo é Super admin". Antes: "Super Admin" na PLATAFORMA (só /superadmin) e,
 * separado, a cascata Superadmin no menu do avatar (Backup, Módulos, CMS...).
 * Agora: um lugar só, sob o item da PLATAFORMA.
 *
 * Rótulos escritos à mão de propósito (§5 2026-06-05).
 */
import { describe, expect, it, vi, beforeEach } from 'vitest';
import { fireEvent, render, screen } from '@testing-library/react';

vi.mock('@inertiajs/react', () => ({
  usePage: () => ({ props: { shell: { sidebar_counts: null, shortcuts: null } } }),
  router: { reload: vi.fn(), get: vi.fn() },
}));

import { SidebarMenu } from '@/Components/cockpit/Sidebar';
import { aninharSuperadmin, type ShellMenuItem } from '@/Components/cockpit/shared';

const MENU: ShellMenuItem[] = [
  { label: 'Visão geral', href: '/home', group: 'landing' },
  { label: 'Forja', href: '/forja', group: 'plataforma' },
  { label: 'Super Admin', href: '/superadmin', group: 'plataforma' },
  { label: 'Backup', href: '/backup' },
  { label: 'Módulos', href: '/manage-modules' },
  { label: 'CMS', href: '/cms' },
  { label: 'Conector', href: '/connector' },
  { label: 'Office Impresso', href: '/officeimpresso' },
];

beforeEach(() => {
  localStorage.clear();
});

describe('aninharSuperadmin', () => {
  it('põe Painel + os itens de admin como sub-itens do Super Admin', () => {
    const { menu, avulsos } = aninharSuperadmin(MENU);
    const sa = menu.find((i) => i.label === 'Super Admin')!;
    expect(sa.href).toBeUndefined();
    expect(sa.children!.map((c) => [c.label, c.href])).toEqual([
      ['Painel', '/superadmin'],
      ['Backup', '/backup'],
      ['Módulos', '/manage-modules'],
      ['CMS', '/cms'],
      ['Conector', '/connector'],
      ['Office Impresso', '/officeimpresso'],
    ]);
    expect(menu.map((i) => i.label)).toEqual(['Visão geral', 'Forja', 'Super Admin']);
    expect(avulsos).toEqual([]);
  });

  it('dropdown sem link próprio leva ao filho (Conector chegava como /#)', () => {
    const menu: ShellMenuItem[] = [
      { label: 'Super Admin', href: '/superadmin', group: 'plataforma' },
      { label: 'Conector', href: '#', children: [{ label: 'Clientes', href: '/connector/client' }] },
      { label: 'CMS', children: [
        { label: 'Páginas', href: '/cms/pages' },
        { label: 'Blog', href: '/cms/blog' },
      ] },
    ];
    const sa = aninharSuperadmin(menu).menu[0];
    expect(sa.children!.map((c) => [c.label, c.href])).toEqual([
      ['Painel', '/superadmin'],
      ['Conector', '/connector/client'],
      ['CMS · Páginas', '/cms/pages'],
      ['CMS · Blog', '/cms/blog'],
    ]);
  });

  it('sem o item Super Admin, os itens seguem para a cascata do avatar', () => {
    const semPai = MENU.filter((i) => i.label !== 'Super Admin');
    const { menu, avulsos } = aninharSuperadmin(semPai);
    expect(menu).toBe(semPai);
    expect(avulsos.map((i) => i.label)).toEqual(['Backup', 'Módulos', 'CMS', 'Conector', 'Office Impresso']);
  });
});

describe('Sidebar · Super Admin na PLATAFORMA', () => {
  it('abre os sub-itens dentro do sidebar, no grupo PLATAFORMA', () => {
    render(<SidebarMenu items={aninharSuperadmin(MENU).menu} />);
    // PLATAFORMA nasce fechado: abre o grupo, depois o item.
    fireEvent.click(screen.getByText('PLATAFORMA'));
    fireEvent.click(screen.getByRole('button', { name: /Super Admin/ }));
    for (const rotulo of ['Painel', 'Backup', 'Módulos', 'CMS', 'Conector', 'Office Impresso']) {
      const link = screen.getByRole('link', { name: rotulo });
      expect(link.closest('.sb-group')?.querySelector('.sb-group-l')?.textContent?.trim()).toBe('PLATAFORMA');
    }
  });
});
