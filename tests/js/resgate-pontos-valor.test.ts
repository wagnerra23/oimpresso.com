// US-SELL-079 — a conta do resgate na tela é a MESMA do servidor.
// A tabela abaixo é a mesma de tests/Feature/Sells/ResgatePontosContratoTest.php (UC-SRP-01):
// se uma das duas mudar, a outra reprova (dupla prova da regra mestre de valor).
import { describe, expect, it } from 'vitest';
import { valorDoResgate } from '@/Pages/Sells/_components/resgate-pontos-valor';

const TABELA: Array<[number, string, string]> = [
  [50, '0.1000', '5.00'],
  [7, '0.1500', '1.05'],
  [3, '0.3333', '1.00'], // 0,9999 → 1,00
  [1, '0.0050', '0.01'], // meio centavo sobe
  [1, '0.0049', '0.00'],
  [1234, '1.2500', '1542.50'],
  [0, '0.5000', '0.00'],
];

describe('valorDoResgate (tela) — UC-SRP-01', () => {
  it.each(TABELA)('%i pontos × %s = %s', (pontos, valorPonto, esperado) => {
    expect(valorDoResgate(pontos, valorPonto)).toBe(esperado);
  });
});
