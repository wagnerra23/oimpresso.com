// CRM-23/25 — kanban e conversão dentro da tela de Leads (`crm-blade.jsx` → `TelaLeads`).
//
// Antes (produção 2026-11-25): "Kanban" e "Converter para cliente" levavam à tela antiga.
// Agora: quadro por estágio com contador e "arraste aqui"; soltar chama a rota de estágio e o
// cartão muda de coluna; a janela de conversão grava o estágio escolhido e depois converte.
// O que NÃO prova: px e cores (jsdom não resolve CSS) nem o escopo no servidor (UC-CRMLD-15..17).
// Roda no job Frontend / Vite build (.github/workflows/ci.yml).

import type { ReactNode } from 'react';
import { describe, it, expect, vi, afterEach, beforeEach } from 'vitest';
import { render, screen, cleanup, fireEvent, within, waitFor } from '@testing-library/react';

vi.mock('sonner', () => ({ toast: { success: vi.fn(), error: vi.fn() } }));
vi.mock('@inertiajs/react', () => ({
  router: { reload: vi.fn(), get: vi.fn() },
  usePage: () => ({ props: { shell: { cockpit: {}, menu: [] } }, url: '/crm/leads' }),
  Link: ({ children, href, ...r }: { children?: ReactNode; href: string }) => <a href={href} {...r}>{children}</a>,
  Deferred: ({ children }: { children: ReactNode }) => <>{children}</>,
}));
vi.mock('@/Layouts/AppShellV2', () => ({ default: ({ children }: { children: ReactNode }) => <>{children}</> }));

import { router } from '@inertiajs/react';
import LeadsIndex from '../../Modules/Crm/Resources/js/Pages/Crm/Leads/Index';

const lead = (id: number, nome: string, estagio_id: string | null) => ({
  id, codigo: `L${id}`, nome, celular: '0', email: null, documento: null, fonte: 'Site', estagio: null, estagio_id,
  atribuidos: ['Usuário fictício'], endereco: '', ultimo: null, proximo: '26/11/2026 14:30', adicionado_em: null,
});
const OPCOES = { fontes: [], usuarios: [], estagios: [{ value: '1', label: 'Novo' }, { value: '2', label: 'Proposta' }, { value: '3', label: 'Cliente ativo' }] };
const QUADRO = [lead(10, 'Lead fictício A', '1'), lead(11, 'Lead fictício B', '1')];

const fetchMock = vi.fn();
beforeEach(() => {
  fetchMock.mockReset().mockResolvedValue({ ok: true, json: async () => ({ success: true }) });
  vi.stubGlobal('fetch', fetchMock);
});
afterEach(() => { cleanup(); vi.unstubAllGlobals(); });

const coluna = (nome: RegExp) => screen.getByRole('region', { name: nome });

describe('Leads · kanban e conversão', () => {
  it('CRM-23 · uma coluna por estágio com contador, "arraste aqui" na vazia; sem densidade nem filtro de estágio', () => {
    render(<LeadsIndex filtros={{ vista: 'kanban' }} opcoes={OPCOES} kanban={QUADRO as never} />);
    expect(within(coluna(/^Novo: 2/)).getByText('Lead fictício A')).toBeTruthy();
    expect(within(coluna(/^Proposta: 0/)).getByText('arraste aqui')).toBeTruthy();
    expect(within(screen.getByRole('button', { name: 'Abrir Lead fictício A' })).getByText('14:30')).toBeTruthy();
    expect(screen.queryByRole('radio', { name: 'Compacto' })).toBeNull();
    expect(screen.queryByRole('combobox', { name: 'Estágio de vida' })).toBeNull();
  });

  it('CRM-23 · soltar numa coluna chama a rota de estágio e o cartão muda de coluna', async () => {
    render(<LeadsIndex filtros={{ vista: 'kanban' }} opcoes={OPCOES} kanban={QUADRO as never} />);
    const dados = new Map<string, string>();
    const dataTransfer = { setData: (k: string, v: string) => dados.set(k, v), getData: (k: string) => dados.get(k) ?? '', effectAllowed: '', dropEffect: '' };
    fireEvent.dragStart(screen.getByRole('button', { name: 'Abrir Lead fictício A' }), { dataTransfer });
    fireEvent.drop(coluna(/^Proposta/), { dataTransfer });
    expect(fetchMock.mock.calls[0][0]).toBe('/crm/lead/10/post-life-stage?crm_life_stage=2');
    await waitFor(() => expect(within(coluna(/^Proposta: 1/)).getByText('Lead fictício A')).toBeTruthy());
  });

  it('CRM-23 · trocar a vista pede o kanban ao servidor', () => {
    render(<LeadsIndex filtros={{}} opcoes={OPCOES} leads={{ data: [], total: 0, current_page: 1, last_page: 1, per_page: 25, from: 0, to: 0, links: [] } as never} />);
    fireEvent.click(screen.getByRole('radio', { name: 'Kanban' }));
    expect(vi.mocked(router.get).mock.calls.at(-1)?.[1]).toMatchObject({ vista: 'kanban' });
  });

  it('CRM-25 · a janela sugere o estágio "Cliente…", grava o escolhido e depois converte', async () => {
    const assign = vi.fn();
    vi.stubGlobal('location', { ...window.location, assign });
    render(<LeadsIndex filtros={{}} opcoes={OPCOES} lead={lead(10, 'Lead fictício A', '1') as never} />);
    fireEvent.click(within(screen.getByRole('dialog')).getByRole('button', { name: 'Converter para cliente' }));
    const janela = await screen.findByRole('dialog', { name: /Converter Lead fictício A em cliente/ });
    expect(within(janela).getByRole('button', { name: 'Cliente ativo' }).getAttribute('aria-pressed')).toBe('true');
    fireEvent.click(within(janela).getByRole('button', { name: 'Proposta' }));
    fireEvent.click(within(janela).getByRole('button', { name: 'Converter e abrir em Clientes' }));
    await waitFor(() => expect(assign).toHaveBeenCalledWith('/contacts/10'));
    expect(fetchMock.mock.calls.map((c) => c[0])).toEqual(['/crm/lead/10/post-life-stage?crm_life_stage=2', '/crm/lead/10/convert']);
  });

  it('CRM-25 · se o estágio não grava, não converte', async () => {
    fetchMock.mockResolvedValueOnce({ ok: false, json: async () => ({}) });
    render(<LeadsIndex filtros={{}} opcoes={OPCOES} lead={lead(10, 'Lead fictício A', '1') as never} />);
    fireEvent.click(within(screen.getByRole('dialog')).getByRole('button', { name: 'Converter para cliente' }));
    const janela = await screen.findByRole('dialog', { name: /Converter/ });
    fireEvent.click(within(janela).getByRole('button', { name: 'Converter e abrir em Clientes' }));
    await waitFor(() => expect(fetchMock).toHaveBeenCalledTimes(1));
    expect(fetchMock.mock.calls[0][0]).toContain('post-life-stage');
  });
});
