// Contrato do fluxo Reconectar da Caixa/Atendimento (Camada 1 · bug 2026-06-18).
//
// Regressão travada: o `connect` devolve `state:'paired'` (+ `paired:true`) e o
// `status` devolve `state:'connected'`. O ReconnectModal só aceitava 'connected',
// então a verdade "Canal já pareado — sessão ativa" virava ERRO vermelho com botão
// "Já escaneei" fantasma. `isSessionActive` unifica os dois vocabulários — este
// teste é a catraca que impede a divergência de voltar (faltou no #2974).
//
// Nota: usa só expect/toBe (sem jest-dom — ver tests/js/setup.ts).

import { describe, it, expect } from 'vitest';

import { isSessionActive } from '@/Pages/Atendimento/CaixaUnificada/_components/reconnectState';

describe('isSessionActive — sessão já ativa = sucesso (não erro)', () => {
  it("aceita o vocabulário do connect ('paired'/paired:true) E do status ('connected')", () => {
    expect(isSessionActive({ ok: true, state: 'paired', paired: true })).toBe(true);
    expect(isSessionActive({ state: 'paired' })).toBe(true);
    expect(isSessionActive({ paired: true })).toBe(true);
    expect(isSessionActive({ state: 'connected' })).toBe(true);
  });

  it('mantém QR/desconectado/nulo como NÃO-sucesso (segue mostrando QR ou erro real)', () => {
    expect(isSessionActive({ state: 'qr_required' })).toBe(false);
    expect(isSessionActive({ state: 'disconnected' })).toBe(false);
    expect(isSessionActive({ state: null })).toBe(false);
    expect(isSessionActive({})).toBe(false);
    expect(isSessionActive(null)).toBe(false);
    expect(isSessionActive(undefined)).toBe(false);
  });
});
