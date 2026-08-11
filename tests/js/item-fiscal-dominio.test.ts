/**
 * Prova das regras fiscais do item — onda 4 do preview `/sells/create-v3`.
 *
 * Estas regras decidem se a NF-e é ACEITA ou REJEITADA pela SEFAZ. Rejeição não é
 * detalhe de UI: é a venda parada e retrabalho de quem emite.
 *
 * O teste cobre as duas famílias, e a segunda é a que importa mais:
 *   - FORMATO — o campo tem a cara que a lei exige;
 *   - COERÊNCIA — dois campos individualmente válidos que juntos se contradizem.
 *     CST 40 (isenta) com alíquota 18% passa em QUALQUER validação de formato e é
 *     rejeitada na hora. É exatamente o erro que validação campo-a-campo não pega.
 */

import { describe, expect, it } from 'vitest';

import {
  CST_SEM_ALIQUOTA,
  erroDeCoerencia,
  errosFiscais,
  fiscalValido,
  validarAliquota,
  validarCbenef,
  validarCest,
  validarCfop,
  validarGtin,
  validarNcm,
  validarReducao,
} from '@/Pages/Sells/_components/v3/item-fiscal-dominio';

describe('NCM — 8 dígitos, obrigatório', () => {
  it('UC-V340 · aceita 8 dígitos, com ou sem máscara', () => {
    expect(validarNcm('39199090')).toBeNull();
    expect(validarNcm('3919.90.90')).toBeNull();
  });

  it('UC-V340 · exige preenchimento e DIZ quantos dígitos faltam', () => {
    expect(validarNcm('')).toBe('NCM é obrigatório na NF-e');
    expect(validarNcm('3919')).toBe('NCM tem 8 dígitos — faltam 4');
    expect(validarNcm('391990901')).toBe('NCM tem 8 dígitos — faltam 1');
  });
});

describe('CFOP — 4 dígitos e o primeiro tem significado', () => {
  it('UC-V341 · aceita entradas (1/2/3) e saídas (5/6/7)', () => {
    for (const cfop of ['1102', '2102', '3102', '5102', '6102', '7102']) {
      expect(validarCfop(cfop)).toBeNull();
    }
  });

  it('UC-V341 · recusa primeiro dígito que não existe na tabela', () => {
    // 4, 8 e 9 não são naturezas de operação válidas
    for (const cfop of ['4102', '8102', '9102']) {
      expect(validarCfop(cfop)).toBe('CFOP começa em 1, 2, 3, 5, 6 ou 7');
    }
  });

  it('UC-V341 · exige preenchimento e comprimento exato', () => {
    expect(validarCfop('')).toBe('CFOP é obrigatório');
    expect(validarCfop('510')).toBe('CFOP tem 4 dígitos');
    expect(validarCfop('51020')).toBe('CFOP tem 4 dígitos');
  });
});

describe('campos opcionais — vazio passa, preenchido é conferido', () => {
  it('UC-V342 · CEST: 7 dígitos quando informado', () => {
    expect(validarCest('')).toBeNull();
    expect(validarCest('2806400')).toBeNull();
    expect(validarCest('28064')).toBe('CEST tem 7 dígitos');
  });

  it('UC-V342 · GTIN: 8, 12, 13 ou 14 — os quatro padrões reais de código de barras', () => {
    expect(validarGtin('')).toBeNull();
    for (const g of ['12345678', '123456789012', '7891234000017', '12345678901234']) {
      expect(validarGtin(g)).toBeNull();
    }
    expect(validarGtin('1234567890')).toBe('GTIN tem 8, 12, 13 ou 14 dígitos'); // 10
  });

  it('UC-V342 · cBenef: 2 letras da UF + 6 dígitos', () => {
    expect(validarCbenef('')).toBeNull();
    expect(validarCbenef('SC123456')).toBeNull();
    expect(validarCbenef('sc123456')).toBeNull(); // case-insensitive
    expect(validarCbenef('SC12345')).toBe('cBenef: 2 letras da UF + 6 dígitos');
    expect(validarCbenef('123456SC')).toBe('cBenef: 2 letras da UF + 6 dígitos');
  });
});

describe('alíquota e redução — faixa 0..100', () => {
  it('UC-V343 · aceita a faixa e o pt-BR', () => {
    expect(validarAliquota('18')).toBeNull();
    expect(validarAliquota('1,65')).toBeNull();
    expect(validarAliquota('0')).toBeNull();
    expect(validarAliquota('100')).toBeNull();
  });

  it('UC-V343 · recusa negativo e acima de 100', () => {
    expect(validarAliquota('-1')).toBe('Alíquota não pode ser negativa');
    expect(validarAliquota('101')).toBe('Alíquota acima de 100%');
    expect(validarReducao('-0,5')).toBe('Redução vai de 0 a 100%');
    expect(validarReducao('120')).toBe('Redução vai de 0 a 100%');
  });
});

describe('COERÊNCIA CST × alíquota — o erro que o formato NÃO pega', () => {
  it('UC-V344 · CST que declara não haver imposto não admite alíquota', () => {
    for (const cst of CST_SEM_ALIQUOTA) {
      const erro = erroDeCoerencia(`${cst} — algo`, '18');
      expect(erro).toBe(`CST ${cst} não admite alíquota — zere ou troque o CST`);
    }
  });

  it('UC-V344 · os mesmos CSTs passam quando a alíquota é zero', () => {
    for (const cst of CST_SEM_ALIQUOTA) {
      expect(erroDeCoerencia(`${cst} — algo`, '0')).toBeNull();
    }
  });

  it('UC-V344 · CST 00 é tributado integralmente — alíquota zero é contradição no sentido oposto', () => {
    expect(erroDeCoerencia('00 — Tributada integralmente', '0')).toBe(
      'CST 00 é tributado integralmente — alíquota não pode ser zero',
    );
    expect(erroDeCoerencia('00 — Tributada integralmente', '18')).toBeNull();
  });

  it('UC-V345 · CST 102 (Simples) tem TRÊS dígitos e não pode ser lido como "10"', () => {
    // o risco é o prefixo de 2 dígitos casar por engano; 102 não entra em nenhuma regra
    expect(erroDeCoerencia('102 — Simples sem crédito', '0')).toBeNull();
    expect(erroDeCoerencia('102 — Simples sem crédito', '18')).toBeNull();
  });

  it('UC-V344 · a validação de FORMATO sozinha aprovaria o par incoerente — é por isso que a coerência existe', () => {
    // CST 40 + alíquota 18: os dois campos passam individualmente…
    expect(validarAliquota('18')).toBeNull();
    // …e o par é rejeitado
    expect(erroDeCoerencia('40 — Isenta', '18')).not.toBeNull();
  });
});

describe('errosFiscais — a aba inteira, em lista', () => {
  const valido = {
    ncm: '39199090',
    cfop: '5102',
    cest: '',
    gtin: '',
    cbenef: '',
    cst: '00 — Tributada integralmente',
    aliquota: '18',
    reducao: '0',
  };

  it('UC-V346 · item corretamente preenchido não acusa nada', () => {
    expect(errosFiscais(valido)).toEqual([]);
    expect(fiscalValido(valido)).toBe(true);
  });

  it('UC-V346 · devolve TODOS os erros de uma vez, não só o primeiro', () => {
    const erros = errosFiscais({ ...valido, ncm: '', cfop: '9999', aliquota: '150' });
    expect(erros.length).toBeGreaterThanOrEqual(3);
    expect(erros).toContain('NCM é obrigatório na NF-e');
    expect(erros).toContain('CFOP começa em 1, 2, 3, 5, 6 ou 7');
    expect(erros).toContain('Alíquota acima de 100%');
  });

  it('UC-V346 · pega a incoerência mesmo com todo o resto correto', () => {
    const erros = errosFiscais({ ...valido, cst: '40 — Isenta', aliquota: '18' });
    expect(erros).toEqual(['CST 40 não admite alíquota — zere ou troque o CST']);
    expect(fiscalValido({ ...valido, cst: '40 — Isenta', aliquota: '18' })).toBe(false);
  });
});
