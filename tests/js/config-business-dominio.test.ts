/**
 * UC-V387..UC-V392 — configurações do business na gravação da V3 (Sells/CreateV3.casos.md).
 *
 * Cada caso cita a regra do Blade/pos.js de onde veio; a V3 não inventa regra.
 */

import { readFileSync } from 'node:fs';

import { describe, expect, it } from 'vitest';

import {
  abaixoDoPrecoMinimo,
  arredondarTotal,
  bloqueioDaConfig,
  descontoDaVendaEfetivo,
  descontoInicial,
  estoqueInsuficiente,
  linhaParaSomar,
  type ConfigVenda,
  type LinhaLancavel,
} from '@/Pages/Sells/_components/v3/config-business-dominio';
import { montarPayloadVenda, totalParaGravar } from '@/Pages/Sells/_components/v3/gravacao-dominio';

const base: ConfigVenda = {
  dataEditavel: false,
  descontoDaVenda: true,
  descontoPadrao: 0,
  impostoDoPedido: true,
  precoMinimo: false,
  venderSemEstoque: false,
  comissionado: { tipo: null, obrigatorio: false },
  prazoObrigatorio: false,
  rascunho: true,
  aPrazo: true,
  recorrente: true,
  arredondamento: 0,
  somaNaLinha: true,
};
const semPrazo = { numero: null, tipo: null };

describe('UC-V387 · comissionado e prazo obrigatórios impedem a gravação', () => {
  it('UC-V387 · comissionado obrigatório com o select do Blade (user / cmsn_agnt) bloqueia', () => {
    for (const tipo of ['user', 'cmsn_agnt'] as const) {
      const c = { ...base, comissionado: { tipo, obrigatorio: true } };
      expect(bloqueioDaConfig(c, semPrazo)).toMatch(/comissionado/);
    }
  });

  it('UC-V387 · comissionado = usuário logado não bloqueia: o store põe o próprio usuário', () => {
    const c = { ...base, comissionado: { tipo: 'logged_in_user' as const, obrigatorio: true } };
    expect(bloqueioDaConfig(c, semPrazo)).toBeNull();
  });

  it('UC-V387 · comissionado desligado ou não obrigatório não bloqueia', () => {
    expect(bloqueioDaConfig({ ...base, comissionado: { tipo: null, obrigatorio: true } }, semPrazo)).toBeNull();
    expect(bloqueioDaConfig({ ...base, comissionado: { tipo: 'user', obrigatorio: false } }, semPrazo)).toBeNull();
  });

  it('UC-V387 · prazo obrigatório bloqueia sem prazo no cliente e libera com prazo', () => {
    const c = { ...base, prazoObrigatorio: true };
    expect(bloqueioDaConfig(c, semPrazo)).toMatch(/prazo/);
    expect(bloqueioDaConfig(c, { numero: '', tipo: 'days' })).toMatch(/prazo/);
    expect(bloqueioDaConfig(c, { numero: 30, tipo: null })).toMatch(/prazo/);
    expect(bloqueioDaConfig(c, { numero: 30, tipo: 'days' })).toBeNull();
    expect(bloqueioDaConfig(base, semPrazo)).toBeNull();
  });

  it('UC-V387 · o prazo do cliente vai no payload; sem prazo segue vazio como antes', () => {
    const venda = {
      locationId: 1,
      contactId: 2,
      transactionDate: '30/10/2026 10:00',
      itens: [
        { productId: 5, variationId: 6, nome: 'X', sku: 'X', qtd: '1', preco: '10,00', desc: '0', acr: '0', enableStock: false, tipoProduto: 'single' },
      ],
      descTipo: 'percentual' as const,
      descVal: '0',
      acr: '0',
      frete: '0',
      pagamentos: [],
    };
    const com = montarPayloadVenda({ ...venda, prazo: { numero: 30, tipo: 'days' } });
    const sem = montarPayloadVenda(venda);
    expect(com.ok && com.payload.pay_term_number).toBe(30);
    expect(com.ok && com.payload.pay_term_type).toBe('days');
    expect(sem.ok && sem.payload.pay_term_number).toBe('');
    expect(sem.ok && sem.payload.pay_term_type).toBe('days');
  });
});

const linha = (o: Partial<LinhaLancavel>): LinhaLancavel => ({
  variationId: 6,
  medidas: null,
  qtd: '1',
  preco: '10,00',
  desc: '0',
  acr: '0',
  ...o,
});

describe('UC-V388 · lançar o mesmo produto soma na linha conforme item_addition_method', () => {
  const itens = [linha({ variationId: 9 }), linha({ variationId: 6, qtd: '2' })];

  it('UC-V388 · método 1: o mesmo produto, mesmo preço, cai na linha existente', () => {
    expect(linhaParaSomar(itens, linha({}), true)).toBe(1);
  });

  it('UC-V388 · método 0: sempre linha nova', () => {
    expect(linhaParaSomar(itens, linha({}), false)).toBe(-1);
  });

  it('UC-V388 · preço, desconto ou acréscimo diferentes abrem linha nova (somar gravaria pelo preço antigo)', () => {
    expect(linhaParaSomar(itens, linha({ preco: '9,90' }), true)).toBe(-1);
    expect(linhaParaSomar(itens, linha({ desc: '5' }), true)).toBe(-1);
    expect(linhaParaSomar(itens, linha({ acr: '2' }), true)).toBe(-1);
    expect(linhaParaSomar(itens, linha({ preco: '10' }), true)).toBe(1); // mesmo valor, outra grafia
  });

  it('UC-V388 · item com medidas (dimensional) e item sem variação não somam', () => {
    expect(linhaParaSomar(itens, linha({ medidas: '1× 0,50×0,10m' }), true)).toBe(-1);
    expect(linhaParaSomar([linha({ medidas: '1× 0,50×0,10m' })], linha({}), true)).toBe(-1);
    expect(linhaParaSomar(itens, linha({ variationId: null }), true)).toBe(-1);
  });
});

/* ─── [V0] Prova dupla (REGRA MESTRE): a função contra conta à mão em centavos inteiros ─── */

const item = (o: Record<string, unknown> = {}) => ({
  productId: 5,
  variationId: 6,
  nome: 'Camiseta',
  sku: 'C1',
  qtd: '3',
  preco: '49,90',
  desc: '0',
  acr: '0',
  enableStock: true,
  tipoProduto: 'single',
  precoVenda: 49.9,
  estoque: 10,
  ...o,
});
const venda = (o: Record<string, unknown> = {}) => ({
  locationId: 1,
  contactId: 2,
  transactionDate: '30/10/2026 10:00',
  itens: [item()],
  descTipo: 'percentual' as const,
  descVal: '0',
  acr: '0',
  frete: '0',
  pagamentos: [],
  ...o,
});

describe('UC-V389 · desconto da venda: some com disable_discount e nasce com default_sales_discount', () => {
  it('UC-V389 · desligado, o desconto digitado vale 0 no total e no payload', () => {
    const c = { ...base, descontoDaVenda: false };
    expect(descontoDaVendaEfetivo(c, 'fixo', '20,00')).toEqual({ descTipo: 'percentual', descVal: '0' });
    // 3 × 49,90 = 149,70; com 10% seria 134,73 — desligado fica 149,70.
    const r = montarPayloadVenda(venda({ descVal: '10', config: c }));
    expect(r.ok && r.total).toBe(149.7);
    expect(r.ok && r.payload.discount_amount).toBe(0);
    expect(r.ok && r.payload.final_total).toBe(149.7);
    const ligado = montarPayloadVenda(venda({ descVal: '10', config: base }));
    expect(ligado.ok && ligado.total).toBe(134.73);
  });

  it('UC-V389 · desligado, o teto do usuário não reprova um desconto que não existe', () => {
    const r = montarPayloadVenda(venda({ descVal: '50', descontoMaximo: 5, config: { ...base, descontoDaVenda: false } }));
    expect(r.ok).toBe(true);
  });

  it('UC-V389 · a venda nasce com o desconto padrão em %; desligado ou zero, nasce 0', () => {
    expect(descontoInicial({ ...base, descontoPadrao: 2.5 })).toBe('2,50');
    expect(descontoInicial({ ...base, descontoPadrao: 0 })).toBe('0,00');
    expect(descontoInicial({ ...base, descontoPadrao: 2.5, descontoDaVenda: false })).toBe('0,00');
  });
});

describe('UC-V390 · preço mínimo (enable_msp) no lugar dos 85% fixos', () => {
  it('UC-V390 · com enable_msp, um centavo abaixo do preço de venda bloqueia; igual passa', () => {
    const c = { ...base, precoMinimo: true };
    expect(abaixoDoPrecoMinimo(c, [item({ preco: '49,89' })])).toMatch(/preço mínimo/);
    expect(abaixoDoPrecoMinimo(c, [item({ preco: '49,90' })])).toBeNull();
    expect(abaixoDoPrecoMinimo(c, [item({ preco: '60,00' })])).toBeNull();
    expect(montarPayloadVenda(venda({ itens: [item({ preco: '40,00' })], config: c })).ok).toBe(false);
  });

  it('UC-V390 · o desconto da linha não conta, como no Blade (compara o preço unitário)', () => {
    expect(abaixoDoPrecoMinimo({ ...base, precoMinimo: true }, [item({ preco: '49,90', desc: '30' })])).toBeNull();
  });

  it('UC-V390 · sem enable_msp não há piso: 50% da tabela grava', () => {
    expect(abaixoDoPrecoMinimo(base, [item({ preco: '24,95' })])).toBeNull();
    expect(montarPayloadVenda(venda({ itens: [item({ preco: '24,95' })], config: base })).ok).toBe(true);
  });
});

describe('UC-V391 · vender sem estoque só com allow_overselling', () => {
  it('UC-V391 · sem a regra, quantidade acima do saldo bloqueia — somando as linhas da mesma variação', () => {
    expect(estoqueInsuficiente(base, [item({ qtd: '10' })])).toBeNull();
    expect(estoqueInsuficiente(base, [item({ qtd: '10,001' })])).toMatch(/estoque/);
    expect(estoqueInsuficiente(base, [item({ qtd: '6' }), item({ qtd: '5' })])).toMatch(/estoque/);
    expect(montarPayloadVenda(venda({ itens: [item({ qtd: '11' })], config: base })).ok).toBe(false);
  });

  it('UC-V391 · com allow_overselling, ou produto sem controle de estoque, passa', () => {
    expect(estoqueInsuficiente({ ...base, venderSemEstoque: true }, [item({ qtd: '99' })])).toBeNull();
    expect(estoqueInsuficiente(base, [item({ qtd: '99', enableStock: false })])).toBeNull();
    expect(estoqueInsuficiente(base, [item({ qtd: '99', estoque: null })])).toBeNull();
  });
});

/** Caminho 2, escrito aqui sem ler a função: `__round` do Blade em centavos, metade para cima. */
function arredondaAMao(totalCentavos: number, multCentavos: number): number {
  const resto = totalCentavos % multCentavos;
  return resto * 2 >= multCentavos ? totalCentavos - resto + multCentavos : totalCentavos - resto;
}

describe('UC-V392 · arredondamento do total por amount_rounding_method, só no fim', () => {
  it('UC-V392 · números concretos dos 4 múltiplos do Blade', () => {
    expect(arredondarTotal(156.23, 1)).toEqual({ total: 156, diferenca: -0.23 });
    expect(arredondarTotal(156.5, 1)).toEqual({ total: 157, diferenca: 0.5 });
    expect(arredondarTotal(156.23, 0.5)).toEqual({ total: 156, diferenca: -0.23 });
    expect(arredondarTotal(156.26, 0.5)).toEqual({ total: 156.5, diferenca: 0.24 });
    expect(arredondarTotal(156.23, 0.1)).toEqual({ total: 156.2, diferenca: -0.03 });
    expect(arredondarTotal(156.23, 0.05)).toEqual({ total: 156.25, diferenca: 0.02 });
    expect(arredondarTotal(156.23, 0)).toEqual({ total: 156.23, diferenca: 0 });
  });

  it('UC-V392 · a função bate com a conta à mão em centavos para 5.000 totais × 4 múltiplos', () => {
    for (const mult of [1, 0.5, 0.1, 0.05]) {
      const m = Math.round(mult * 100);
      for (let c = 0; c < 5000; c++) {
        const esperado = arredondaAMao(c * 7 + 3, m);
        const r = arredondarTotal((c * 7 + 3) / 100, mult);
        expect(Math.round(r.total * 100)).toBe(esperado);
        expect(Math.round(r.diferenca * 100)).toBe(esperado - (c * 7 + 3));
      }
    }
  });

  it('UC-V392 · arredonda o total de 2 casas, depois do desconto; o payload leva round_off_amount', () => {
    // 3 × 49,90 = 149,70; −2,5% = 145,9575 → 145,96 (2 casas) → múltiplo 1 = 146,00 (+0,04).
    const c = { ...base, arredondamento: 1 };
    const r = montarPayloadVenda(venda({ descVal: '2,5', config: c }));
    expect(r.ok && r.total).toBe(146);
    expect(r.ok && r.payload.final_total).toBe(146);
    expect(r.ok && r.payload.round_off_amount).toBe(0.04);
    expect(totalParaGravar(venda({ descVal: '2,5', config: c }))).toBe(146);
    // Sem arredondamento, o payload é o de antes (sem a chave).
    const sem = montarPayloadVenda(venda({ descVal: '2,5', config: base }));
    expect(sem.ok && sem.total).toBe(145.96);
    expect(sem.ok && 'round_off_amount' in sem.payload).toBe(false);
  });
});

describe('UC-V392 · a fixture da gravação, arredondada', () => {
  it('UC-V392 · 156,23 com múltiplo 1 sai 156 com round_off_amount −0,23 (o Pest posta esses números)', () => {
    const fx = JSON.parse(readFileSync('tests/fixtures/sells-v3/venda-simples.json', 'utf8')) as {
      entrada: Parameters<typeof montarPayloadVenda>[0];
    };
    const r = montarPayloadVenda({ ...fx.entrada, config: { ...base, arredondamento: 1 } });
    expect(r.ok && r.payload.final_total).toBe(156);
    expect(r.ok && r.payload.round_off_amount).toBe(-0.23);
    expect(r.ok && r.payload.discount_amount).toBe(5);
  });
});
