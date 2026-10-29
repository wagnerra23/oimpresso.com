import { describe, expect, it } from 'vitest';
import { camposDeSubtipo } from '../../resources/js/Pages/Sells/_components/subtipoVenda';

// UC-S03 (Sells/Create.casos.md) — lado do envio. O lado do servidor (venda gravada
// como reparo, mesmo valor e mesmo estoque) é tests/Feature/Sells/SellsRepairSubtipoContratoTest.php.
describe('UC-S03 · camposDeSubtipo', () => {
  it('PDV aberto como reparo envia sub_type=repair e print_label=0', () => {
    expect(camposDeSubtipo('repair')).toEqual({ sub_type: 'repair', print_label: 0 });
  });

  it('venda comum não envia tipo nenhum', () => {
    expect(camposDeSubtipo(null)).toEqual({});
    expect(camposDeSubtipo(undefined)).toEqual({});
    expect(camposDeSubtipo('')).toEqual({});
  });

  it('tipo fora da lista vira venda comum (só repair foi decidido)', () => {
    expect(camposDeSubtipo('invoice')).toEqual({});
    expect(camposDeSubtipo('REPAIR')).toEqual({});
  });
});

// UC-SBON-01 (Sells/Create.casos.md, P-154) — bonificação marcada na tela.
describe('UC-SBON-01 · camposDeSubtipo com bonificação', () => {
  it('venda marcada como bonificação envia sub_type=bonificacao', () => {
    expect(camposDeSubtipo(null, true)).toEqual({ sub_type: 'bonificacao' });
  });

  it('sem a marca, a venda comum segue sem tipo', () => {
    expect(camposDeSubtipo(null, false)).toEqual({});
  });

  it('reparo vence a marca de bonificação', () => {
    expect(camposDeSubtipo('repair', true)).toEqual({ sub_type: 'repair', print_label: 0 });
  });
});
