/**
 * Cálculo do imposto do item — prova por DOIS CAMINHOS (REGRA MESTRE de valor).
 *
 * @covers-us UC-V371 UC-V372
 *
 * A REGRA MESTRE de `memory/proibicoes.md` nomeia **imposto** explicitamente, e exige
 * provar o resultado por dois caminhos INDEPENDENTES com números concretos. Aqui o
 * segundo caminho é aritmética à mão em **centavos inteiros** — nunca a mesma
 * expressão em float, que provaria só que a função é igual a si mesma.
 *
 * Separado de `item-fiscal-dominio.test.ts` de propósito: aquele arquivo prova
 * VALIDAÇÃO (formato + coerência), e o docblock dele diz literalmente "isto NÃO é
 * cálculo de valor". Misturar as duas famílias apagaria essa fronteira.
 */
import { describe, expect, it } from 'vitest';

import {
  IMPOSTOS,
  aliquotaDe,
  cstNaoTributa,
  difalDoItem,
  impostoDe,
  somaDosImpostos,
} from '@/Pages/Sells/_components/v3/item-fiscal-dominio';

/** Caminho B: centavos inteiros, sem tocar na função sob teste. */
const aMao = (baseCentavos: number, aliqMilesimos: number): number =>
  Math.round((baseCentavos * aliqMilesimos) / 100_000) / 100;

const CST_TRIBUTA = '00 — Tributada integralmente';

describe('UC-V371 · valor do imposto — função real × aritmética à mão', () => {
  it('bate em centavos para uma matriz de bases × alíquotas', () => {
    const bases = [0, 1, 84, 861.25, 945.25, 1115.4, 12_345.67, 99_999.99];
    const aliquotas = ['0', '1,65', '7,6', '12', '18', '25', '100'];

    let comparados = 0;
    for (const base of bases) {
      for (const a of aliquotas) {
        const real = impostoDe({ k: 'icms', aliq: 0 }, base, a, CST_TRIBUTA);
        const mao = aMao(Math.round(base * 100), Math.round(Number(a.replace(',', '.')) * 1000));
        expect(real).toBeCloseTo(mao, 2);
        comparados++;
      }
    }

    // controle positivo: se o laço não rodar, o `expect` acima nunca é exercido e o
    // teste passa por não-execução (LC-13) — o contador é o que impede isso.
    expect(comparados).toBe(bases.length * aliquotas.length);
  });

  it('o caso concreto da cena: 861,25 a 18% dá 155,03', () => {
    expect(impostoDe({ k: 'icms', aliq: 0 }, 861.25, '18', CST_TRIBUTA)).toBeCloseTo(155.03, 2);
  });

  it('CST que NÃO tributa zera o valor, mesmo com alíquota preenchida', () => {
    // é a mesma regra que o `erroDeCoerencia` acusa no campo — um dono só, para a
    // tela nunca acusar incoerência num lugar e mostrar imposto no outro.
    for (const cst of ['40 — Isenta', '41 — Não tributada', '60 — ST cobrado anteriormente']) {
      expect(cstNaoTributa(cst)).toBe(true);
      expect(impostoDe({ k: 'icms', aliq: 0 }, 1000, '18', cst)).toBe(0);
    }
    // '102' tem 3 dígitos: NÃO é o CST '10', e tributa normalmente
    expect(cstNaoTributa('102 — Simples sem crédito')).toBe(false);
    expect(impostoDe({ k: 'icms', aliq: 0 }, 1000, '18', '102 — Simples sem crédito')).toBeCloseTo(180, 2);
  });

  it('só o ICMS lê a alíquota da tela; o resto vem do cadastro', () => {
    expect(aliquotaDe({ k: 'icms', aliq: 18 }, '25')).toBe(25);
    expect(aliquotaDe({ k: 'pis', aliq: 1.65 }, '25')).toBe(1.65);
  });

  it('o total é a soma dos 9, e a soma confere item a item', () => {
    const base = 861.25;
    const total = somaDosImpostos(base, '18', CST_TRIBUTA);
    const mao = IMPOSTOS.reduce(
      (s, i) => s + aMao(Math.round(base * 100), Math.round((i.k === 'icms' ? 18 : i.aliq) * 1000)),
      0,
    );
    expect(total).toBeCloseTo(mao, 2);
    expect(total).toBeGreaterThan(0);
  });
});

describe('UC-V372 · DIFAL — a diferença vai 100% para o destino (EC 87/2015)', () => {
  it('remetente, destino e FCP conferem à mão', () => {
    const base = 1000;
    const d = difalDoItem(base, '12', '18', '2');

    expect(d.remetente).toBeCloseTo(120, 2); // 1000 × 12%
    expect(d.destino).toBeCloseTo(60, 2); //  1000 × 18% − 120
    expect(d.fcp).toBeCloseTo(20, 2); //      1000 × 2%
    expect(d.total).toBeCloseTo(80, 2); //    destino + fcp
    expect(d.invertido).toBe(false);
  });

  it('a partilha NÃO é meio a meio — o destino leva a diferença inteira', () => {
    // antes de 2019 havia partilha progressiva; desde então é 100% destino. Se
    // alguém "corrigir" isto para metade, este número cai.
    const d = difalDoItem(1000, '12', '18', '0');
    expect(d.destino).toBeCloseTo(60, 2);
    expect(d.destino).not.toBeCloseTo(30, 2);
  });

  it('interna do destino MENOR que a interestadual: nada a recolher, nunca negativo', () => {
    const d = difalDoItem(1000, '18', '12', '2');
    expect(d.invertido).toBe(true);
    expect(d.destino).toBe(0);
    expect(d.total).toBeCloseTo(20, 2); // sobra só o FCP
  });

  it('base zero devolve zero em tudo', () => {
    const d = difalDoItem(0, '12', '18', '2');
    expect(d.remetente).toBe(0);
    expect(d.destino).toBe(0);
    expect(d.fcp).toBe(0);
    expect(d.total).toBe(0);
  });
});
