/**
 * Hub da CV — cartão "Roteiro da produção" (UC-CVOS-11 · US-COMVIS-003, decisão [W] 2026-10-26).
 *
 * Âncora: SPEC US-COMVIS-003 ("toda OS de comunicação visual nasce com o roteiro padrão da empresa").
 * A resposta da API abaixo é a forma de RoteiroPadraoController::show, escrita à mão.
 */
import { afterEach, describe, expect, it, vi } from 'vitest';
import { render, screen } from '@testing-library/react';

import { RoteiroPadraoCard } from '@/Pages/ComunicacaoVisual/_components/RoteiroPadraoCard';

function responde(corpo: unknown) {
  globalThis.fetch = vi.fn().mockResolvedValue({ ok: true, status: 200, json: () => Promise.resolve(corpo) }) as unknown as typeof fetch;
}

afterEach(() => {
  vi.restoreAllMocks();
});

describe('Hub CV · roteiro padrão da OS', () => {
  it('UC-CVOS-11 · mostra o roteiro padrão atual, e salvar só libera quando muda', async () => {
    responde({ roteiro_id: 7, roteiros: [{ id: 7, nome: 'Banner padrão', familia: 'Comunicação visual' }, { id: 8, nome: 'Fachada', familia: 'Projeto' }] });
    render(<RoteiroPadraoCard />);

    expect(await screen.findByText(/Banner padrão · Comunicação visual/)).not.toBeNull();
    expect((screen.getByRole('button', { name: 'Salvar roteiro' }) as HTMLButtonElement).disabled).toBe(true);
    const [url, init] = (globalThis.fetch as unknown as ReturnType<typeof vi.fn>).mock.calls[0];
    expect(url).toBe('/comunicacao-visual/api/roteiro-padrao');
    expect(init.method).toBe('GET');
  });

  it('UC-CVOS-11 · sem roteiro ativo, diz onde cadastrar e não mostra o seletor', async () => {
    responde({ roteiro_id: null, roteiros: [] });
    render(<RoteiroPadraoCard />);

    expect(await screen.findByText(/Nenhum roteiro ativo/)).not.toBeNull();
    expect(screen.queryByRole('combobox')).toBeNull();
  });
});
