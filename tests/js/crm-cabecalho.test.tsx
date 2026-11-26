// CRM-10 — cabeçalho único das telas do CRM, no desenho do protótipo.
//
// Âncora: `prototipo-ui/cowork/Wagner/crm-blade.jsx` → `CrmBladePage` (`M.Header`). Medido em
// produção em 2026-11-25: cada tela tinha título próprio ("Painel do CRM", "Leads"…), sem linha de
// contexto, sem selo de atualização e sem "+ Adicionar lead".
// Roda no job Frontend / Vite build (.github/workflows/ci.yml).

import type { ReactNode } from 'react';
import { describe, it, expect, vi, afterEach } from 'vitest';
import { render, screen, cleanup } from '@testing-library/react';

let pagina: Record<string, unknown> = {};
vi.mock('@inertiajs/react', () => ({
  router: { reload: vi.fn() },
  usePage: () => ({ props: pagina, url: '/crm/dashboard' }),
  Link: ({ children, href, ...resto }: { children?: ReactNode; href: string }) => <a href={href} {...resto}>{children}</a>,
}));

import CrmCabecalho from '../../Modules/Crm/Resources/js/Pages/Crm/_components/CrmCabecalho';

const MENU = (ghosts: string[]) => [{
  label: 'CRM', primary: { label: 'Novo lead', href: '/crm/leads/create' },
  ghosts: ghosts.map((k) => ({ key: k, label: k, href: `/crm/${k}` })),
}];

afterEach(() => { cleanup(); pagina = {}; });

describe('CRM-10 · cabeçalho do módulo', () => {
  it('título "CRM", subtítulo = aba, linha de contexto com empresa e papel', () => {
    pagina = { shell: { cockpit: { businessNome: 'Empresa fictícia', usuarioCargo: 'Operacional' }, menu: MENU(['dashboard', 'leads']) } };
    render(<CrmCabecalho aba="Painel" />);
    expect(screen.getByRole('heading', { level: 1 }).textContent).toContain('CRM');
    expect(screen.getByText('Painel', { selector: 'p, span, div' })).toBeTruthy();
    expect(screen.getByText('Empresa fictícia · papel: Operacional')).toBeTruthy();
    expect(screen.getByRole('button', { name: /Atualizado \d{2}:\d{2}/ })).toBeTruthy();
    expect(screen.getByRole('link', { name: /Adicionar lead/ }).getAttribute('href')).toBe('/crm/leads/create');
  });

  it('sem a aba Leads (sem permissão), não oferece "Adicionar lead"', () => {
    pagina = { shell: { cockpit: { businessNome: 'Empresa fictícia' }, menu: MENU(['dashboard']) } };
    render(<CrmCabecalho aba="Painel" />);
    expect(screen.queryByText(/Adicionar lead/)).toBeNull();
  });

  it('CRM-11 · aba ativa marcada só pelo sublinhado, cores do protótipo, faixa com fundo --bg', () => {
    pagina = { shell: { cockpit: {}, menu: MENU(['dashboard', 'leads']) } };
    render(<CrmCabecalho aba="Painel" />);
    const ativa = screen.getByRole('tab', { selected: true });
    expect(ativa.getAttribute('href')).toBe('/crm/dashboard');
    expect(ativa.style.backgroundColor).toBe('');
    expect(ativa.style.borderBottomColor).toBe('var(--accent)');
    expect(ativa.className).toContain('text-[var(--text)]');
    expect(screen.getByRole('tab', { selected: false }).className).toContain('text-[var(--text-dim)]');
    expect((document.querySelector('[data-contract="crm-abas"]') as HTMLElement).style.background).toBe('var(--bg)');
  });

  it('CONTROLE · fora do CRM a aba ativa segue com o fundo de sempre', async () => {
    const { default: PageHeaderTabs } = await import('@/Components/shared/PageHeaderTabs');
    render(<PageHeaderTabs ghosts={[{ key: 'a', label: 'A', href: '/a' }]} activeGhostKey="a" />);
    expect(screen.getByRole('tab', { selected: true }).style.backgroundColor).not.toBe('');
  });

  it('ação própria da tela substitui o "Adicionar lead"', () => {
    pagina = { shell: { cockpit: {}, menu: MENU(['leads']) } };
    render(<CrmCabecalho aba="Funil" acoes={<button type="button">Novo negócio</button>} />);
    expect(screen.getByRole('button', { name: 'Novo negócio' })).toBeTruthy();
    expect(screen.queryByText(/Adicionar lead/)).toBeNull();
  });
});
