/**
 * Status fiscal da venda (lote 0.5 da auditoria do NfeBrasil).
 *
 * @covers-us UC-NFST-06 UC-NFST-07
 *
 * Render das telas de verdade com o hook de verdade: só a rede é falsa. O que se prova é o que a
 * operadora vê e para onde o link leva.
 */
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
import { render, screen, waitFor } from '@testing-library/react';

vi.mock('@inertiajs/react', () => ({
  Head: () => null,
  Link: ({ children, href }: { children: React.ReactNode; href: string }) => <a href={href}>{children}</a>,
  router: { post: vi.fn() },
}));
vi.mock('@/Layouts/AppShellV2', () => ({ default: ({ children }: { children: React.ReactNode }) => <>{children}</> }));

import NfceStatus from '@/Pages/NfeBrasil/Transactions/NfceStatus';
import FiscalSection from '@/Pages/Sells/_components/FiscalSection';

const nfe55 = {
  id: 501, modelo: '55', modelo_label: 'NFe', serie: '1', numero: 1234,
  chave_44: '35261112345678000199550010000012341000012345', status: 'autorizada', cstat: '100',
  motivo: 'Autorizado o uso da NF-e', valor_total: 10, emitido_em: null, is_terminal: true, is_cancelavel: false,
};

function redeCom(emissoes: unknown[]) {
  vi.stubGlobal('fetch', vi.fn(async (url: string) => ({
    ok: true,
    json: async () => (String(url).includes('/emissoes') ? { emissoes } : null),
  })));
}

beforeEach(() => vi.unstubAllGlobals());
afterEach(() => vi.unstubAllGlobals());

describe('UC-NFST-06 · a tela mostra a NF-e da venda', () => {
  it('venda com NF-e 55 autorizada mostra a nota e o DANFE pela rota da emissão', async () => {
    redeCom([nfe55]);
    render(<NfceStatus transaction_id={77} />);

    await waitFor(() => expect(screen.getByText('NFe da venda #77')).toBeTruthy());
    expect(screen.queryByText(/ainda não foi emitida/i)).toBeNull();
    expect(screen.getByRole('link', { name: /baixar danfe/i }).getAttribute('href')).toBe('/nfe-brasil/emissoes/501/danfe-pdf');
  });

  it('venda sem nota continua dizendo que ainda não foi emitida', async () => {
    redeCom([]);
    render(<NfceStatus transaction_id={77} />);

    await waitFor(() => expect(screen.getByText(/ainda não foi emitida/i)).toBeTruthy());
  });
});

describe('UC-NFST-07 · o link do drawer usa o id da venda', () => {
  it('"Detalhes" aponta para a venda, não para a emissão', async () => {
    redeCom([nfe55]);
    render(<FiscalSection saleId={77} />);

    const link = await screen.findByRole('link', { name: /detalhes/i });
    expect(link.getAttribute('href')).toBe('/nfe-brasil/transactions/77/status');
  });
});
