// CRM-12 a CRM-15 — Painel do CRM na forma do protótipo (`crm-blade.jsx` → `TelaPainel`).
//
// Medido em produção em 2026-11-25 (WR2, usuário Operacional): grade de 4 colunas com 3 cards
// (buraco à direita), título do cartão em texto normal sem divisória, tabela com cabeçalho
// "Status · Total" e número em fonte normal, aniversário com o ano e "Enviar desejos" desabilitado.
// O que NÃO prova: medidas em px e cores (jsdom não resolve CSS). Medir no smoke pós-deploy.
// Roda no job Frontend / Vite build (.github/workflows/ci.yml).

import type { ReactNode } from 'react';
import { describe, it, expect, vi, afterEach } from 'vitest';
import { render, screen, cleanup, fireEvent, within } from '@testing-library/react';

const aviso = vi.fn();
vi.mock('sonner', () => ({ toast: { warning: (...a: unknown[]) => aviso(...a), success: vi.fn(), error: vi.fn() } }));
vi.mock('@inertiajs/react', () => ({
  router: { reload: vi.fn() },
  usePage: () => ({ props: { shell: { cockpit: {}, menu: [] } }, url: '/crm/dashboard' }),
  Link: ({ children, href, ...r }: { children?: ReactNode; href: string }) => <a href={href} {...r}>{children}</a>,
  Deferred: ({ children }: { children: ReactNode }) => <>{children}</>,
}));
vi.mock('@/Layouts/AppShellV2', () => ({ default: ({ children }: { children: ReactNode }) => <>{children}</> }));

import PainelIndex from '../../Modules/Crm/Resources/js/Pages/Crm/Painel/Index';

const PESSOAL = {
  hoje: 2, meus_leads: 5, convertidos: 1, chamadas: null,
  por_status: [{ rotulo: 'Agendado', total: 7 }, { rotulo: 'Aberto', total: 0 }],
};
const NEGOCIO = {
  clientes: 3, leads: 4, total_fontes: 2, total_fases: 2, status: [],
  por_fonte: [], por_fase: [], por_usuario: [], conversao: [], chamadas: null,
  aniversarios: { hoje: [], proximos: [{ id: 9, name: 'Contato fictício', dob: '1990-08-27' }] },
};

afterEach(() => { cleanup(); aviso.mockClear(); });

describe('Painel do CRM · forma do protótipo', () => {
  it('CRM-12 · grade com o número de cards (3 → 3 colunas, sem buraco)', () => {
    const { container } = render(<PainelIndex permissoes={{ acompanhamentos: true, leads: true, chamadas: false, admin: false }} pessoal={PESSOAL} />);
    const grade = container.querySelector('[class*="lg:grid-cols-"]') as HTMLElement;
    expect(grade.className).toContain('lg:grid-cols-3');
  });

  it('CRM-13/14 · cartão com título em caixa alta e tabela sem cabeçalho, número mono à direita', () => {
    render(<PainelIndex permissoes={{ acompanhamentos: true, leads: true, chamadas: false, admin: false }} pessoal={PESSOAL} />);
    const meus = document.querySelector('[data-contract="crm-painel-meus"]') as HTMLElement;
    const titulo = within(meus).getByRole('heading', { level: 3 });
    expect(titulo.className).toContain('uppercase');
    expect(titulo.className).toContain('border-b');
    expect(meus.querySelector('thead')).toBeNull();
    const numero = within(meus).getByText('7');
    expect(numero.className).toContain('font-mono');
    expect(numero.className).toContain('text-right');
  });

  it('CRM-15 · aniversário sem o ano, texto de ajuda e "Enviar desejos" avisa sem seleção', () => {
    render(<PainelIndex permissoes={{ acompanhamentos: true, leads: true, chamadas: false, admin: true }} pessoal={PESSOAL} negocio={NEGOCIO} />);
    expect(screen.getByText('27 de ago')).toBeTruthy();
    expect(screen.queryByText('27/08/1990')).toBeNull();
    expect(screen.getByText('Marque quem vai receber e mande os parabéns como campanha.')).toBeTruthy();
    fireEvent.click(screen.getByRole('button', { name: /Enviar desejos/ }));
    expect(aviso).toHaveBeenCalledWith('Selecione os contatos para enviar os desejos.');
  });
});
