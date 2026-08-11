/**
 * Prova do cálculo de comissão — onda 5 do preview `/sells/create-v3`.
 *
 * ⚠️ TIER 0 — comissão é dinheiro que a empresa deve a alguém. A REGRA MESTRE exige
 * dois caminhos: a função real × aritmética à mão a partir da definição.
 *
 * O que este teste protege, além da conta:
 *   - a escolha de BASE muda quanto se paga, e é decisão de incentivo (bruto paga o
 *     vendedor para dar desconto; margem alinha);
 *   - o GATILHO por recebimento é o que impede pagar comissão de venda que o cliente
 *     nunca pagou;
 *   - a faixa é por VALOR TOTAL, não progressiva por fatia — confundir os dois muda
 *     o que a empresa paga.
 */

import { describe, expect, it } from 'vitest';

import {
  comeMaisQueMetadeDaMargem,
  comissaoDoBeneficiario,
  comissaoLiberada,
  comissaoTotal,
  estornoProporcional,
  percentualDaFaixa,
  percentualSobreALiquida,
  type Beneficiario,
  type TotaisDaVenda,
} from '@/Pages/Sells/_components/v3/comissao-dominio';

const tot: TotaisDaVenda = { bruto: 10000, liquido: 9000, margem: 3000 };

const ben = (over: Partial<Beneficiario> = {}): Beneficiario => ({
  k: 1,
  tipo: 'funcionario',
  nome: 'Kamila Reis',
  base: 'liquido',
  regra: 'percentual',
  pct: '3',
  valor: '0',
  ...over,
});

describe('base de cálculo — a escolha muda quanto se paga', () => {
  it('caminho A × caminho B: 3% sobre cada base dá números diferentes', () => {
    // caminho A — a função
    const sobreLiquido = comissaoDoBeneficiario(ben({ base: 'liquido' }), tot);
    const sobreBruto = comissaoDoBeneficiario(ben({ base: 'bruto' }), tot);
    const sobreMargem = comissaoDoBeneficiario(ben({ base: 'margem' }), tot);

    // caminho B — à mão, da definição
    expect(sobreLiquido).toBeCloseTo((9000 * 3) / 100, 2); // 270
    expect(sobreBruto).toBeCloseTo((10000 * 3) / 100, 2); // 300
    expect(sobreMargem).toBeCloseTo((3000 * 3) / 100, 2); // 90

    // e a consequência de incentivo: bruto paga MAIS que líquido quando há desconto
    expect(sobreBruto).toBeGreaterThan(sobreLiquido);
  });

  it('regra fixa ignora a base — o mesmo valor em qualquer uma', () => {
    for (const base of ['liquido', 'bruto', 'margem'] as const) {
      expect(comissaoDoBeneficiario(ben({ base, regra: 'fixo', valor: '250,00' }), tot)).toBeCloseTo(250, 2);
    }
  });
});

describe('faixa progressiva — por VALOR TOTAL, não por fatia', () => {
  it('cada faixa devolve seu percentual', () => {
    expect(percentualDaFaixa(1000)).toBe(2); // até 5k
    expect(percentualDaFaixa(5000)).toBe(2); // limite inclusivo
    expect(percentualDaFaixa(5000.01)).toBe(3);
    expect(percentualDaFaixa(20000)).toBe(3);
    expect(percentualDaFaixa(20000.01)).toBe(4); // sem teto
    expect(percentualDaFaixa(1_000_000)).toBe(4);
  });

  it('quem cai na faixa de 4% recebe 4% sobre TUDO — não 2%+3%+4% por fatia', () => {
    const b = ben({ regra: 'faixa', base: 'bruto' });
    const grande: TotaisDaVenda = { bruto: 30000, liquido: 30000, margem: 10000 };

    // por valor total: 30000 × 4%
    expect(comissaoDoBeneficiario(b, grande)).toBeCloseTo(1200, 2);

    // o modelo progressivo por fatia daria outro número — e NÃO é o que está implementado
    const progressivoPorFatia = 5000 * 0.02 + 15000 * 0.03 + 10000 * 0.04;
    expect(progressivoPorFatia).toBeCloseTo(950, 2);
    expect(comissaoDoBeneficiario(b, grande)).not.toBeCloseTo(progressivoPorFatia, 2);
  });
});

describe('vários beneficiários — o motivo de a onda existir', () => {
  it('soma tipos diferentes com regras diferentes na mesma venda', () => {
    const bens = [
      ben({ k: 1, tipo: 'funcionario', base: 'liquido', regra: 'percentual', pct: '3' }), // 270
      ben({ k: 2, tipo: 'representante', base: 'bruto', regra: 'percentual', pct: '1,5' }), // 150
      ben({ k: 3, tipo: 'agencia', regra: 'fixo', valor: '80,00' }), // 80
    ];

    expect(comissaoTotal(bens, tot)).toBeCloseTo(270 + 150 + 80, 2);
  });

  it('lista vazia é zero, não NaN', () => {
    expect(comissaoTotal([], tot)).toBe(0);
    expect(percentualSobreALiquida(0, tot)).toBe(0);
  });

  it('percentual sobre a líquida é o número que dispara o alerta', () => {
    expect(percentualSobreALiquida(270, tot)).toBeCloseTo(3, 2);
    expect(percentualSobreALiquida(100, { bruto: 0, liquido: 0, margem: 0 })).toBe(0); // sem divisão por zero
  });
});

describe('gatilho — o que impede pagar comissão de venda não paga', () => {
  const parcelas = [
    { valor: '3.000,00', lanc: 'RECEBIDA' },
    { valor: '3.000,00', lanc: 'A RECEBER' },
    { valor: '3.000,00', lanc: 'A RECEBER' },
  ];

  it('por RECEBIMENTO libera proporcional ao que o cliente pagou', () => {
    // 1 de 3 parcelas recebidas → 1/3 da comissão
    expect(comissaoLiberada(300, 'recebimento', parcelas, 9000)).toBeCloseTo(100, 2);
  });

  it('nada recebido libera zero — mesmo com a venda emitida', () => {
    const nenhuma = parcelas.map((p) => ({ ...p, lanc: 'A RECEBER' }));
    expect(comissaoLiberada(300, 'recebimento', nenhuma, 9000)).toBe(0);
  });

  it('tudo recebido libera tudo', () => {
    const todas = parcelas.map((p) => ({ ...p, lanc: 'RECEBIDA' }));
    expect(comissaoLiberada(300, 'recebimento', todas, 9000)).toBeCloseTo(300, 2);
  });

  it('faturamento e emissão liberam INTEIRO — o direito nasce no evento', () => {
    expect(comissaoLiberada(300, 'faturamento', parcelas, 9000)).toBe(300);
    expect(comissaoLiberada(300, 'emissao', parcelas, 9000)).toBe(300);
  });
});

describe('estorno na devolução — senão a devolução vira prejuízo dobrado', () => {
  it('devolveu metade, estorna metade', () => {
    expect(estornoProporcional(300, 4500, 9000)).toBeCloseTo(150, 2);
  });

  it('devolução total estorna tudo; nada devolvido estorna zero', () => {
    expect(estornoProporcional(300, 9000, 9000)).toBeCloseTo(300, 2);
    expect(estornoProporcional(300, 0, 9000)).toBe(0);
  });

  it('devolvido acima do total não estorna mais que a comissão', () => {
    expect(estornoProporcional(300, 99999, 9000)).toBeCloseTo(300, 2);
  });
});

describe('alerta de margem — o aviso de negócio que a fonte traz pronto', () => {
  it('acusa quando a comissão come mais da metade da margem', () => {
    expect(comeMaisQueMetadeDaMargem(1600, tot)).toBe(true); // 1600/3000 = 53%
    expect(comeMaisQueMetadeDaMargem(1400, tot)).toBe(false); // 46%
  });

  it('margem zero ou negativa: qualquer comissão já é alerta', () => {
    expect(comeMaisQueMetadeDaMargem(1, { bruto: 100, liquido: 100, margem: 0 })).toBe(true);
    expect(comeMaisQueMetadeDaMargem(0, { bruto: 100, liquido: 100, margem: 0 })).toBe(false);
  });
});
