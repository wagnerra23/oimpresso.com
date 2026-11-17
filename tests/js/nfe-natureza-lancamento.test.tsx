/**
 * Natureza escolhida ao emitir a NF-e da venda — onda 5b (RUNBOOK-tributacao §Naturezas).
 *
 * @covers-us UC-NFTR-63 UC-NFTR-64
 *
 * Por que RENDER: o servidor já prova que aceita ou recusa a natureza (UC-NFTR-60..62). O que só o
 * render prova é o painel fiscal da venda: sem a flag (404) nada muda na tela e o POST segue sem
 * operacao_id; com naturezas disponíveis, a escolhida vai no POST da NF-e e nunca no da NFC-e.
 */
import { afterEach, describe, expect, it, vi } from 'vitest';
import { fireEvent, render, screen, waitFor } from '@testing-library/react';
import FiscalSection from '@/Pages/Sells/_components/FiscalSection';

vi.mock('@/Hooks/useEmissoesPorTransaction', () => ({
  useEmissoesPorTransaction: () => ({ emissoes: [], loading: false, error: null, isPolling: false, refetch: vi.fn() }),
}));

type Corpo = Record<string, unknown>;

function servidor(naturezasStatus: number) {
  const posts: Corpo[] = [];
  const fetchMock = vi.fn(async (url: string, init?: RequestInit) => {
    if (url.includes('/naturezas/lancamento')) {
      const ok = naturezasStatus === 200;
      const corpo = ok
        ? { naturezas: [{ id: 7, nome: 'Remessa em garantia', cfop: '5949', cfop_interestadual: '6949' }] }
        : { message: 'Not Found' };
      return { ok, status: naturezasStatus, json: async () => corpo } as Response;
    }
    if (url.endsWith('/emitir')) {
      posts.push(JSON.parse(String(init?.body ?? '{}')));
      return { ok: true, status: 200, json: async () => ({ emissao: { status: 'pendente' } }) } as Response;
    }
    return { ok: true, status: 200, json: async () => ({ separavel: false }) } as Response;
  });
  vi.stubGlobal('fetch', fetchMock);
  return { fetchMock, posts };
}

afterEach(() => vi.unstubAllGlobals());

describe('Natureza da operação ao emitir', () => {
  it('UC-NFTR-63 · sem a flag (404) o seletor não aparece e a NF-e sai sem operacao_id', async () => {
    const { fetchMock, posts } = servidor(404);
    render(<FiscalSection saleId={10} />);

    await waitFor(() => expect(fetchMock.mock.calls.some(([u]) => String(u).includes('/naturezas/lancamento'))).toBe(true));
    expect(screen.queryByRole('combobox', { name: 'Natureza da operação' })).toBeNull();

    fireEvent.click(screen.getByRole('button', { name: /Emitir NFe/ }));
    await waitFor(() => expect(posts).toHaveLength(1));
    expect(posts[0]).toEqual({ modelo: '55' });
  });

  it('UC-NFTR-64 · a natureza escolhida vai no POST da NF-e e não no da NFC-e', async () => {
    const { posts } = servidor(200);
    render(<FiscalSection saleId={10} />);

    const seletor = await screen.findByRole('combobox', { name: 'Natureza da operação' });
    fireEvent.keyDown(seletor, { key: 'Enter' });
    fireEvent.click(await screen.findByRole('option', { name: /Remessa em garantia/ }));

    fireEvent.click(screen.getByRole('button', { name: /Emitir NFe/ }));
    await waitFor(() => expect(posts).toHaveLength(1));
    expect(posts[0]).toEqual({ modelo: '55', operacao_id: 7 });

    // Os botões ficam disabled enquanto a 1ª emissão não termina (FiscalSection:
    // emitting !== null). Clicar antes disso é ignorado e o 2º POST nunca sai —
    // foi assim que este caso caiu no CI do PR #10957 e passou no main.
    const nfce = screen.getByRole('button', { name: /Emitir NFC-e/ }) as HTMLButtonElement;
    await waitFor(() => expect(nfce.disabled).toBe(false));
    fireEvent.click(nfce);
    await waitFor(() => expect(posts).toHaveLength(2));
    expect(posts[1]).toEqual({ modelo: '65' });
  });
});
