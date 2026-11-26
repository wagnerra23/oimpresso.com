// CRM-31/32 — Acompanhamentos na forma do protótipo (`crm-blade.jsx` → `TelaAcompanhamentos`).
//
// Medido em produção em 2026-11-25: filtros fixos, título sem contador, sem Confortável/Compacto,
// linha de 65px com célula 14px, cabeçalho sem faixa, selo "Agendado" azul, ação "…" e sem a coluna
// "Informação adicional". O protótipo usa o `DataTablePro` do DS (= `DataGrid` sem listras, célula
// 9×10 no confortável, 5×10 no compacto).
// O que NÃO prova: px renderizados e cores (jsdom não resolve CSS). Medir no smoke pós-deploy.
// Roda no job Frontend / Vite build (.github/workflows/ci.yml).

import type { ReactNode } from 'react';
import { describe, it, expect, vi, afterEach } from 'vitest';
import { render, screen, cleanup, fireEvent, within } from '@testing-library/react';

vi.mock('sonner', () => ({ toast: { warning: vi.fn(), success: vi.fn(), error: vi.fn() } }));
vi.mock('@inertiajs/react', () => ({
  router: { reload: vi.fn(), get: vi.fn(), visit: vi.fn() },
  usePage: () => ({ props: { shell: { cockpit: {}, menu: [] } }, url: '/crm/follow-ups' }),
  Link: ({ children, href, ...r }: { children?: ReactNode; href: string }) => <a href={href} {...r}>{children}</a>,
  Deferred: ({ children }: { children: ReactNode }) => <>{children}</>,
}));
vi.mock('@/Layouts/AppShellV2', () => ({ default: ({ children }: { children: ReactNode }) => <>{children}</> }));

import AcompanhamentosIndex from '../../Modules/Crm/Resources/js/Pages/Crm/Acompanhamentos/Index';

const linha = (id: number, titulo: string, extra: string | null) => ({
  id, titulo, contato: 'Contato fictício', inicio: '25/11/2026 09:00', fim: '25/11/2026 09:30', status: 'scheduled', tipo: 'call',
  categoria: null, atribuidos: ['Usuário fictício'], descricao: '', informacao_adicional: extra, por: null, em_dias: null,
  adicionado_por: 'Usuário fictício', adicionado_em: '25/11/2026',
  editar: { notify_via: { sms: false, mail: false } },
});
const PAGINA = {
  data: [linha(1, 'Ligar', 'Origem: Indicação'), linha(2, 'Visitar', null)],
  total: 7, current_page: 1, last_page: 1, per_page: 25, from: 1, to: 2, links: [],
};
const OPCOES = { contatos: [], usuarios: [], status: [{ value: 'scheduled', label: 'Agendado' }], tipos: [], categorias: [], por: [], notificar: [], recorrencia: [] };

const montar = () => render(<AcompanhamentosIndex filtros={{}} opcoes={OPCOES as never} acompanhamentos={PAGINA as never} />);

afterEach(() => cleanup());

describe('Acompanhamentos · forma do protótipo', () => {
  it('CRM-31 · filtros recolhem e expandem, título com contador "x de y"', () => {
    montar();
    const filtros = document.querySelector('[data-contract="crm-filtros"]') as HTMLElement;
    fireEvent.click(within(filtros).getByRole('button', { name: 'Recolher' }));
    expect(within(filtros).getByText('recolhidos')).toBeTruthy();
    expect(within(filtros).getByRole('button', { name: 'Expandir' })).toBeTruthy();
    const tabela = document.querySelector('[data-contract="crm-acompanhamentos"]') as HTMLElement;
    expect(within(tabela).getByText('2 de 7')).toBeTruthy();
  });

  it('CRM-31/32 · Confortável é 9×10 sem listra; Compacto é 5×10', () => {
    montar();
    const celula = () => screen.getByText('Ligar').closest('td') as HTMLElement;
    expect(celula().className).toContain('py-[9px]');
    expect(screen.getByText('Visitar').closest('td')!.className).not.toContain('color-mix');
    fireEvent.click(screen.getByRole('radio', { name: 'Compacto' }));
    expect(celula().className).toContain('py-[5px]');
  });

  it('CRM-32 · "Agendado" no tom de destaque, ação "Ações", coluna "Informação adicional"', () => {
    montar();
    const selo = screen.getAllByText('Agendado')[0];
    expect(selo.getAttribute('style')).toContain('var(--accent)');
    expect(screen.getAllByRole('button', { name: /Ações de Ligar/ })[0].textContent).toContain('Ações');
    expect(screen.getByRole('columnheader', { name: /Informação adicional/ })).toBeTruthy();
    expect(screen.getByText('Origem: Indicação')).toBeTruthy();
  });
});
