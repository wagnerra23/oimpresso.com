// PixAutomaticoBloco — bloco do drawer da assinatura (US-RB-020 · UC-RBPA-14 · decisão [W] 2026-10-26).
//
// O bloco só aparece em produção com RB_COBRAR_PIX_AUTOMATICO ligada (desligada hoje), então nem o CI
// visual nem um screenshot de produção o mostram: este teste é a prova de render dos quatro estados
// e do pedido de autorização (fetch simulado). Sem jest-dom (não está no setup — ver tests/js/setup.ts).

import { describe, it, expect, afterEach, vi } from 'vitest';
import { render, screen, cleanup, fireEvent, waitFor } from '@testing-library/react';

import PixAutomaticoBloco from '@/Pages/RecurringBilling/_components/PixAutomaticoBloco';

const QR = 'QR Code da autorização do Pix Automático';

afterEach(() => {
  cleanup();
  vi.unstubAllGlobals();
});

describe('PixAutomaticoBloco', () => {
  it('sem mandato: explica e oferece pedir autorização, sem QR', () => {
    render(<PixAutomaticoBloco subId={7} inicial={{ status: null, pix_copia_cola: null }} />);
    screen.getByText('Cobrança automática por Pix: o cliente autoriza uma vez no app do banco.');
    screen.getByRole('button', { name: /Pedir autorização/ });
    expect(screen.queryByTitle(QR)).toBeNull();
  });

  it('CRIADA: mostra o QR do Pix copia e cola e o botão de copiar, sem pedir de novo', () => {
    render(
      <PixAutomaticoBloco subId={7} inicial={{ status: 'CRIADA', pix_copia_cola: '00020126180014br.gov.bcb.pix-QR' }} />,
    );
    screen.getByText('Aguardando o cliente aprovar no app do banco.');
    screen.getByText('00020126180014br.gov.bcb.pix-QR');
    screen.getByRole('button', { name: /Copiar Pix copia e cola/ });
    screen.getByTitle(QR);
    expect(screen.queryByRole('button', { name: /Pedir/ })).toBeNull();
  });

  it('APROVADA: só o texto de que as faturas são cobradas sozinhas', () => {
    render(<PixAutomaticoBloco subId={7} inicial={{ status: 'APROVADA', pix_copia_cola: null }} />);
    screen.getByText(/Cada fatura é cobrada sozinha, de 10 a 2 dias antes do vencimento/);
    expect(screen.queryAllByRole('button')).toHaveLength(0);
  });

  it('REJEITADA: oferece pedir nova autorização', () => {
    render(<PixAutomaticoBloco subId={7} inicial={{ status: 'REJEITADA', pix_copia_cola: null }} />);
    screen.getByText('O cliente recusou a autorização.');
    screen.getByRole('button', { name: /Pedir nova autorização/ });
  });

  it('pedir autorização chama a rota da assinatura e passa a mostrar o QR que voltou', async () => {
    const fetchMock = vi.fn().mockResolvedValue({
      ok: true,
      json: async () => ({ ok: true, mandato: { id_rec: 'RN1', status: 'CRIADA', pix_copia_cola: 'QR-DO-BANCO' } }),
    });
    vi.stubGlobal('fetch', fetchMock);

    render(<PixAutomaticoBloco subId={42} inicial={{ status: null, pix_copia_cola: null }} />);
    fireEvent.click(screen.getByRole('button', { name: /Pedir autorização/ }));

    await waitFor(() => screen.getByText('QR-DO-BANCO'));
    screen.getByTitle(QR);
    expect(fetchMock).toHaveBeenCalledTimes(1);
    expect(fetchMock.mock.calls[0][0]).toBe('/recurring-billing/42/pix-automatico');
    expect(fetchMock.mock.calls[0][1].method).toBe('POST');
  });

  it('erro do banco aparece como alerta e o botão volta', async () => {
    vi.stubGlobal('fetch', vi.fn().mockResolvedValue({ ok: false, json: async () => ({ ok: false, message: 'Cadastre a credencial.' }) }));

    render(<PixAutomaticoBloco subId={42} inicial={{ status: null, pix_copia_cola: null }} />);
    fireEvent.click(screen.getByRole('button', { name: /Pedir autorização/ }));

    await waitFor(() => screen.getByRole('alert'));
    expect(screen.getByRole('alert').textContent).toBe('Cadastre a credencial.');
    screen.getByRole('button', { name: /Pedir autorização/ });
  });
});
