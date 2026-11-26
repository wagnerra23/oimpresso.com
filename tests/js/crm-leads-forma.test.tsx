// CRM-20/21/22/26 — Leads na forma do protótipo (`crm-blade.jsx` → `TelaLeads`).
//
// Medido em produção em 2026-11-25 pelo código (a WR2 tem 0 leads): filtros fixos, título sem
// contador, densidade e Lista/Kanban como botões soltos, sem coluna Ação, estágio em selo neutro,
// último acompanhamento em texto, sem Pessoa de contato/Interesse (campos personalizados) e
// gaveta de 760px sem Tipo.
// O que NÃO prova: px renderizados e cores (jsdom não resolve CSS). Medir no smoke pós-deploy.
// Roda no job Frontend / Vite build (.github/workflows/ci.yml).

import type { ReactNode } from 'react';
import { describe, it, expect, vi, afterEach } from 'vitest';
import { render, screen, cleanup, fireEvent, within } from '@testing-library/react';

vi.mock('@inertiajs/react', () => ({
  router: { reload: vi.fn(), get: vi.fn() },
  usePage: () => ({ props: { shell: { cockpit: {}, menu: [] } }, url: '/crm/leads' }),
  Link: ({ children, href, ...r }: { children?: ReactNode; href: string }) => <a href={href} {...r}>{children}</a>,
  Deferred: ({ children }: { children: ReactNode }) => <>{children}</>,
}));
vi.mock('@/Layouts/AppShellV2', () => ({ default: ({ children }: { children: ReactNode }) => <>{children}</> }));

import LeadsIndex from '../../Modules/Crm/Resources/js/Pages/Crm/Leads/Index';
import { frescor, tomDoEstagio } from '../../Modules/Crm/Resources/js/Pages/Crm/_components/lead';

const lead = (id: number, nome: string, estagio: string) => ({
  id, codigo: `L${id}`, nome, celular: '0', email: null, documento: null, fonte: null, estagio, atribuidos: [],
  endereco: '', ultimo: null, proximo: null, adicionado_em: '25/11/2026 10:00', tipo: 'PJ' as const, campo1: 'Contato fictício', campo2: null,
});
const PAGINA = { data: [lead(1, 'Lead fictício A', 'Qualificado'), lead(2, 'Lead fictício B', 'Novo')], total: 9, current_page: 1, last_page: 1, per_page: 25, from: 1, to: 2, links: [] };
const ROTULOS = { campo1: 'Pessoa de contato', campo2: 'Interesse' };

afterEach(() => cleanup());

describe('Leads · forma do protótipo', () => {
  it('CRM-20/21 · filtros recolhem; título com contador; Lista/Kanban e densidade em seletores', () => {
    render(<LeadsIndex filtros={{}} rotulos={ROTULOS} leads={PAGINA as never} />);
    const filtros = document.querySelector('[data-contract="crm-filtros"]') as HTMLElement;
    fireEvent.click(within(filtros).getByRole('button', { name: 'Recolher' }));
    expect(within(filtros).getByText('recolhidos')).toBeTruthy();
    expect(within(document.querySelector('[data-contract="crm-leads"]') as HTMLElement).getByText('2 de 9')).toBeTruthy();
    expect(screen.getByRole('radio', { name: 'Kanban' })).toBeTruthy();
    expect(screen.getByRole('radio', { name: 'Compacto' })).toBeTruthy();
  });

  it('CRM-22 · coluna Ação, estágio pintado por tom e colunas dos campos personalizados', () => {
    render(<LeadsIndex filtros={{}} rotulos={ROTULOS} leads={PAGINA as never} />);
    expect(screen.getByRole('button', { name: 'Ações de Lead fictício A' })).toBeTruthy();
    expect(screen.getByText('Qualificado').getAttribute('style')).toContain('var(--warn)');
    expect(screen.getByText('Novo').getAttribute('style')).toContain('var(--accent)');
    expect(screen.getByRole('columnheader', { name: /Pessoa de contato/ })).toBeTruthy();
    expect(screen.getByRole('columnheader', { name: /Interesse/ })).toBeTruthy();
  });

  it('CRM-26 · gaveta de 480px com Tipo e o campo personalizado com o rótulo do negócio', () => {
    render(<LeadsIndex filtros={{}} rotulos={ROTULOS} lead={lead(1, 'Lead fictício A', 'Cliente')} />);
    const gaveta = screen.getByRole('dialog');
    expect(gaveta.className).toContain('sm:max-w-[480px]');
    expect(within(gaveta).getByText('Tipo')).toBeTruthy();
    expect(within(gaveta).getByText('PJ')).toBeTruthy();
    expect(within(gaveta).getByText('Pessoa de contato')).toBeTruthy();
    expect(within(gaveta).getByText('Contato fictício')).toBeTruthy();
  });

  it('CRM-22 · frescor e tom do estágio', () => {
    const agora = new Date(2026, 10, 25, 12, 0);
    expect(frescor('25/11/2026 10:00', agora)).toEqual({ texto: 'há 2h', tom: 'ok' });
    expect(frescor('20/11/2026 12:00', agora)).toEqual({ texto: 'há 5d', tom: 'warn' });
    expect(frescor('04/11/2026 12:00', agora)).toEqual({ texto: 'há 21d', tom: 'neg' });
    expect(frescor(null, agora)).toBeNull();
    expect(tomDoEstagio('Negociação')).toBe('warn');
    expect(tomDoEstagio('Perdido')).toBe('neg');
    expect(tomDoEstagio('Outro nome')).toBe('mute');
  });
});
