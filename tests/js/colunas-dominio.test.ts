/**
 * Prova das colunas do grid — onda 6 do preview `/sells/create-v3`.
 *
 * O foco não é a lista de colunas: é a DEFESA contra dado podre. A preferência vem do
 * `localStorage`, que é entrada não confiável — o usuário edita, uma versão futura
 * remove uma coluna, um JSON trunca. Uma tela de venda que quebra porque o storage
 * tem lixo é pior que uma tela sem preferência salva.
 */

import { describe, expect, it } from 'vitest';

import {
  CHAVES_FIXAS,
  COLUNAS,
  alternarColuna,
  carregarColunas,
  colunasPadrao,
  moverColuna,
  salvarColunas,
  sanearColunas,
} from '@/Pages/Sells/_components/v3/colunas-dominio';

/** Storage de mentira — deixa simular bloqueado, com lixo e com cota cheia. */
const storageFalso = (inicial?: string | null, quebrado = false) => ({
  getItem: () => {
    if (quebrado) throw new Error('storage bloqueado');
    return inicial ?? null;
  },
  setItem: () => {
    if (quebrado) throw new Error('cota cheia');
  },
});

describe('catálogo de colunas', () => {
  it('UC-V365 · as chaves são únicas — duas colunas com a mesma chave se sobrescreveriam', () => {
    const chaves = COLUNAS.map((c) => c.k);
    expect(new Set(chaves).size).toBe(chaves.length);
  });

  it('UC-V365 · o padrão inclui todas as fixas', () => {
    for (const fixa of CHAVES_FIXAS) {
      expect(colunasPadrao()).toContain(fixa);
    }
  });

  it('UC-V365 · toda coluna pertence a um grupo conhecido', () => {
    const grupos = new Set(['ident', 'medida', 'valor', 'fiscal', 'producao', 'estoque']);
    for (const c of COLUNAS) expect(grupos.has(c.grupo)).toBe(true);
  });
});

describe('sanear — as quatro formas de o dado chegar podre', () => {
  it('UC-V363 · não-array cai no padrão', () => {
    for (const lixo of [null, undefined, 42, 'produto', {}, true]) {
      expect(sanearColunas(lixo)).toEqual(colunasPadrao());
    }
  });

  it('UC-V362 · chave que não existe mais é descartada (coluna removida numa versão futura)', () => {
    const r = sanearColunas(['produto', 'qtd', 'preco', 'total', 'coluna_que_sumiu']);
    expect(r).not.toContain('coluna_que_sumiu');
    expect(r).toContain('produto');
  });

  it('UC-V363 · chave repetida mantém só a primeira', () => {
    const r = sanearColunas(['produto', 'qtd', 'preco', 'total', 'sku', 'sku', 'sku']);
    expect(r.filter((k) => k === 'sku')).toHaveLength(1);
  });

  it('UC-V364 · coluna FIXA ausente é reinserida — a linha não existe sem ela', () => {
    const r = sanearColunas(['sku']); // nenhuma fixa
    for (const fixa of CHAVES_FIXAS) expect(r).toContain(fixa);
    expect(r).toContain('sku');
  });

  it('UC-V363 · lista só com lixo cai no padrão, não em lista vazia', () => {
    expect(sanearColunas(['nada', 'existe'])).toEqual(colunasPadrao());
  });
});

describe('carregar/salvar — storage é entrada não confiável', () => {
  it('UC-V361 · storage vazio devolve o padrão', () => {
    expect(carregarColunas(storageFalso(null))).toEqual(colunasPadrao());
  });

  it('UC-V361 · JSON inválido NÃO lança — devolve o padrão', () => {
    expect(carregarColunas(storageFalso('{isso não é json'))).toEqual(colunasPadrao());
  });

  it('UC-V361 · storage bloqueado (modo anônimo) NÃO lança', () => {
    expect(carregarColunas(storageFalso(null, true))).toEqual(colunasPadrao());
  });

  it('UC-V366 · preferência válida é respeitada', () => {
    const salvo = JSON.stringify(['produto', 'qtd', 'preco', 'total', 'ncm', 'cfop']);
    const r = carregarColunas(storageFalso(salvo));
    expect(r).toContain('ncm');
    expect(r).toContain('cfop');
  });

  it('UC-V361 · salvar com cota cheia NÃO lança — preferência não derruba a venda', () => {
    expect(() => salvarColunas(['produto'], storageFalso(null, true))).not.toThrow();
  });
});

describe('mover — fixa não sai do lugar', () => {
  const base = ['produto', 'qtd', 'preco', 'total', 'sku', 'ncm'];

  it('UC-V366 · move coluna comum', () => {
    const r = moverColuna(base, 5, 4); // ncm antes de sku
    expect(r.indexOf('ncm')).toBeLessThan(r.indexOf('sku'));
    expect(r).toHaveLength(base.length);
  });

  it('UC-V360 · não move coluna FIXA', () => {
    expect(moverColuna(base, 0, 3)).toEqual(base);
  });

  it('UC-V360 · não solta coluna comum em cima de posição de fixa', () => {
    expect(moverColuna(base, 4, 0)).toEqual(base);
  });

  it('UC-V366 · índice fora da lista é ignorado, não quebra', () => {
    expect(moverColuna(base, 99, 0)).toEqual(base);
    expect(moverColuna(base, 0, -1)).toEqual(base);
    expect(moverColuna(base, 2, 2)).toEqual(base);
  });
});

describe('alternar — fixa não desliga', () => {
  const base = ['produto', 'qtd', 'preco', 'total', 'sku'];

  it('UC-V366 · liga coluna que estava fora', () => {
    expect(alternarColuna(base, 'ncm')).toContain('ncm');
  });

  it('UC-V366 · desliga coluna comum', () => {
    expect(alternarColuna(base, 'sku')).not.toContain('sku');
  });

  it('UC-V360 · NÃO desliga fixa — é o que impede o grid ficar sem total', () => {
    for (const fixa of CHAVES_FIXAS) {
      expect(alternarColuna(base, fixa)).toContain(fixa);
    }
  });

  it('UC-V366 · chave desconhecida não entra', () => {
    expect(alternarColuna(base, 'inventada')).toEqual(base);
  });
});
