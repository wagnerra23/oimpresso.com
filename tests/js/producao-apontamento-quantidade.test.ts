// US-PCP-021 · UC-PCMAT-08 — a quantidade que a tela do operador manda é a que o servidor baixa do
// estoque (regra mestre): vírgula vira ponto, ponto com vírgula é milhar, e o que mostra no botão bate.
import { describe, expect, it } from 'vitest';
import { quantidadeBr, quantidadeParaEnvio } from '../../Modules/Producao/Resources/js/Pages/Producao/Apontamento/_lib/quantidade';

describe('quantidadeParaEnvio', () => {
  it.each([
    ['2,5', '2.5'], ['2.5', '2.5'], ['10', '10'], ['1.000,25', '1000.25'], [' 0,75 ', '0.75'], ['3,1234', '3.1234'],
  ])('%s → %s', (texto, esperado) => expect(quantidadeParaEnvio(texto)).toBe(esperado));

  it.each(['', '0', '0,0', '2,12345', 'abc', '-1', '1,2,3', '2.5.1'])('%s não envia', (texto) => expect(quantidadeParaEnvio(texto)).toBeNull());
});

describe('quantidadeBr', () => {
  it.each([['2.5000', '2,5'], ['10', '10'], ['3.0000', '3'], ['100.0000', '100'], ['0.7500', '0,75'], ['1000.25', '1000,25']])(
    '%s → %s', (q, esperado) => expect(quantidadeBr(q)).toBe(esperado));
});
