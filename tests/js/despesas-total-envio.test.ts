import { describe, expect, it } from 'vitest';
import { totalParaEnvio } from '../../resources/js/Pages/Financeiro/Despesas/_lib/total-envio';

// UC-DESPES-06 [V0] — o que a Nova despesa manda no `final_total`. O `num_uf` do servidor lê "." como
// milhar quando há 3+ dígitos depois dele (incidente 2026-06-05), então a tela nunca manda float cru:
// sempre 2 casas e vírgula decimal. O lado servidor ("1234,56" → 1234.56) está no DespesasIndexContratoTest.
describe('UC-DESPES-06 · total da Nova despesa vai com 2 casas e vírgula decimal', () => {
  it.each([
    [1234.56, '1234,56'],
    [1234.5, '1234,50'],
    [80, '80,00'],
    [204.99605, '205,00'],
    [0.1 + 0.2, '0,30'],
  ])('%p → %p', (entrada, esperado) => {
    expect(totalParaEnvio(entrada)).toBe(esperado);
  });

  it('nunca manda ponto: nada que o num_uf possa ler como milhar', () => {
    for (const v of [1234.567, 25000, 99999.999]) expect(totalParaEnvio(v)).not.toContain('.');
  });
});
