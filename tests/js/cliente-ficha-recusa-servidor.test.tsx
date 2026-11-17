// Ficha do cliente (`Pages/Cliente/_show/*`, também na gaveta da lista de clientes): o desconto no
// extrato e a anotação da aba Documentos passam a conferir a recusa do servidor.
//
// Cobre UC-CIDX-13, UC-CIDX-14, UC-CIDX-15, UC-CIDX-17, UC-CIDX-18 e UC-CIDX-20 (resources/js/Pages/Cliente/Index.casos.md). Os dois endpoints
// (LedgerDiscountController@store e DocumentAndNoteController@store) respondem 200 com
// {success: false|0, msg} quando não gravam; a tela olhava só o status HTTP.
//
// Lane: Vitest do `Frontend / Vite build` (.github/workflows/ci.yml).
// Comando: npx vitest run tests/js/cliente-ficha-recusa-servidor.test.tsx

import { describe, it, expect, afterEach, vi } from 'vitest';
import { render, screen, cleanup, fireEvent, waitFor } from '@testing-library/react';
import AddDiscountModal from '@/Pages/Cliente/_show/AddDiscountModal';
import DocumentsTab from '@/Pages/Cliente/_show/DocumentsTab';
import ActionsMenu from '@/Pages/Cliente/_show/ActionsMenu';
import { router } from '@inertiajs/react';
import EnderecosEntregaList from '@/Pages/Cliente/_drawer/EnderecosEntregaList';

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

describe('UC-CIDX-18 · o desconto vai ao servidor com data e hora e valor com 2 casas', () => {
  function enviar(valorDigitado: string) {
    const fetchFalso = responderEmOrdem({ success: true, msg: 'Sucesso' });
    render(<AddDiscountModal contactId={7} contactName="Cliente Teste" contactType="customer" onClose={() => {}} onSuccess={() => {}} />);
    fireEvent.change(document.getElementById('discount-date')!, { target: { value: '2026-11-17' } });
    fireEvent.change(document.getElementById('discount-amount')!, { target: { value: valorDigitado } });
    fireEvent.click(screen.getByTestId('discount-submit-btn'));
    return fetchFalso;
  }

  it('UC-CIDX-18 a data vai com hora (o servidor recusava só a data)', async () => {
    const fetchFalso = enviar('25');

    await waitFor(() => expect(fetchFalso).toHaveBeenCalledTimes(1));
    const corpo = fetchFalso.mock.calls[0][1].body as FormData;
    expect(String(corpo.get('date'))).toMatch(/^2026-11-17 \d{2}:\d{2}$/);
    expect(corpo.get('amount')).toBe('25.00');
  });

  it('UC-CIDX-18 "25.000" digitado vai como 25.00, não como vinte e cinco mil', async () => {
    const fetchFalso = enviar('25.000');

    await waitFor(() => expect(fetchFalso).toHaveBeenCalledTimes(1));
    expect((fetchFalso.mock.calls[0][1].body as FormData).get('amount')).toBe('25.00');
  });

  it('UC-CIDX-18 valor zero não chega ao servidor e a tela avisa', async () => {
    const fetchFalso = enviar('0');

    expect((await screen.findByRole('alert')).textContent).toContain('maior que zero');
    expect(fetchFalso).not.toHaveBeenCalled();
  });
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

describe('UC-CIDX-15 · excluir pela ficha completa', () => {
  const PERM = { pay_due: false, delete: true, toggle_status: false, add_discount: false };

  async function excluirPelaFicha(onAfterDestroy = vi.fn()) {
    vi.spyOn(window, 'confirm').mockReturnValue(true);
    const alerta = vi.fn();
    vi.stubGlobal('alert', alerta);
    render(
      <ActionsMenu contactId={7} contactName="Cliente Teste" contactType="customer" isActive permissions={PERM} onAfterDestroy={onAfterDestroy} />,
    );
    fireEvent.keyDown(screen.getByRole('button', { name: /ações/i }), { key: 'Enter' });
    fireEvent.click(await screen.findByTestId('actions-delete'));
    return { onAfterDestroy, alerta };
  }

  it('UC-CIDX-15 recusa do servidor (cadastro com vínculo) aparece e a ficha não sai como se tivesse excluído', async () => {
    responderEmOrdem({ success: false, msg: 'Este cadastro não pode ser excluído porque tem veículos.' });

    const { onAfterDestroy, alerta } = await excluirPelaFicha();

    await waitFor(() => expect(alerta).toHaveBeenCalledWith('Este cadastro não pode ser excluído porque tem veículos.'));
    expect(onAfterDestroy).not.toHaveBeenCalled();
  });

  it('UC-CIDX-15 controle: exclusão aceita segue para fora da ficha', async () => {
    responderEmOrdem({ success: true, msg: 'Excluído' });

    const { onAfterDestroy, alerta } = await excluirPelaFicha();

    await waitFor(() => expect(onAfterDestroy).toHaveBeenCalledTimes(1));
    expect(alerta).not.toHaveBeenCalled();
  });
});

describe('UC-CIDX-17 · ativar/desativar manda o estado confirmado', () => {
  async function alternar(isActive: boolean) {
    vi.spyOn(window, 'confirm').mockReturnValue(true);
    vi.spyOn(router, 'reload').mockImplementation(() => {});
    const fetchFalso = responderEmOrdem({ success: true, msg: 'Atualizado' });
    render(
      <ActionsMenu
        contactId={7}
        contactName="Cliente Teste"
        contactType="customer"
        isActive={isActive}
        permissions={{ pay_due: false, delete: false, toggle_status: true, add_discount: false }}
      />,
    );
    fireEvent.keyDown(screen.getByRole('button', { name: /ações/i }), { key: 'Enter' });
    fireEvent.click(await screen.findByTestId('actions-toggle-status'));
    await waitFor(() => expect(fetchFalso).toHaveBeenCalledTimes(1));
    return fetchFalso.mock.calls[0];
  }

  it('UC-CIDX-17 "Desativar" manda POST com status=inactive', async () => {
    const [url, init] = await alternar(true);
    expect(url).toBe('/contacts/update-status/7');
    expect((init as RequestInit).method).toBe('POST');
    expect(((init as RequestInit).body as FormData).get('status')).toBe('inactive');
  });

  it('UC-CIDX-17 "Reativar" manda status=active', async () => {
    const [, init] = await alternar(false);
    expect(((init as RequestInit).body as FormData).get('status')).toBe('active');
  });
});

describe('UC-CIDX-20 · endereços de entrega mostram a recusa do servidor', () => {
  const endereco = {
    id: 31, label: 'Filial Centro', zip_code: null, address_line_1: null, numero: null,
    address_line_2: null, neighborhood: null, city: null, state: null, city_code: null,
    is_default: false, is_shipping: false, one_line: 'Rua Teste, 10',
  };

  function responder(...respostas: Array<[number, unknown]>) {
    const fetchFalso = vi.fn();
    for (const [status, corpo] of respostas) {
      fetchFalso.mockResolvedValueOnce(
        new Response(JSON.stringify(corpo), { status, headers: { 'Content-Type': 'application/json' } }),
      );
    }
    vi.stubGlobal('fetch', fetchFalso);
    return fetchFalso;
  }

  async function abrir(...depois: Array<[number, unknown]>) {
    const fetchFalso = responder([200, { addresses: [endereco] }], ...depois);
    vi.spyOn(window, 'confirm').mockReturnValue(true);
    render(<EnderecosEntregaList contactId={7} />);
    await screen.findByText('Filial Centro');
    return fetchFalso;
  }

  it('UC-CIDX-20 "Remover" recusado (403) avisa e o endereço continua na lista', async () => {
    const fetchFalso = await abrir([403, { message: 'Sem permissao' }]);

    fireEvent.click(screen.getByTitle('Remover'));

    expect((await screen.findByRole('alert')).textContent).toBe('Sem permissão para alterar este cliente.');
    expect(fetchFalso.mock.calls[1][1].method).toBe('DELETE');
    expect(screen.queryByText('Filial Centro')).not.toBeNull();
  });

  it('UC-CIDX-20 controle: "Remover" aceito tira o endereço e não mostra aviso', async () => {
    await abrir([200, { addresses: [] }]);

    fireEvent.click(screen.getByTitle('Remover'));

    await waitFor(() => expect(screen.queryByText('Filial Centro')).toBeNull());
    expect(screen.queryByRole('alert')).toBeNull();
  });

  it('UC-CIDX-20 "Marcar como entrega" recusado avisa com a mensagem do servidor (422)', async () => {
    await abrir([422, { errors: { is_shipping: ['O endereço precisa ter CEP.'] } }]);

    fireEvent.click(screen.getByTitle('Marcar como endereço de entrega'));

    expect((await screen.findByRole('alert')).textContent).toBe('O endereço precisa ter CEP.');
  });
});
