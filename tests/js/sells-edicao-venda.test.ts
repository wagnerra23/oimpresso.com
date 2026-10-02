import { describe, expect, it } from 'vitest';
import {
  linhaDoBanco,
  linhaParaEnvio,
  numeroParaEnvio,
  precoComDesconto,
  subtotalLinha,
} from '../../resources/js/Pages/Sells/_components/edicaoVenda';

// UC-SEDIT-09 (Sells/Edit.casos.md) — regras de valor da edição React. O lado do servidor
// (total por diferença, salvar sem mexer não muda nada) é tests/Feature/Sells/SellsEditContratoTest.php.
describe('UC-SEDIT-09 · edicaoVenda', () => {
  it('número vai em pt-BR com 4 casas — 1.125 nunca vira "1.125" (que o num_uf leria como milhar)', () => {
    expect(numeroParaEnvio(1.125)).toBe('1,1250');
    expect(numeroParaEnvio(1250)).toBe('1250,0000');
    expect(numeroParaEnvio(Number.NaN)).toBe('0,0000');
  });

  it('desconto fixo é POR UNIDADE; % sobre o preço; nunca negativo', () => {
    expect(precoComDesconto(100, 10, 'fixed')).toBe(90);
    expect(precoComDesconto(100, 10, 'percentage')).toBe(90);
    expect(precoComDesconto(5, 10, 'fixed')).toBe(0);
  });

  it('pré-fill usa o preço ANTES do desconto, não o já descontado (antes reaplicava o desconto)', () => {
    const l = linhaDoBanco({
      transaction_sell_lines_id: 7, product_id: 1, variation_id: 2, quantity_ordered: '2.0000',
      unit_price_before_discount: '100.0000', sell_price_inc_tax: '90.0000',
      line_discount_amount: '10.0000', line_discount_type: 'fixed', item_tax: '0.0000',
    });
    expect(l.unit_price).toBe(100);
    expect(l.discount).toBe(10);
    expect(subtotalLinha(l)).toBe(180); // 2 × (100 − 10)
  });

  it('linha NÃO mexida volta com os valores gravados — mesmo os inconsistentes da tela de criação', () => {
    // Venda da criação React: inc gravado SEM o desconto da linha.
    const l = linhaDoBanco({
      product_id: 1, variation_id: 2, quantity_ordered: '1', unit_price_before_discount: '100',
      sell_price_inc_tax: '100', line_discount_amount: '10', line_discount_type: 'percentage', item_tax: '0',
    });
    expect(linhaParaEnvio(l)).toMatchObject({ unit_price: '100,0000', unit_price_inc_tax: '100,0000', line_discount_amount: '10,0000' });
  });

  it('mudar só a quantidade mantém o preço unitário gravado', () => {
    const l = { ...linhaDoBanco({ product_id: 1, quantity_ordered: '1', unit_price_before_discount: '50', sell_price_inc_tax: '50', item_tax: '0' }), quantity: 3 };
    expect(linhaParaEnvio(l)).toMatchObject({ quantity: '3,0000', unit_price_inc_tax: '50,0000' });
  });

  it('mudar preço ou desconto recalcula na regra do servidor', () => {
    const base = linhaDoBanco({ product_id: 1, quantity_ordered: '2', unit_price_before_discount: '100', sell_price_inc_tax: '100', line_discount_amount: '0', line_discount_type: 'fixed', item_tax: '0' });
    expect(linhaParaEnvio({ ...base, discount: 15 })).toMatchObject({ unit_price_inc_tax: '85,0000', line_discount_amount: '15,0000' });
    expect(linhaParaEnvio({ ...base, unit_price: 80 })).toMatchObject({ unit_price_inc_tax: '80,0000' });
  });

  it('imposto da linha mantém a proporção quando o preço muda', () => {
    const l = linhaDoBanco({ product_id: 1, quantity_ordered: '1', unit_price_before_discount: '100', sell_price_inc_tax: '110', item_tax: '10', tax_id: 3 });
    expect(linhaParaEnvio({ ...l, unit_price: 200 })).toMatchObject({ item_tax: '20,0000', unit_price_inc_tax: '220,0000', tax_id: 3 });
  });
});
