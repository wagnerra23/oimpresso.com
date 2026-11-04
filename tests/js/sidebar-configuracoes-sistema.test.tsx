/**
 * Sidebar — Configurações e Gerenciamento de usuários no grupo SISTEMA.
 *
 * [W] 2026-11-04: "o configurações [...] ficou muito escondido" no menu do avatar
 * (rodapé) — "ir para coluna do Sistema". O item vem do dropdown core do
 * AdminSidebarMenu (`__('business.settings')` = "Configurações"), que NÃO declara
 * `group`: chega ao grupo pelo match de label em SIDEBAR_GROUPS.sistema.
 *
 * Render, não leitura de constante: o que importa é o que APARECE no menu.
 * Rótulos escritos à mão de propósito (§5 2026-06-05).
 */
import { describe, expect, it, vi, beforeEach } from 'vitest';
import { render } from '@testing-library/react';

vi.mock('@inertiajs/react', () => ({
  usePage: () => ({ props: { shell: { sidebar_counts: null, shortcuts: null } } }),
  router: { reload: vi.fn(), get: vi.fn() },
}));

import { SidebarMenu } from '@/Components/cockpit/Sidebar';
import { isUserMenuItem, type ShellMenuItem } from '@/Components/cockpit/shared';

const MENU: ShellMenuItem[] = [
  { label: 'Visão geral', href: '/home', group: 'landing' },
  { label: 'Auditoria', href: '/auditoria', group: 'sistema' },
  // Sem `group`, como o core publica hoje.
  { label: 'Configurações', href: '/business/settings' },
  { label: 'Gerenciamento de usuários', href: '/users' },
];

/** Rótulo do grupo dono de um link, subindo até o `.sb-group`. */
const grupoDe = (rotulo: string): string | null => {
  const link = Array.from(document.querySelectorAll('.sb-body a, a')).find(
    (a) => a.textContent?.trim() === rotulo
  );
  const grupo = link?.closest('.sb-group');
  return grupo?.querySelector('.sb-group-l')?.textContent?.trim() ?? null;
};

beforeEach(() => {
  localStorage.clear();
});

describe('Sidebar · Configurações em SISTEMA', () => {
  it('renderiza Configurações dentro do grupo SISTEMA', () => {
    render(<SidebarMenu items={MENU} />);
    expect(grupoDe('Configurações')).toBe('SISTEMA');
  });

  it('Configurações deixou de ser item do menu do avatar', () => {
    expect(isUserMenuItem('Configurações')).toBe(false);
    expect(isUserMenuItem('Settings')).toBe(false);
  });

  it('Gerenciamento de usuários também fica em SISTEMA, fora do rodapé', () => {
    expect(isUserMenuItem('Gerenciamento de usuários')).toBe(false);
    render(<SidebarMenu items={MENU} />);
    expect(grupoDe('Gerenciamento de usuários')).toBe('SISTEMA');
  });
});
