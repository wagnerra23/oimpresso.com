// Ficha do cliente — aba Extrato, envio por e-mail (`Pages/Cliente/_show/LedgerTab`, também na
// gaveta da lista de clientes).
//
// Cobre UC-CIDX-12 (resources/js/Pages/Cliente/Index.casos.md). A tela mandava `email`/`format`,
// nomes que ContactController::sendLedger não lê; o servidor respondia 200 com {success: 0} e a
// tela dizia "Extrato enviado por e-mail." sem nada ter saído.
//
// Lane: Vitest do `Frontend / Vite build` (.github/workflows/ci.yml).
// Comando: npx vitest run tests/js/cliente-extrato-email.test.tsx

import { describe, it, expect, afterEach, vi } from 'vitest';
import { render, screen, cleanup, fireEvent, waitFor } from '@testing-library/react';
import LedgerTab from '@/Pages/Cliente/_show/LedgerTab';

function responder(corpo: unknown, status = 200) {
  const fetchFalso = vi.fn().mockResolvedValue(
    new Response(JSON.stringify(corpo), { status, headers: { 'Content-Type': 'application/json' } }),
  );
  vi.stubGlobal('fetch', fetchFalso);
  return fetchFalso;
}

function enviar() {
  render(<LedgerTab contactId={7} contactName="Cliente Teste" initialFilters={{ format: 'format_2' }} />);
  fireEvent.click(screen.getByRole('button', { name: 'Enviar por e-mail' }));
  fireEvent.change(screen.getByLabelText('E-mail destinatário'), { target: { value: 'cliente@example.com' } });
  fireEvent.click(screen.getByRole('button', { name: 'Enviar' }));
}

afterEach(() => {
  cleanup();
  vi.unstubAllGlobals();
  vi.restoreAllMocks();
});

describe('UC-CIDX-12 · envio do extrato por e-mail', () => {
  it('UC-CIDX-12 manda os campos com os nomes que o servidor lê', async () => {
    const fetchFalso = responder({ success: 1, msg: 'Enviado' });

    enviar();

    await waitFor(() => expect(fetchFalso).toHaveBeenCalledTimes(1));
    const [url, init] = fetchFalso.mock.calls[0];
    expect(url).toBe('/contacts/send-ledger');
    const corpo = (init as RequestInit).body as FormData;
    expect(corpo.get('to_email')).toBe('cliente@example.com');
    expect(corpo.get('ledger_format')).toBe('format_2');
    expect(corpo.get('contact_id')).toBe('7');
    // Os nomes antigos não vão mais.
    expect(corpo.get('email')).toBeNull();
    expect(corpo.get('format')).toBeNull();
    expect(await screen.findByText('Extrato enviado por e-mail.')).toBeTruthy();
  });

  it('UC-CIDX-12 recusa do servidor (200 com success 0) aparece como falha, não como enviado', async () => {
    responder({ success: 0, msg: 'Algo deu errado.' });

    enviar();

    expect(await screen.findByText('Falha: Algo deu errado.')).toBeTruthy();
    expect(screen.queryByText('Extrato enviado por e-mail.')).toBeNull();
  });
});
