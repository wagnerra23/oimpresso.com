// Ficha do cliente (`Pages/Cliente/_show/*`, também na gaveta da lista de clientes): o desconto no
// extrato e a anotação da aba Documentos passam a conferir a recusa do servidor.
//
// Cobre UC-CIDX-13 e UC-CIDX-14 (resources/js/Pages/Cliente/Index.casos.md). Os dois endpoints
// (LedgerDiscountController@store e DocumentAndNoteController@store) respondem 200 com
// {success: false|0, msg} quando não gravam; a tela olhava só o status HTTP.
//
// Lane: Vitest do `Frontend / Vite build` (.github/workflows/ci.yml).
// Comando: npx vitest run tests/js/cliente-ficha-recusa-servidor.test.tsx

import { describe, it, expect, afterEach, vi } from 'vitest';
import { render, screen, cleanup, fireEvent, waitFor } from '@testing-library/react';
import AddDiscountModal from '@/Pages/Cliente/_show/AddDiscountModal';
import DocumentsTab from '@/Pages/Cliente/_show/DocumentsTab';

function responderEmOrdem(...corpos: unknown[]) {
  const fetchFalso = vi.fn();
  for (const corpo of corpos) {
    fetchFalso.mockResolvedValueOnce(
      new Response(JSON.stringify(corpo), { status: 200, headers: { 'Content-Type': 'application/json' } }),
    );
  }
  vi.stubGlobal('fetch', fetchFalso);
  return fetchFalso;
}

afterEach(() => {
  cleanup();
  vi.unstubAllGlobals();
  vi.restoreAllMocks();
});

describe('UC-CIDX-13 · desconto no extrato', () => {
  function aplicar() {
    const onSuccess = vi.fn();
    render(<AddDiscountModal contactId={7} contactName="Cliente Teste" contactType="customer" onClose={() => {}} onSuccess={onSuccess} />);
    fireEvent.change(document.getElementById('discount-amount')!, { target: { value: '10' } });
    fireEvent.click(screen.getByTestId('discount-submit-btn'));
    return onSuccess;
  }

  it('UC-CIDX-13 recusa do servidor (200 com success 0) aparece e o modal não fecha', async () => {
    responderEmOrdem({ success: 0, msg: 'Algo deu errado.' });

    const onSuccess = aplicar();

    expect((await screen.findByRole('alert')).textContent).toContain('Algo deu errado.');
    expect(onSuccess).not.toHaveBeenCalled();
  });

  it('UC-CIDX-13 controle: desconto aceito fecha o modal', async () => {
    responderEmOrdem({ success: true, msg: 'Sucesso' });

    const onSuccess = aplicar();

    await waitFor(() => expect(onSuccess).toHaveBeenCalledTimes(1));
    expect(screen.queryByRole('alert')).toBeNull();
  });
});

describe('UC-CIDX-14 · anotação da aba Documentos', () => {
  const escrever = (texto: string) =>
    fireEvent.change(screen.getByPlaceholderText(/Anotações internas sobre o cliente/), { target: { value: texto } });

  it('UC-CIDX-14 recusa do servidor (200 com success false) marca "Erro ao salvar", não "Salvo"', async () => {
    responderEmOrdem({ success: false, msg: 'Algo deu errado.' });
    render(<DocumentsTab contactId={7} documents={[]} notes={[]} />);

    escrever('primeira anotação');

    expect(await screen.findByText('Erro ao salvar', {}, { timeout: 4000 })).toBeTruthy();
    expect(screen.queryByText('Salvo')).toBeNull();
  });

  it('UC-CIDX-14 a anotação criada guarda o id e o salvamento seguinte atualiza a mesma nota', async () => {
    const fetchFalso = responderEmOrdem(
      { success: true, msg: 'Sucesso', note: { id: 55 } },
      { success: true, msg: 'Sucesso' },
    );
    render(<DocumentsTab contactId={7} documents={[]} notes={[]} />);

    escrever('primeira anotação');
    await waitFor(() => expect(fetchFalso).toHaveBeenCalledTimes(1), { timeout: 4000 });
    expect(fetchFalso.mock.calls[0][0]).toBe('/note-documents');
    expect(await screen.findByText('Salvo')).toBeTruthy();

    escrever('primeira anotação, continuada');
    await waitFor(() => expect(fetchFalso).toHaveBeenCalledTimes(2), { timeout: 4000 });
    expect(fetchFalso.mock.calls[1][0]).toBe('/note-documents/55');
    expect(((fetchFalso.mock.calls[1][1] as RequestInit).body as FormData).get('_method')).toBe('PUT');
  });
});
