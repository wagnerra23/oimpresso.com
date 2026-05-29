// Teste do parser/formatter pt-BR — blinda o cálculo de valores da venda.
//
// CONTEXTO (por que este teste existe):
//   `resources/js/Lib/numberPtBR.ts` é o coração do parsing de valores monetários
//   da tela de venda (Sells/Create via NumericInputPtBR). Já causou o Bug R$25k da
//   Larissa @ Rota Livre (biz=4, 2026-05-27): ela digitou R$ 25 e o sistema gravou
//   R$ 25.000. A correção foi este parser — mas até hoje rodava SEM teste algum.
//   Qualquer edit futuro podia reintroduzir o prejuízo silenciosamente.
//
//   Este suite trava a classe inteira de bug (parsing pt-BR ↔ en-US ↔ milhar/decimal).
//   Refs: ADR 0211 (vitest infra, Frente 1 F1-C) · dossier
//   memory/sessions/2026-05-28-arte-prevencao-bugs-mwart-larissa.md
//
// Convenção do parser: vírgula = decimal, ponto = milhar (paridade Blade pos.js).

import { describe, it, expect } from 'vitest';
import { parseDecimalPtBR, formatDecimalPtBR } from '@/Lib/numberPtBR';

describe('parseDecimalPtBR — casos canônicos documentados', () => {
  it.each([
    ['25,00', 25],
    ['1.234,56', 1234.56],
    ['25.000,00', 25000],
    ['25.000', 25000], // sem vírgula + 3 dígitos depois → milhar pt-BR
    ['147.77', 147.77], // sem vírgula + ≤2 dígitos → decimal en-US tolerado
    ['25', 25],
    ['', 0],
  ])('parse(%j) === %j', (input, expected) => {
    expect(parseDecimalPtBR(input as string)).toBe(expected);
  });

  it('parse("abc") é NaN', () => {
    expect(parseDecimalPtBR('abc')).toBeNaN();
  });
});

describe('parseDecimalPtBR — REGRESSÃO Bug R$25k Larissa (2026-05-27) [NÃO REMOVER]', () => {
  // O caso que custou dinheiro real. Estes asserts BLINDAM contra reintrodução.
  it('"25,00" → 25 (NUNCA 25000)', () => {
    expect(parseDecimalPtBR('25,00')).toBe(25);
    expect(parseDecimalPtBR('25,00')).not.toBe(25000);
  });

  it('"25" → 25 (R$ 25, não vinte e cinco mil)', () => {
    expect(parseDecimalPtBR('25')).toBe(25);
  });

  it('"R$ 25,00" com símbolo de moeda → 25', () => {
    expect(parseDecimalPtBR('R$ 25,00')).toBe(25);
  });

  // Contraste: "25.000" com ponto de milhar AÍ SIM é 25000 (intenção pt-BR explícita).
  it('"25.000" (ponto = milhar pt-BR) → 25000', () => {
    expect(parseDecimalPtBR('25.000')).toBe(25000);
  });
});

describe('parseDecimalPtBR — moeda, espaços, sinais, milhar composto', () => {
  it.each([
    ['R$ 1.234,56', 1234.56],
    ['  25,50  ', 25.5],
    ['-25,00', -25],
    ['1.234.567,89', 1234567.89],
    [',50', 0.5],
    ['100,', 100],
  ])('parse(%j) === %j', (input, expected) => {
    expect(parseDecimalPtBR(input as string)).toBe(expected);
  });
});

describe('parseDecimalPtBR — heurística do ponto (decimal en-US vs milhar pt-BR)', () => {
  it.each([
    ['1.5', 1.5], // 1 ponto, 1 dígito → decimal
    ['25.0', 25], // 2 dígitos → decimal
    ['3.50', 3.5], // 2 dígitos → decimal (R$ 3,50 digitado com ponto)
    ['1.234', 1234], // 3 dígitos → milhar
    ['3.500', 3500], // 3 dígitos → milhar (R$ 3.500 = três mil e quinhentos)
    ['1.234.567', 1234567], // múltiplos pontos → milhar
  ])('parse(%j) === %j', (input, expected) => {
    expect(parseDecimalPtBR(input as string)).toBe(expected);
  });
});

describe('parseDecimalPtBR — entradas não-string', () => {
  it('number passa direto', () => {
    expect(parseDecimalPtBR(25)).toBe(25);
    expect(parseDecimalPtBR(1234.56)).toBe(1234.56);
  });

  it('null/undefined → 0', () => {
    expect(parseDecimalPtBR(null)).toBe(0);
    expect(parseDecimalPtBR(undefined)).toBe(0);
  });
});

describe('parseDecimalPtBR — degenerados → NaN (failsafe: input volta ao último valor)', () => {
  it.each([['-'], [','], ['.'], ['abc'], ['R$']])('parse(%j) é NaN', (input) => {
    expect(parseDecimalPtBR(input as string)).toBeNaN();
  });
});

describe('formatDecimalPtBR', () => {
  it.each([
    [25, undefined, '25,00'],
    [1234.56, undefined, '1.234,56'],
    [25000, undefined, '25.000,00'],
    [25, 0, '25'],
  ])('format(%j, %j) === %j', (value, precision, expected) => {
    expect(formatDecimalPtBR(value as number, precision as number | undefined)).toBe(expected);
  });

  it('NaN/null/undefined → "0,00"', () => {
    expect(formatDecimalPtBR(NaN)).toBe('0,00');
    expect(formatDecimalPtBR(null)).toBe('0,00');
    expect(formatDecimalPtBR(undefined)).toBe('0,00');
  });
});

describe('round-trip — parse(format(x)) preserva valor (foco→blur não corrompe)', () => {
  // Garante que o ciclo display→edição→reformatação do NumericInputPtBR é idempotente:
  // um total de R$ 25.000,00 que volta pro input não pode virar outro número.
  it.each([0, 25, 25.5, 1234.56, 25000, 1234567.89, 0.5, 3.5])(
    'valor %j sobrevive ao round-trip',
    (value) => {
      expect(parseDecimalPtBR(formatDecimalPtBR(value))).toBe(value);
    },
  );
});
