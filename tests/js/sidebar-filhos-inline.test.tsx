/**
 * Sidebar — item com filhos abre DENTRO do sidebar, como as sub-telas do Financeiro.
 *
 * Pedido [W] 2026-11-06: os módulos que publicam `Menu::dropdown` (Fisio, Ponto,
 * Essenciais…) abriam um popover lateral; ele quer todos como o Financeiro, cujas
 * sub-telas aparecem em linhas `sb-ghost` sob o item. Rótulos e rotas escritos à
 * mão de propósito (§5 2026-06-05).
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
  {
    label: 'Ponto',
    href: '#',
    children: [
      { label: 'Espelho', href: '/ponto/espelho' },
      { label: 'Configurações', href: '/ponto/configuracoes' },
    ],
  },
  { label: 'Auditoria', href: '/auditoria' },
];

const irPara = (path: string) => window.history.pushState({}, '', path);
const botao = () => screen.getByRole('button', { name: /Ponto/ });

beforeEach(() => localStorage.clear());
afterEach(() => irPara('/'));

describe('Sidebar · item com filhos abre inline', () => {
  it('fechado de início fora da rota; clique mostra os filhos dentro do sidebar, sem popover', () => {
    irPara('/auditoria');
    const { container } = render(<SidebarMenu items={MENU} />);

    expect(botao().getAttribute('aria-expanded')).toBe('false');
    expect(screen.queryByRole('link', { name: 'Espelho' })).toBeNull();

    fireEvent.click(botao());

    expect(botao().getAttribute('aria-expanded')).toBe('true');
    const espelho = screen.getByRole('link', { name: 'Espelho' });
    expect(espelho.classList.contains('sb-ghost')).toBe(true);
    // Mesmo corpo do grupo — nada flutuando fora do sidebar.
    expect(espelho.closest('.sb-group-body')).not.toBeNull();
    expect(container.querySelector('.sb-item-popover')).toBeNull();
    expect(document.querySelector('.sb-item-popover')).toBeNull();

    fireEvent.click(botao());
    expect(screen.queryByRole('link', { name: 'Espelho' })).toBeNull();
  });

  it('na rota de um filho, o grupo e o item já abrem e só o filho leva aria-current', () => {
    localStorage.setItem('oimpresso.cockpit.group.v2.pessoas.expanded', '0');
    irPara('/ponto/configuracoes');
    render(<SidebarMenu items={MENU} />);

    expect(botao().getAttribute('aria-expanded')).toBe('true');
    const cfg = screen.getByRole('link', { name: 'Configurações' });
    expect(cfg.getAttribute('aria-current')).toBe('page');
    expect(document.querySelectorAll('[aria-current="page"]')).toHaveLength(1);
  });

  // Medido em prod 2026-11-06: em /fisio/agenda, "Painel" (/fisio) e "Agenda"
  // levavam aria-current juntos, porque /fisio casa por prefixo.
  it('filho que é prefixo de outro não divide o aria-current com ele', () => {
    irPara('/fisio/agenda');
    render(
      <SidebarMenu
        items={[
          {
            label: 'Fisio',
            href: '#',
            children: [
              { label: 'Painel', href: '/fisio' },
              { label: 'Agenda', href: '/fisio/agenda' },
            ],
          },
        ]}
      />,
    );

    const atuais = document.querySelectorAll('[aria-current="page"]');
    expect(atuais).toHaveLength(1);
    expect(atuais[0].textContent).toBe('Agenda');
  });

  it('ghost que é prefixo de outro não divide o aria-current com ele', () => {
    irPara('/financeiro/contas-receber');
    render(
      <SidebarMenu
        items={[
          {
            label: 'Financeiro',
            href: '/financeiro',
            ghosts: [
              { key: 'painel', label: 'Painel', href: '/financeiro' },
              { key: 'receber', label: 'A receber', href: '/financeiro/contas-receber' },
            ],
          },
        ]}
      />,
    );

    const atuais = document.querySelectorAll('[aria-current="page"]');
    expect(atuais).toHaveLength(1);
    expect(atuais[0].textContent).toBe('A receber');
  });
});
