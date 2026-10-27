import { describe, expect, it } from 'vitest';
import {
  camposDespesas,
  despesasVazias,
  somaDespesas,
  valorParaEnvio,
} from '../../resources/js/Pages/Purchase/_components/compra-envio';

// UC-PURCRE-14 [V0] — o que a Nova compra manda em total, frete e despesas. O `num_uf` do servidor
// lê "." + 3 dígitos como milhar (incidente 2026-06-05). A outra perna da prova, o servidor gravando
// o número certo, está em tests/Feature/Purchase/PurchaseCreateDespesasContratoTest.php.
describe('UC-PURCRE-14 · valores da compra vão com 2 casas e vírgula decimal', () => {
  it.each([
    [3.495, '3,5'], // 1,5 × 2,33: antes ia "3.495" e gravava 3495
    [1234.56, '1234,56'],
    [1234.5, '1234,5'],
    [80, '80'],
    [0.1 + 0.2, '0,3'],
  ])('%p → %p', (entrada, esperado) => {
    expect(valorParaEnvio(entrada)).toBe(esperado);
  });

  it('nunca manda ponto: nada que o num_uf leia como milhar', () => {
    for (const v of [3.495, 1234.567, 25000, 99999.999]) expect(valorParaEnvio(v)).not.toContain('.');
  });
});

describe('UC-PURCRE-14 · despesas adicionais somam ao total e viram os 8 campos do store', () => {
  it('soma em centavos', () => {
    const d = despesasVazias();
    d[0] = { nome: 'Frete extra', valor: 12.5 };
    d[2] = { nome: 'Seguro', valor: 0.1 + 0.2 };
    expect(somaDespesas(d)).toBe(12.8);
  });

  it('par com valor vai preenchido; par zerado vai vazio (o store não grava)', () => {
    const d = despesasVazias();
    d[0] = { nome: ' Frete extra ', valor: 12.5 };
    d[1] = { nome: 'Esquecida', valor: 0 };
    expect(camposDespesas(d)).toEqual({
      additional_expense_key_1: 'Frete extra',
      additional_expense_value_1: '12,5',
      additional_expense_key_2: '',
      additional_expense_value_2: '',
      additional_expense_key_3: '',
      additional_expense_value_3: '',
      additional_expense_key_4: '',
      additional_expense_value_4: '',
    });
  });
});
