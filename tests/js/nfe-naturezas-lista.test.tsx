/**
 * Naturezas de operação na Tributação — onda 3b (RUNBOOK-tributacao §Naturezas).
 *
 * @covers-us UC-NFTR-51 UC-NFTR-52
 *
 * Por que RENDER: o servidor já prova a conferência e o bloqueio de edição (UC-NFTR-44..49). O que
 * só o render prova é a TELA obedecer: a natureza que o cálculo usa abre só para leitura, e o botão
 * de salvar trava enquanto a conferência do servidor apontar erro.
 */
import { afterEach, describe, expect, it, vi } from 'vitest';
import { fireEvent, render, screen, waitFor } from '@testing-library/react';
import NaturezasSecao from '@/Pages/NfeBrasil/Tributacao/_components/Naturezas/NaturezasSecao';

vi.mock('sonner', () => ({ toast: { success: vi.fn(), error: vi.fn() } }));

const linhas = [
  { id: 1, editavel: false, padrao: true, pendencias: null,
    natureza: { nome: 'Venda', tipo: 'S', fin: '1', ativo: true, ref: false, cfop: { in: '?102', out: '', ext: '' } } },
  { id: 2, editavel: true, padrao: false, pendencias: 1,
    natureza: { nome: 'Remessa em garantia', tipo: 'S', fin: '1', ativo: true, ref: true, cfop: { in: '5949', out: '6949', ext: '' },
      icms: { cst: '90' } } },
  { id: 3, editavel: true, padrao: false, pendencias: 0,
    natureza: { nome: 'Bonificação e brinde', tipo: 'S', fin: '1', ativo: false, ref: false, cfop: { in: '5910', out: '6910', ext: '' } } },
];

function servidor(alertas: unknown[] = []) {
  const fetchMock = vi.fn(async (url: string) => {
    const corpo = url.endsWith('/conferir') ? { alertas } : { regime: 'LP', naturezas: linhas };
    return { ok: true, status: 200, json: async () => corpo } as Response;
  });
  vi.stubGlobal('fetch', fetchMock);
  return fetchMock;
}

afterEach(() => vi.unstubAllGlobals());

describe('Naturezas de operação', () => {
  it('UC-NFTR-51 · a lista mostra a situação de cada natureza e a do cálculo abre só para leitura', async () => {
    servidor();
    render(<NaturezasSecao />);

    expect(await screen.findByText('Remessa em garantia')).toBeTruthy();
    expect(screen.getByText('1 pendência')).toBeTruthy();
    expect(screen.getByText('Usada no cálculo')).toBeTruthy();
    // Inativa fica fora de "Todas" e aparece no filtro próprio.
    expect(screen.queryByText('Bonificação e brinde')).toBeNull();
    fireEvent.click(screen.getByRole('radio', { name: 'Inativas' }));
    expect(screen.getByText('Bonificação e brinde')).toBeTruthy();

    fireEvent.click(screen.getByRole('radio', { name: 'Todas' }));
    fireEvent.click(screen.getByRole('button', { name: 'Ver Venda' }));
    expect(screen.getByText('Só leitura')).toBeTruthy();
    expect((screen.getByLabelText('Nome') as HTMLInputElement).disabled).toBe(true);
    expect(screen.queryByRole('button', { name: /Salvar natureza|para salvar/ })).toBeNull();
  });

  it('UC-NFTR-52 · o salvar trava enquanto a conferência do servidor apontar erro', async () => {
    const fetchMock = servidor([{ nivel: 'erro', secao: 'ibs', mensagem: 'Informe o cClassTrib do IBS/CBS com 6 dígitos.' }]);
    render(<NaturezasSecao />);

    fireEvent.click(await screen.findByRole('button', { name: 'Editar Remessa em garantia' }));

    const botao = await screen.findByRole('button', { name: '1 erro para salvar' });
    expect((botao as HTMLButtonElement).disabled).toBe(true);
    // A conferência foi pedida ao servidor, não calculada na tela.
    await waitFor(() => expect(fetchMock.mock.calls.some(([u]) => String(u).endsWith('/naturezas/conferir'))).toBe(true));
  });
});
