/**
 * Linhas dos formulários de estoque — `Pages/Estoque/_components/linhas-estoque-dominio.ts`.
 *
 * Mexe em quantidade de estoque (REGRA MESTRE): os números saem no formato que o servidor lê.
 * Segundo caminho da prova: `tests/Feature/Estoque/EstoqueTransferenciaStoreTest.php` grava no
 * MySQL exatamente estes pedidos (quantidade '3', custo '12,5', base_unit_multiplier 1) e confere
 * origem 10 → 7 e destino 2 → 5. Aqui se prova que a tela monta esse mesmo pedido.
 */
import { describe, expect, it } from 'vitest';
import {
  type DadosDaLinha,
  erroDaLinha,
  itemAjuste,
  itemTransferencia,
  jaTem,
  linhaNova,
  quantidadeBase,
  somaLinhas,
  trocarUnidade,
} from '@/Pages/Estoque/_components/linhas-estoque-dominio';

const dados = (extra: Partial<DadosDaLinha> = {}): DadosDaLinha => ({
  product_id: 11,
  variation_id: 21,
  nome: 'Camiseta',
  sub_sku: 'CAM-01',
  enable_stock: 1,
  qty_available: 10,
  unidade: 'un',
  unit_id: 1,
  permite_decimal: false,
  custo: 12.5,
  sub_unidades: [],
  usa_lote: false,
  usa_validade: false,
  lotes: [],
  ...extra,
});

describe('UC-TRCRT-06 · pedido da transferência — o formato que o EstoqueTransferenciaStoreTest grava', () => {
  it('3 unidades a 12,50 saem como quantity "3" e unit_price "12,5"', () => {
    const l = { ...linhaNova(dados()), quantidade: 3 };
    expect(itemTransferencia(l)).toEqual({
      product_id: 11,
      variation_id: 21,
      enable_stock: 1,
      quantity: '3',
      unit_price: '12,5',
      product_unit_id: 1,
      base_unit_multiplier: '1',
    });
  });

  it('custo de milhar não ganha ponto (o num_uf leria milhar); envia as 2 casas que a tela mostra', () => {
    expect(itemTransferencia({ ...linhaNova(dados({ custo: 1500 })), quantidade: 1 }).unit_price).toBe('1500');
    expect(itemTransferencia({ ...linhaNova(dados({ custo: 1234.56 })), quantidade: 1 }).unit_price).toBe('1234,56');
    // 3 casas no cadastro: a tela mostra 12,13 e envia 12,13 — paridade com o @num_format da Blade.
    expect(itemTransferencia({ ...linhaNova(dados({ custo: 12.125 })), quantidade: 1 }).unit_price).toBe('12,13');
    expect(itemTransferencia({ ...linhaNova(dados({ permite_decimal: true })), quantidade: 1.5 }).quantity).toBe('1,5');
  });

  it('o multiplicador da unidade vai com PONTO: o servidor faz conta com ele sem num_uf', () => {
    const d = dados({ sub_unidades: [{ id: 7, nome: 'meia dúzia', multiplicador: 0.5, permite_decimal: true }] });
    const l = { ...trocarUnidade(linhaNova(d), 7), quantidade: 4 };
    const item = itemTransferencia(l);
    expect(item.base_unit_multiplier).toBe('0.5');
    expect(item.sub_unit_id).toBe(7);
    expect(quantidadeBase(l)).toBe(2);
    // custo na unidade escolhida = base × multiplicador (paridade Blade)
    expect(item.unit_price).toBe('6,25');
  });

  it('lote e linha já gravada (edição) seguem no item', () => {
    const l = { ...linhaNova(dados()), lot_no_line_id: 99, transaction_sell_lines_id: 55 };
    const item = itemTransferencia(l);
    expect(item.lot_no_line_id).toBe(99);
    expect(item.transaction_sell_lines_id).toBe(55);
  });
});

describe('UC-AJCRT-05 · pedido do ajuste — o formato que o StockAdjustmentController@store lê', () => {
  it('2 unidades a 8 saem como "2" e "8", sem unidade', () => {
    const l = { ...linhaNova(dados({ custo: 8 })), quantidade: 2 };
    expect(itemAjuste(l)).toEqual({ product_id: 11, variation_id: 21, enable_stock: 1, quantity: '2', unit_price: '8' });
  });
});

describe('UC-TRCRT-06 · recusas da linha (paridade Blade)', () => {
  it('não passa do saldo do local', () => {
    expect(erroDaLinha({ ...linhaNova(dados()), quantidade: 10 })).toBeNull();
    expect(erroDaLinha({ ...linhaNova(dados()), quantidade: 11 })).toMatch(/Só há 10/);
  });

  it('com lote escolhido, o limite é o saldo do lote', () => {
    const d = dados({ usa_lote: true, lotes: [{ purchase_line_id: 5, lot_number: 'L1', exp_date: null, qty_available: 4 }] });
    expect(erroDaLinha({ ...linhaNova(d), quantidade: 5, lot_no_line_id: 5 })).toMatch(/Só há 4/);
  });

  it('produto sem controle de estoque não tem limite; zero e fração indevida são recusados', () => {
    expect(erroDaLinha({ ...linhaNova(dados({ enable_stock: 0 })), quantidade: 500 })).toBeNull();
    expect(erroDaLinha({ ...linhaNova(dados()), quantidade: 0 })).toMatch(/maior que zero/);
    expect(erroDaLinha({ ...linhaNova(dados()), quantidade: 1.5 })).toMatch(/fração/);
  });

  it('a mesma variação não entra duas vezes; o subtotal exibido é qtd × custo', () => {
    const linhas = [{ ...linhaNova(dados()), quantidade: 3 }];
    expect(jaTem(linhas, 21)).toBe(true);
    expect(jaTem(linhas, 22)).toBe(false);
    expect(somaLinhas(linhas)).toBe(37.5);
  });
});
