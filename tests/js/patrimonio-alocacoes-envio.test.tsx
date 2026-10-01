// UC-ALOC-06 / UC-ALOC-07 / UC-ALOC-08 · Patrimônio/Alocações — o que os drawers MANDAM.
//
// DUPLA PROVA (REGRA MESTRE — quantidade): este arquivo é o caminho 1. Ele fixa as STRINGS
// exatas do corpo do POST. O caminho 2 é o Pest `AlocacoesFormContratoTest`, que posta ESTAS
// MESMAS strings no controller e lê o banco. Se um lado mudar a string, o outro deixa de
// provar a mesma coisa — por isso os literais são repetidos lá, não importados.
//
// @see resources/js/Pages/Patrimonio/Alocacoes.casos.md

import { describe, it, expect, afterEach, vi } from 'vitest';
import { render, screen, cleanup, fireEvent } from '@testing-library/react';
import {
  montarEnvioAlocacao,
  montarEnvioDevolucao,
  paraDataHoraDoNegocio,
  validarAlocacao,
  validarDevolucao,
} from '@/Pages/Patrimonio/_alocacoes/envio';

const post = vi.fn();
const put = vi.fn();
const del = vi.fn();
vi.mock('@inertiajs/react', () => ({
  router: { post: (...a: unknown[]) => post(...a), put: (...a: unknown[]) => put(...a), delete: (...a: unknown[]) => del(...a), get: vi.fn() },
}));

import { AlocarDrawer, DevolverDrawer } from '@/Pages/Patrimonio/_alocacoes/Drawers';

afterEach(() => { cleanup(); post.mockReset(); put.mockReset(); del.mockReset(); });

describe('UC-ALOC-06 · strings do envio (caminho 1 da dupla prova)', () => {
  it('alocação: quantidade vírgula-decimal sem milhar, data no formato da empresa', () => {
    const corpo = montarEnvioAlocacao({
      assetId: '7', receiver: '3', quantidade: 2.5, alocadoEm: '2026-09-30T14:05',
      prazo: '2026-10-15', motivo: '  Balcão  ', refNo: '',
    }, 'd/m/Y', false);
    expect(corpo).toEqual({
      asset_id: '7', receiver: '3', quantity: '2,5',
      transaction_datetime: '30/09/2026 14:05', allocated_upto: '15/10/2026', reason: 'Balcão',
    });
  });

  it('quantidade 1234 NÃO ganha separador de milhar (o `num_uf` leria "1.234" como 1,234)', () => {
    const corpo = montarEnvioAlocacao({
      assetId: '1', receiver: '1', quantidade: 1234, alocadoEm: '2026-09-30T08:00',
      prazo: '', motivo: '', refNo: 'ALO-9',
    }, 'd/m/Y', false);
    expect(corpo.quantity).toBe('1234');
    expect(corpo.allocated_upto).toBe('');
    expect(corpo.ref_no).toBe('ALO-9');
  });

  it('relógio 12h da empresa: hora vira "h:i A", como o `uf_date` concatena', () => {
    expect(paraDataHoraDoNegocio('2026-09-30T14:05', 'd/m/Y', true)).toBe('30/09/2026 02:05 PM');
    expect(paraDataHoraDoNegocio('2026-09-30T00:30', 'm/d/Y', true)).toBe('09/30/2026 12:30 AM');
  });

  it('devolução: manda parent_id e NÃO manda asset_id (o servidor tira da alocação)', () => {
    const corpo = montarEnvioDevolucao(42, { quantidade: 1, devolvidoEm: '2026-09-30T09:00', motivo: 'Fim', refNo: '' }, 'd/m/Y', false);
    expect(corpo).toEqual({ parent_id: '42', quantity: '1', transaction_datetime: '30/09/2026 09:00', reason: 'Fim' });
    expect(corpo).not.toHaveProperty('asset_id');
  });

  it('forma, não saldo: o cliente só recusa zero e campo vazio', () => {
    expect(validarAlocacao({ assetId: '', receiver: '', quantidade: 0, alocadoEm: '', prazo: '', motivo: '', refNo: '' }))
      .toEqual(expect.objectContaining({ assetId: expect.any(String), receiver: expect.any(String), quantidade: expect.any(String), alocadoEm: expect.any(String) }));
    // 999 unidades de um bem com saldo 1: o cliente NÃO recusa — quem decide é a trava do servidor.
    expect(validarAlocacao({ assetId: '1', receiver: '1', quantidade: 999, alocadoEm: '2026-09-30T08:00', prazo: '', motivo: '', refNo: '' })).toEqual({});
    expect(validarDevolucao({ quantidade: 0, devolvidoEm: '', motivo: '', refNo: '' })).toHaveProperty('quantidade');
  });
});

const alocar = {
  modo: 'alocar' as const,
  bens: [{ id: 7, nome: 'Plotter', saldo: 3 }],
  pessoas: [{ id: 3, nome: 'Ana Souza' }],
  asset_id: 7,
};

describe('UC-ALOC-07 · drawer de alocar', () => {
  it('abre como diálogo modal rotulado, mostra o saldo que o servidor mandou e posta na rota certa', () => {
    render(<AlocarDrawer formulario={alocar} formatoData="d/m/Y" hora12={false} onClose={() => {}} />);
    const dialogo = screen.getByRole('dialog', { name: 'Alocar recurso' });
    expect(dialogo).toBeTruthy();
    expect(screen.getByText('Saldo livre agora: 3')).toBeTruthy();
    // Sem pessoa escolhida o cliente segura, e o erro é anunciado (role=alert).
    fireEvent.click(screen.getByRole('button', { name: 'Alocar' }));
    expect(post).not.toHaveBeenCalled();
    expect(screen.getByText('Escolha quem recebe o bem.').getAttribute('role')).toBe('alert');
  });

  it('editar: PUT na alocação, e o erro de saldo do SERVIDOR aparece inline no campo quantidade', () => {
    const msg = 'Saldo insuficiente para alocar: pedido de 5 unidade(s), disponível 3.';
    put.mockImplementation((_url: string, _corpo: unknown, opt: { onError: (e: Record<string, string>) => void; onFinish: () => void }) => {
      opt.onError({ quantity: msg });
      opt.onFinish();
    });
    const editar = { ...alocar, modo: 'editar' as const, alocacao: { id: 9, ref_no: 'ALO-9', receiver: 3, quantidade: 5, alocado_em: '2026-09-30T08:00', prazo: '', motivo: '' } };
    render(<AlocarDrawer formulario={editar} formatoData="d/m/Y" hora12={false} onClose={() => {}} />);
    fireEvent.click(screen.getByRole('button', { name: 'Salvar alocação' }));
    expect(post).not.toHaveBeenCalled();
    expect(put).toHaveBeenCalledWith('/asset/allocation/9', expect.objectContaining({ quantity: '5', receiver: '3' }), expect.anything());
    const erro = screen.getByText(msg);
    expect(erro.getAttribute('role')).toBe('alert');
    expect(erro.id).toBe('al-qtd-erro');
  });
});

const devolver = {
  modo: 'devolver' as const,
  alocacao: { id: 42, ref_no: 'ALO-42', bem: 'Plotter', recebido_por: 'Ana Souza', quantidade: 4, devolvido: 1, restante: 3 },
  devolucoes: [{ id: 50, ref_no: 'REV-50', quantidade: 1, data: '29/09/2026 10:00', autor: 'Wagner', motivo: null }],
};

describe('UC-ALOC-08 · drawer de devolver + Excluir devolução', () => {
  it('lista cada devolução (grão 1:N) e o Excluir chama DELETE na devolução, só depois de confirmar', () => {
    render(<DevolverDrawer formulario={devolver} formatoData="d/m/Y" hora12={false} onClose={() => {}} />);
    expect(screen.getByRole('dialog', { name: 'Devolver ALO-42' })).toBeTruthy();
    expect(screen.getByTestId('saldo-devolucao').textContent).toContain('3 a devolver');

    const confirmar = vi.spyOn(window, 'confirm').mockReturnValueOnce(false).mockReturnValueOnce(true);
    const excluir = screen.getByRole('button', { name: 'Excluir devolução REV-50' });
    fireEvent.click(excluir);
    expect(del).not.toHaveBeenCalled();
    fireEvent.click(excluir);
    expect(del).toHaveBeenCalledWith('/asset/revocation/50', expect.anything());
    confirmar.mockRestore();
  });

  it('devolução começa com o restante e posta parent_id da alocação', () => {
    render(<DevolverDrawer formulario={devolver} formatoData="d/m/Y" hora12={false} onClose={() => {}} />);
    fireEvent.click(screen.getByRole('button', { name: 'Registrar devolução' }));
    expect(post).toHaveBeenCalledTimes(1);
    const [url, corpo] = post.mock.calls[0];
    expect(url).toBe('/asset/revocation');
    expect(corpo).toEqual(expect.objectContaining({ parent_id: '42', quantity: '3' }));
  });

  it('alocação toda devolvida: sem formulário, só a lista e Fechar', () => {
    render(<DevolverDrawer formulario={{ ...devolver, alocacao: { ...devolver.alocacao, devolvido: 4, restante: 0 } }}
      formatoData="d/m/Y" hora12={false} onClose={() => {}} />);
    expect(screen.queryByRole('button', { name: 'Registrar devolução' })).toBeNull();
    expect(screen.getByRole('button', { name: 'Fechar' })).toBeTruthy();
  });
});
