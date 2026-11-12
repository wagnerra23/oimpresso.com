// Consulta de vendas — Excluir pelo menu "Ações" da linha mostra a recusa do servidor.
//
// Cobre UC-SIDX-08 (resources/js/Pages/Sells/Index.casos.md). O destroy (SellController@destroy)
// responde 200 com {success:false, msg} quando não exclui — por exemplo, venda que já teve
// devolução. A tela olhava só o status HTTP: não avisava nada e a linha ficava lá.
//
// Lane: Vitest do `Frontend / Vite build` (.github/workflows/ci.yml).
// Comando: npx vitest run tests/js/sells-index-excluir-recusa.test.tsx

import { describe, it, expect, afterEach, beforeEach, vi } from 'vitest';
import { render, screen, cleanup, fireEvent, waitFor } from '@testing-library/react';
import { AcoesDaVenda, type SaleRow } from '@/Pages/Sells/_components/SellsTabelaUnificada';

const RASCUNHO = { id: 41, invoice_no: 'R-0041', status: 'draft', payment_status: 'due' } as unknown as SaleRow;
const VENDA = { id: 42, invoice_no: 'V-0042', status: 'final', payment_status: 'paid' } as unknown as SaleRow;

function responder(status: number, corpo: unknown) {
  const fetchFalso = vi.fn().mockResolvedValue(
    new Response(JSON.stringify(corpo), { status, headers: { 'Content-Type': 'application/json' } }),
  );
  vi.stubGlobal('fetch', fetchFalso);
  return fetchFalso;
}

function excluir(linha: SaleRow, rotulo: string) {
  const onChange = vi.fn();
  render(<AcoesDaVenda row={linha} onView={() => {}} onChange={onChange} />);
  fireEvent.keyDown(screen.getByRole('button', { name: 'Ações da venda' }), { key: 'Enter' });
  fireEvent.click(screen.getByRole('menuitem', { name: rotulo }));
  return onChange;
}

let alerta: ReturnType<typeof vi.fn>;

beforeEach(() => {
  vi.spyOn(window, 'confirm').mockReturnValue(true);
  alerta = vi.fn();
  vi.stubGlobal('alert', alerta);
});

afterEach(() => {
  cleanup();
  vi.unstubAllGlobals();
  vi.restoreAllMocks();
});

describe('UC-SIDX-08 · Excluir na lista mostra quando o servidor recusa', () => {
  it('UC-SIDX-08 recusa com 200 {success:false}: mostra a mensagem do servidor e não recarrega a lista', async () => {
    const fetchFalso = responder(200, { success: false, msg: 'Esta venda tem devolução e não pode ser excluída.' });

    const onChange = excluir(VENDA, 'Excluir');

    await waitFor(() => expect(alerta).toHaveBeenCalledWith('Esta venda tem devolução e não pode ser excluída.'));
    expect(onChange).not.toHaveBeenCalled();
    // A chamada foi mesmo ao destroy da venda — o aviso não veio de outro caminho.
    expect(fetchFalso).toHaveBeenCalledWith('/sells/42', expect.objectContaining({ method: 'DELETE' }));
  });

  it('UC-SIDX-08 rascunho recusado também avisa', async () => {
    responder(200, { success: false, msg: 'Não foi possível excluir.' });

    const onChange = excluir(RASCUNHO, 'Excluir rascunho');

    await waitFor(() => expect(alerta).toHaveBeenCalledWith('Não foi possível excluir.'));
    expect(onChange).not.toHaveBeenCalled();
  });

  it('UC-SIDX-08 controle: exclusão aceita recarrega a lista e não mostra aviso', async () => {
    responder(200, { success: true, msg: 'Venda excluída.' });

    const onChange = excluir(VENDA, 'Excluir');

    await waitFor(() => expect(onChange).toHaveBeenCalledTimes(1));
    expect(alerta).not.toHaveBeenCalled();
  });

  it('UC-SIDX-08 403 sem permissão continua avisando', async () => {
    responder(403, { message: 'Unauthorized action.' });

    const onChange = excluir(VENDA, 'Excluir');

    await waitFor(() => expect(alerta).toHaveBeenCalledWith('Falha ao excluir a venda.'));
    expect(onChange).not.toHaveBeenCalled();
  });
});
