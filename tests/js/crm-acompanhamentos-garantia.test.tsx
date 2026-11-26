// CRM-30 — aba "Garantia" de Acompanhamentos (US-CRM-094, fatia 3; `crm-blade.jsx` → `TelaAcompanhamentos`).
//
// Antes (produção 2026-11-25): só "Acompanhamentos" e "Acompanhamento recorrente".
// Agora, com CRM_GARANTIA_SLA_ENABLED ligada: terceira aba "Garantia (n abertos)", grade com o selo do
// prazo e o rodapé por estado; com a aba ativa o botão principal vira "Abrir chamado de garantia", e a
// janela grava em /crm/follow-ups/garantia com o número da venda digitado.
// O que NÃO prova: o escopo e a busca da venda no servidor (UC-CRMACO-25/26), nem px e cores.
// Roda no job Frontend / Vite build (.github/workflows/ci.yml).

import type { ReactNode } from 'react';
import { describe, it, expect, vi, afterEach, beforeEach } from 'vitest';
import { render, screen, cleanup, fireEvent, within, waitFor } from '@testing-library/react';

vi.mock('sonner', () => ({ toast: { warning: vi.fn(), success: vi.fn(), error: vi.fn() } }));
vi.mock('@inertiajs/react', () => ({
  router: { reload: vi.fn(), get: vi.fn(), visit: vi.fn() },
  usePage: () => ({ props: { shell: { cockpit: {}, menu: [] } }, url: '/crm/follow-ups' }),
  Link: ({ children, href, ...r }: { children?: ReactNode; href: string }) => <a href={href} {...r}>{children}</a>,
  Deferred: ({ children }: { children: ReactNode }) => <>{children}</>,
}));
vi.mock('@/Layouts/AppShellV2', () => ({ default: ({ children }: { children: ReactNode }) => <>{children}</> }));

import { router } from '@inertiajs/react';
import AcompanhamentosIndex from '../../Modules/Crm/Resources/js/Pages/Crm/Acompanhamentos/Index';

const PAGINA = { data: [], total: 0, current_page: 1, last_page: 1, per_page: 25, from: 0, to: 0, links: [] };
const OPCOES = {
  contatos: [{ value: '7', label: 'Cliente fictício' }], usuarios: [], categorias: [{ value: '3', label: 'Defeito de fábrica' }],
  status: [{ value: 'open', label: 'Aberto' }], tipos: [], por: [], notificar: [], recorrencia: [],
};
const chamado = (id: number, prazo: string, relato: string) => ({
  id, prazo, aberto: '25/11/2026 10:00', vence: '26/11/2026 10:00', contato: 'Cliente fictício', origem: 'Venda POS-0001',
  tipo: 'Defeito de fábrica', fila: 'Assistência', responsavel: 'Usuário fictício', status: 'open', relato,
});
const GARANTIA = {
  itens: [chamado(1, 'vencido', 'Voltou com ruído'), chamado(2, 'vencendo', 'Lona desbotou'), chamado(3, 'encerrado', 'Adesivo com bolha')],
  contagem: { abertos: 2, ok: 0, vencendo: 1, vencido: 1 },
  politicas: [{ tipo_id: '3', tipo: 'Defeito de fábrica', horas: 24, fila: 'Assistência', responsavel: 'Usuário fictício' }],
  prazo_padrao_horas: 48, filas: ['Assistência'],
};

const fetchMock = vi.fn();
beforeEach(() => {
  fetchMock.mockReset().mockResolvedValue({ ok: true, status: 200, json: async () => ({ success: true, id: 9 }) });
  vi.stubGlobal('fetch', fetchMock);
});
afterEach(() => { cleanup(); vi.unstubAllGlobals(); });

const montar = (garantia: unknown) =>
  render(<AcompanhamentosIndex filtros={{}} opcoes={OPCOES as never} acompanhamentos={PAGINA as never} garantia={garantia as never} />);

describe('Acompanhamentos · aba Garantia', () => {
  it('CRM-30 · com a chave desligada (prop nula) não há aba Garantia', () => {
    montar(null);
    expect(screen.getAllByRole('tab').map((t) => t.textContent)).toEqual(['Acompanhamentos', 'Acompanhamento recorrente']);
  });

  it('CRM-30 · a aba conta os abertos, mostra o selo do prazo e o rodapé, sem prometer aviso', () => {
    montar(GARANTIA);
    fireEvent.click(screen.getByRole('tab', { name: 'Garantia (2)' }));
    const grade = document.querySelector('[data-contract="crm-garantia"]') as HTMLElement;
    expect(within(grade).getByText('Vencido')).toBeTruthy();
    expect(within(grade).getByText('Vencendo')).toBeTruthy();
    expect(within(grade).getByText('Encerrado')).toBeTruthy();
    expect(within(grade).getAllByRole('columnheader').map((h) => h.textContent?.trim()).slice(0, 4)).toEqual(['Prazo', 'Vence em', 'Contato', 'Venda / OS']);
    const rodape = document.querySelector('[data-contract="crm-garantia-rodape"]') as HTMLElement;
    expect(rodape.textContent).toContain('Abertos: 2');
    expect(rodape.textContent).toContain('No prazo: 0 · Vencendo: 1 · Vencido: 1');
    expect(document.body.textContent).not.toMatch(/Alerta ao responsável|avisado/);
    // Trocar para a aba Garantia não pede nada ao servidor.
    expect(vi.mocked(router.get)).not.toHaveBeenCalled();
  });

  it('CRM-30 · "Abrir chamado de garantia" grava com o número digitado e recarrega a aba', async () => {
    montar(GARANTIA);
    fireEvent.click(screen.getByRole('tab', { name: 'Garantia (2)' }));
    expect(screen.queryByRole('button', { name: 'Adicionar' })).toBeNull();
    fireEvent.click(screen.getByRole('button', { name: 'Abrir chamado de garantia' }));
    const janela = await screen.findByRole('dialog', { name: 'Abrir chamado de garantia' });
    expect(within(janela).getByText('Prazo: 48h a partir da abertura (Padrão da empresa).')).toBeTruthy();
    fireEvent.change(within(janela).getByLabelText('Venda ou OS *'), { target: { value: 'POS-0001' } });
    fireEvent.change(within(janela).getByLabelText('Defeito relatado *'), { target: { value: 'Lona desbotou na fachada' } });
    // Sem cliente, não envia.
    fireEvent.click(within(janela).getByRole('button', { name: 'Abrir chamado' }));
    expect(fetchMock).not.toHaveBeenCalled();

    fireEvent.keyDown(within(janela).getByLabelText('Cliente *'), { key: 'ArrowDown' });
    fireEvent.click(screen.getByRole('option', { name: 'Cliente fictício' }));
    fireEvent.click(within(janela).getByRole('button', { name: 'Abrir chamado' }));
    await waitFor(() => expect(vi.mocked(router.reload)).toHaveBeenCalledWith({ only: ['garantia'] }));
    const [url, init] = fetchMock.mock.calls[0];
    expect(url).toBe('/crm/follow-ups/garantia');
    expect(JSON.parse(init.body)).toMatchObject({ contact_id: 7, origem: 'POS-0001', relato: 'Lona desbotou na fachada', followup_category_id: null });
  });
});
