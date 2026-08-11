/**
 * Prova do rateio de parcelas — onda 3 do preview `/sells/create-v3` (CU-SELL-09).
 *
 * ⚠️ POR QUE ESTE ARQUIVO EXISTE
 * `ratear()` divide DINHEIRO. A REGRA MESTRE de valor/estoque (`memory/proibicoes.md`)
 * exige provar por **dois caminhos independentes** antes de aplicar. Aqui:
 *
 *   caminho A — a função real (`ratear`);
 *   caminho B — aritmética à mão, escrita de forma DIFERENTE (soma de inteiros a partir
 *               da definição, sem reusar a implementação).
 *
 * Um teste que só chamasse `ratear` e comparasse com o que `ratear` devolve seria
 * tautológico — o §5 de `proibicoes.md` tem lápide de 2026-06-05 exatamente sobre isso.
 * Por isso o caminho B parte da DEFINIÇÃO do invariante, não do código.
 *
 * O invariante que importa, e é o que o ERP não pode violar:
 *     soma(ratear(total, n)) === total, para todo n
 * O erro clássico — dividir em float e arredondar cada parcela — perde R$ 0,01 em
 * `100 / 3` e vira diferença de conciliação que ninguém acha depois.
 */

import { describe, expect, it } from 'vitest';

import {
  MAX_PARCELAS,
  diferencaParaOTotal,
  fechaNoTotal,
  mesmoDiaNoMes,
  quantidadeSaneada,
  ratear,
  somaDasParcelas,
  venceuAntesDeHoje,
} from '@/Pages/Sells/_components/v3/parcelas-dominio';

/** Soma em CENTAVOS inteiros — evita reintroduzir erro de float na própria verificação. */
const somaEmCentavos = (vs: number[]): number => vs.reduce((s, v) => s + Math.round(v * 100), 0);

describe('ratear — o invariante do centavo (Tier 0 · valor)', () => {
  it('caminho A × caminho B: a soma bate com o total, para 1..48 parcelas', () => {
    const totais = [100, 1115.4, 0.01, 0.03, 999.99, 1, 2, 3, 10.1, 4821.37, 0];

    for (const total of totais) {
      for (let n = 1; n <= MAX_PARCELAS; n++) {
        // caminho A — a função real
        const parcelas = ratear(total, n);

        // caminho B — a definição, à mão: total em centavos, dividido e somado como inteiro
        const centavosEsperados = Math.round(total * 100);

        expect(parcelas).toHaveLength(n);
        expect(somaEmCentavos(parcelas)).toBe(centavosEsperados);
      }
    }
  });

  it('o caso que dá nome ao problema: 100 em 3 não perde o centavo', () => {
    const p = ratear(100, 3);

    // o jeito ERRADO daria [33.33, 33.33, 33.33] = 99.99
    expect(p).toEqual([33.34, 33.33, 33.33]);
    expect(somaEmCentavos(p)).toBe(10000);

    // e a prova de que o erro clássico de fato perderia 1 centavo
    const ingenuo = Array.from({ length: 3 }, () => Math.round((100 / 3) * 100) / 100);
    expect(somaEmCentavos(ingenuo)).toBe(9999);
  });

  it('o resto vai para as PRIMEIRAS parcelas, uma unidade em cada', () => {
    // 10,00 em 3 → 334 + 333 + 333 centavos
    expect(ratear(10, 3)).toEqual([3.34, 3.33, 3.33]);
    // 0,05 em 3 → 2 + 2 + 1 centavos (resto 2 → duas primeiras ganham 1)
    expect(ratear(0.05, 3)).toEqual([0.02, 0.02, 0.01]);
  });

  it('nunca devolve lista vazia nem quantidade fracionada', () => {
    expect(ratear(50, 0)).toHaveLength(1);
    expect(ratear(50, -3)).toHaveLength(1);
    expect(ratear(50, 2.7)).toHaveLength(2); // floor, não round
  });

  it('total zero devolve parcelas zeradas — não NaN', () => {
    const p = ratear(0, 4);
    expect(p).toEqual([0, 0, 0, 0]);
    expect(p.every(Number.isFinite)).toBe(true);
  });
});

describe('quantidadeSaneada — entrada de texto não vira NaN nem laço infinito', () => {
  it('texto lixo e vazio caem em 1', () => {
    expect(quantidadeSaneada('')).toBe(1);
    expect(quantidadeSaneada('abc')).toBe(1);
    expect(quantidadeSaneada('0')).toBe(1);
    expect(quantidadeSaneada('-5')).toBe(1);
  });

  it('respeita o teto de 48', () => {
    expect(quantidadeSaneada('999')).toBe(MAX_PARCELAS);
    expect(quantidadeSaneada('48')).toBe(48);
    expect(quantidadeSaneada('12')).toBe(12);
  });
});

describe('soma e diferença — o que a tela usa para liberar o Confirmar', () => {
  it('fecha no total quando as parcelas somam o total', () => {
    const vs = ratear(1115.4, 3).map((v) => v.toFixed(2).replace('.', ','));
    const parcelas = vs.map((valor) => ({ valor }));

    expect(somaDasParcelas(parcelas)).toBeCloseTo(1115.4, 2);
    expect(fechaNoTotal(1115.4, parcelas)).toBe(true);
    expect(Math.abs(diferencaParaOTotal(1115.4, parcelas))).toBeLessThan(0.005);
  });

  it('não fecha quando falta ou sobra centavo — e diz de que lado', () => {
    expect(fechaNoTotal(100, [{ valor: '99,99' }])).toBe(false);
    expect(diferencaParaOTotal(100, [{ valor: '99,99' }])).toBeCloseTo(0.01, 3); // falta
    expect(diferencaParaOTotal(100, [{ valor: '100,01' }])).toBeCloseTo(-0.01, 3); // sobrou
  });

  it('parse pt-BR: separador de milhar não infla o valor (guard do incidente num_uf)', () => {
    // "1.115,40" é mil cento e quinze — não um milhão e cento e quinze mil
    expect(somaDasParcelas([{ valor: '1.115,40' }])).toBeCloseTo(1115.4, 2);
  });
});

describe('mesmoDiaNoMes — "vence no mesmo dia de cada mês" ≠ somar 30 dias', () => {
  it('dia 31 é grampeado no último dia do mês curto, sem virar o mês seguinte', () => {
    const base = new Date(2026, 0, 31); // 31/01/2026

    const fev = mesmoDiaNoMes(base, 1);
    expect(fev.getMonth()).toBe(1); // fevereiro, NÃO março
    expect(fev.getDate()).toBe(28); // 2026 não é bissexto

    const abr = mesmoDiaNoMes(base, 3);
    expect(abr.getMonth()).toBe(3); // abril
    expect(abr.getDate()).toBe(30); // abril tem 30
  });

  it('dia que existe em todo mês anda sem surpresa', () => {
    const base = new Date(2026, 0, 15);
    const mar = mesmoDiaNoMes(base, 2);
    expect([mar.getMonth(), mar.getDate()]).toEqual([2, 15]);
  });

  it('contraste real com somar 30 dias: em 31/01 os dois métodos caem em MESES diferentes', () => {
    // Escolha do caso importa: para 31/05 os dois métodos coincidem em 30/06 (maio tem
    // 31 dias), então aquele caso NÃO distingue nada. Fevereiro é onde a diferença
    // aparece — e é justamente o mês que quebra vencimento mensal em ERP.
    const base = new Date(2026, 0, 31); // 31/01/2026

    const porMes = mesmoDiaNoMes(base, 1);
    expect([porMes.getMonth(), porMes.getDate()]).toEqual([1, 28]); // 28/02 — fica em fevereiro

    const por30dias = new Date(base.getTime());
    por30dias.setDate(por30dias.getDate() + 30);
    expect([por30dias.getMonth(), por30dias.getDate()]).toEqual([2, 2]); // 02/03 — vazou pra março

    // é a diferença que muda competência e mês fechado no financeiro
    expect(porMes.getMonth()).not.toBe(por30dias.getMonth());
  });
});

describe('venceuAntesDeHoje — comparação por dia, não por instante', () => {
  it('vencimento de hoje NÃO está vencido, mesmo com hora diferente', () => {
    const hojeTarde = new Date();
    hojeTarde.setHours(23, 59, 0, 0);
    expect(venceuAntesDeHoje(hojeTarde)).toBe(false);
  });

  it('ontem está vencido; amanhã não', () => {
    const ontem = new Date();
    ontem.setDate(ontem.getDate() - 1);
    const amanha = new Date();
    amanha.setDate(amanha.getDate() + 1);

    expect(venceuAntesDeHoje(ontem)).toBe(true);
    expect(venceuAntesDeHoje(amanha)).toBe(false);
  });
});
