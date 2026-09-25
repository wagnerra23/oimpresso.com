/**
 * Sidebar — item ativo: `aria-current="page"` + o grupo da tela atual abre sozinho.
 *
 * Âncora: `prototipo-ui/cowork/Wagner/sidebar.jsx` — `ItemRow` põe
 * `aria-current="page"` no item ativo; `MenuGroup` tem
 * `useEffect(() => { if (hasActive && !open) setOpen(true) }, [hasActive])`.
 * Playbook: `prototipo-ui/cowork/Wagner/cowork-inbox/sidebar/playbook/09-item-ativo.md`
 * ("grupo fechado no LS abre quando contém a rota; nada gravado no LS").
 *
 * Rótulos e rotas escritos à mão de propósito (§5 2026-06-05).
 */
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
import { fireEvent, render, screen } from '@testing-library/react';

vi.mock('@inertiajs/react', () => ({
  usePage: () => ({ url: window.location.pathname, props: { shell: { sidebar_counts: null, shortcuts: null } } }),
  router: { reload: vi.fn(), get: vi.fn() },
}));

import { SidebarMenu } from '@/Components/cockpit/Sidebar';
import type { ShellMenuItem } from '@/Components/cockpit/shared';

const MENU: ShellMenuItem[] = [
  { label: 'Clientes', href: '/contacts', group: 'cadastro' },
  {
    label: 'Produtos',
    href: '/products',
    group: 'cadastro',
    ghosts: [{ key: 'nova', label: 'Novo produto', href: '/products/create' }],
  },
  { label: 'Auditoria', href: '/auditoria', group: 'sistema' },
];

const LS_CADASTRO = 'oimpresso.cockpit.group.v2.cadastro.expanded';
const LS_SISTEMA = 'oimpresso.cockpit.group.v2.sistema.expanded';

const cabecalho = (rotulo: string): HTMLButtonElement =>
  Array.from(document.querySelectorAll<HTMLButtonElement>('.sb-group-h')).find(
    (b) => b.querySelector('.sb-group-l')?.textContent?.trim() === rotulo
  )!;

const irPara = (path: string) => window.history.pushState({}, '', path);

beforeEach(() => {
  localStorage.clear();
});
afterEach(() => {
  irPara('/');
});

describe('Sidebar · item ativo (playbook sidebar/09)', () => {
  it('grupo FECHADO no localStorage abre quando contém a rota atual — e nada é gravado', () => {
    localStorage.setItem(LS_CADASTRO, '0');
    irPara('/contacts');

    render(<SidebarMenu items={MENU} />);

    expect(cabecalho('CADASTRO').getAttribute('aria-expanded')).toBe('true');
    const link = screen.getByRole('link', { name: /Clientes/ });
    expect(link.getAttribute('aria-current')).toBe('page');
    // A preferência do usuário fica intacta: abrir por estar na rota não persiste.
    expect(localStorage.getItem(LS_CADASTRO)).toBe('0');
  });

  it('controle negativo: grupo fechado SEM a rota atual continua fechado', () => {
    localStorage.setItem(LS_SISTEMA, '0');
    irPara('/contacts');

    render(<SidebarMenu items={MENU} />);

    expect(cabecalho('SISTEMA').getAttribute('aria-expanded')).toBe('false');
    expect(localStorage.getItem(LS_SISTEMA)).toBe('0');
  });

  it('só o item ativo leva aria-current', () => {
    irPara('/contacts');
    render(<SidebarMenu items={MENU} />);

    const marcados = document.querySelectorAll('[aria-current="page"]');
    expect(marcados).toHaveLength(1);
    expect(screen.getByRole('link', { name: /Auditoria/ }).getAttribute('aria-current')).toBeNull();
  });

  it('na sub-tela, o ghost leva aria-current e o item pai não duplica', () => {
    irPara('/products/create');
    render(<SidebarMenu items={MENU} />);

    const ghost = screen.getByRole('link', { name: 'Novo produto' });
    expect(ghost.getAttribute('aria-current')).toBe('page');
    expect(document.querySelectorAll('[aria-current="page"]')).toHaveLength(1);
  });

  it('o clique do usuário continua persistindo a preferência', () => {
    irPara('/contacts');
    render(<SidebarMenu items={MENU} />);

    fireEvent.click(cabecalho('SISTEMA'));
    expect(localStorage.getItem(LS_SISTEMA)).toBe('0');
    fireEvent.click(cabecalho('SISTEMA'));
    expect(localStorage.getItem(LS_SISTEMA)).toBe('1');
  });
});
