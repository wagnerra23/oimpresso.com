/**
 * Agenda de instalação da CV — o bloqueio sem janela fica visível (UC-CVAG-04 · US-COMVIS-007 · UC-FLX-21).
 *
 * Âncora: SPEC US-COMVIS-007 DoD "Bloqueia sem janela … 422 dizendo com quem e quando" + charter da Agenda
 * ("o alerta 'Sem janela — não agendado' mostra com quem e quando"). A mensagem abaixo é a forma que
 * AgendaInstalacao::agendar devolve (InstalacaoAgendaContratoTest UC-CVINST-02), escrita à mão.
 */
import { afterEach, describe, expect, it, vi } from 'vitest';
import { fireEvent, render, screen, waitFor } from '@testing-library/react';

const reload = vi.fn();
vi.mock('@inertiajs/react', () => ({
  router: { reload: (...a: unknown[]) => reload(...a), get: vi.fn() },
  Deferred: ({ children }: { children: unknown }) => children,
  Head: () => null,
}));

import { FormAgendar } from '@/Pages/ComunicacaoVisual/Agenda';

const EQUIPE = [{ id: 7, nome: 'Instalador Alfa' }];
const OS = [{ id: 41, numero: 'OS-41', etapa: 'instalacao' }];
const MSG = 'Sem janela: Instalador Alfa já está na instalação #12 (OS OS-40), de 21/10/2026 09:00 a 11:00.';

function responde(status: number, corpo: unknown) {
  globalThis.fetch = vi.fn().mockResolvedValue({ status, json: () => Promise.resolve(corpo) }) as unknown as typeof fetch;
}

function preencherEEnviar() {
  render(<FormAgendar equipe={EQUIPE} os={OS} diaPadrao="2026-10-21" osInicial="41" />);
  fireEvent.click(screen.getByRole('checkbox'));
  fireEvent.click(screen.getByRole('button', { name: 'Agendar' }));
}

afterEach(() => {
  vi.restoreAllMocks();
  reload.mockReset();
});

describe('Agenda CV · bloqueio sem janela', () => {
  it('UC-CVAG-04 · 422 "Sem janela" vira o alerta com quem e quando, e a agenda não recarrega', async () => {
    responde(422, { message: MSG });
    preencherEEnviar();

    const alerta = await screen.findByText(/Sem janela — não agendado/);
    const caixa = alerta.closest('[data-contract="bloqueio"]');
    expect(caixa).not.toBeNull();
    expect(caixa?.textContent).toContain('Instalador Alfa');
    expect(caixa?.textContent).toContain('21/10/2026 09:00 a 11:00');
    expect(reload).not.toHaveBeenCalled();

    const [url, init] = (globalThis.fetch as unknown as ReturnType<typeof vi.fn>).mock.calls[0];
    expect(url).toBe('/comunicacao-visual/api/os/41/instalacao');
    expect(JSON.parse(init.body)).toMatchObject({ inicio: '2026-10-21 08:00', fim: '2026-10-21 10:00', equipe: [7] });
  });

  it('UC-CVAG-04 · controle: 422 de dado inválido não se passa por falta de janela', async () => {
    responde(422, { message: 'O campo fim deve ser posterior a inicio.' });
    preencherEEnviar();

    await screen.findByText('O campo fim deve ser posterior a inicio.');
    expect(document.querySelector('[data-contract="bloqueio"]')).toBeNull();
  });

  it('UC-CVAG-04 · controle: 201 agenda, recarrega só a agenda e não mostra bloqueio', async () => {
    responde(201, { id: 99 });
    preencherEEnviar();

    await screen.findByText('Instalação agendada.');
    expect(document.querySelector('[data-contract="bloqueio"]')).toBeNull();
    await waitFor(() => expect(reload).toHaveBeenCalledWith({ only: ['instalacoes'] }));
  });
});
