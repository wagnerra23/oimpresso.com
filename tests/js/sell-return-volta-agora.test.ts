// SellReturn/Add — layout do protótipo Devolução V3: o operador digita "Volta agora", e a tela
// posta ao store() o ACUMULADO (UC-SRADD-07/08). Estes casos provam a ponte entre os dois.
import { describe, expect, it } from 'vitest';
import { acumuladoTxt, numUf, podeVoltar } from '@/Pages/SellReturn/_components/devolucaoCalculo';

describe('Volta agora → acumulado postado ao store()', () => {
  it('nada voltando numa linha já devolvida reenvia o já devolvido sem mexer (não reduz)', () => {
    expect(acumuladoTxt('1,00', '')).toBe('1,00');
    expect(acumuladoTxt('1,00', '0')).toBe('1,00');
  });

  it('nada voltando numa linha nunca devolvida posta vazio, como a tela antiga', () => {
    expect(acumuladoTxt('0,00', '')).toBe('');
  });

  it('soma o já devolvido ao que volta agora (UC-SRADD-07: 2 já + 1 agora = 3)', () => {
    expect(acumuladoTxt('2,00', '1')).toBe('3');
    expect(numUf(acumuladoTxt('2,00', '1'))).toBe(3);
  });

  it('fração com vírgula e sem separador de milhar — num_uf lê certo', () => {
    expect(acumuladoTxt('1,50', '0,25')).toBe('1,75');
    expect(numUf(acumuladoTxt('1.000,00', '500'))).toBe(1500);
    expect(acumuladoTxt('1.000,00', '500')).toBe('1500');
  });

  it('pode voltar = vendida − já devolvida, nunca negativo', () => {
    expect(podeVoltar(100, '10,00')).toBe(90);
    expect(podeVoltar(1, '1,00')).toBe(0);
    expect(podeVoltar(1, '2,00')).toBe(0);
  });
});
