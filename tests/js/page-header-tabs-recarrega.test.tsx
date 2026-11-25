// Aba cujo destino ainda é a tela antiga (Blade) navega recarregando a página.
//
// Medido em produção em 2026-11-25 (CRM, empresa sem as telas novas ligadas): o `<Link>` do Inertia
// abria a janela de erro com o HTML da Blade ou o JSON do DataTables dentro. O DataController marca a
// aba com `recarrega: true` e o PageHeaderTabs não deixa o Inertia interceptar o clique.
// Roda no job Frontend / Vite build (.github/workflows/ci.yml).

import type { ReactNode, MouseEvent } from 'react';
import { describe, it, expect, vi, afterEach } from 'vitest';
import { render, screen, fireEvent, cleanup } from '@testing-library/react';

// O `Link` de verdade chama o `onClick` de quem o usa e só depois decide: se o evento já veio com
// `defaultPrevented`, não intercepta (`shouldIntercept` do @inertiajs/core). O dublê reproduz isso.
const visit = vi.fn();
vi.mock('@inertiajs/react', () => ({
  router: { visit: (...a: unknown[]) => visit(...a) },
  Link: ({ children, href, onClick, ...resto }: { children?: ReactNode; href: string; onClick?: (e: MouseEvent) => void }) => (
    <a
      href={href}
      {...resto}
      onClick={(e) => {
        onClick?.(e);
        if (!e.defaultPrevented) {
          e.preventDefault();
          visit(href);
        }
      }}
    >
      {children}
    </a>
  ),
}));

import PageHeaderTabs from '@/Components/shared/PageHeaderTabs';

const assign = vi.fn();
Object.defineProperty(window, 'location', { value: { ...window.location, assign }, writable: true });

afterEach(() => {
  cleanup();
  visit.mockClear();
  assign.mockClear();
});

const ABAS = [
  { key: 'dashboard', label: 'Painel', href: '/crm/dashboard' },
  { key: 'campaigns', label: 'Campanhas', href: '/crm/campaigns', recarrega: true },
];

describe('PageHeaderTabs — aba com recarrega', () => {
  it('aba com recarrega navega pela página inteira, sem visita Inertia', () => {
    render(<PageHeaderTabs ghosts={ABAS} activeGhostKey="dashboard" />);
    fireEvent.click(screen.getByRole('tab', { name: 'Campanhas' }));
    expect(assign).toHaveBeenCalledWith('/crm/campaigns');
    expect(visit).not.toHaveBeenCalled();
  });

  it('aba sem recarrega continua sendo visita Inertia', () => {
    render(<PageHeaderTabs ghosts={ABAS} activeGhostKey="campaigns" />);
    fireEvent.click(screen.getByRole('tab', { name: 'Painel' }));
    expect(visit).toHaveBeenCalledWith('/crm/dashboard');
    expect(assign).not.toHaveBeenCalled();
  });

  it('Ctrl+clique na aba com recarrega fica com o navegador (abre em aba nova)', () => {
    render(<PageHeaderTabs ghosts={ABAS} activeGhostKey="dashboard" />);
    fireEvent.click(screen.getByRole('tab', { name: 'Campanhas' }), { ctrlKey: true });
    expect(assign).not.toHaveBeenCalled();
  });
});
