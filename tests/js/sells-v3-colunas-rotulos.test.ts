/**
 * Venda V3 — as colunas que existem nos dois lados têm o nome e o grupo da âncora.
 *
 * @covers-us UC-V415
 *
 * Âncora: `COLUNAS` de `prototipo-ui/cowork/Wagner/venda-v3.jsx`. O teste LÊ o protótipo para cada
 * coluna que é a mesma nos dois catálogos (pares de chave abaixo), para não virar um terceiro dono
 * dos rótulos. A chave da produção não muda: a escolha que o operador salvou continua valendo.
 */
import { readFileSync } from 'node:fs';
import { resolve } from 'node:path';
import { describe, expect, it } from 'vitest';
import { COLUNAS, carregarColunas, definicaoDe } from '@/Pages/Sells/_components/v3/colunas-dominio';

const raiz = resolve(__dirname, '../..');

/** { chave: { l, g } } do array COLUNAS do protótipo. */
function catalogoDoPrototipo(): Record<string, { l: string; g: string }> {
  const src = readFileSync(resolve(raiz, 'prototipo-ui/cowork/Wagner/venda-v3.jsx'), 'utf8');
  const ini = src.indexOf('const COLUNAS = [');
  const fim = src.indexOf('];', src.indexOf("k: 'localEstoque'", ini));
  const bloco = src.slice(ini, fim);
  return Object.fromEntries([...bloco.matchAll(/\{ k: '(\w+)', l: '([^']+)', g: '(\w+)'/g)].map((m) => [m[1], { l: m[2], g: m[3] }]));
}

/* produção → protótipo, só onde a coluna mostra o mesmo dado. */
const MESMA_COLUNA: Record<string, string> = {
  preco: 'preco',
  total: 'total',
  sku: 'codigo',
  un: 'unidade',
  impressao: 'impressao',
  executante: 'func',
  prazo: 'prazoEtapa',
  estoque: 'estoque',
};

describe('UC-V415 · colunas com o nome e o grupo da âncora', () => {
  it('controle: a leitura do protótipo achou o catálogo de 31 colunas', () => {
    expect(Object.keys(catalogoDoPrototipo())).toHaveLength(31);
  });

  it('cada coluna que é a mesma nos dois lados tem o rótulo e o grupo do protótipo', () => {
    const proto = catalogoDoPrototipo();
    for (const [nossa, deles] of Object.entries(MESMA_COLUNA)) {
      const d = definicaoDe(nossa);
      expect(d, nossa).toBeTruthy();
      expect(d!.label, nossa).toBe(proto[deles].l);
      expect(d!.grupo, nossa).toBe(proto[deles].g);
    }
  });

  it('"Comprimento (m)" é a altura do protótipo, com a unidade que a produção já mostrava', () => {
    expect(catalogoDoPrototipo().altura.l).toBe('Comprimento');
    expect(definicaoDe('altura')!.label).toBe('Comprimento (m)');
  });

  it('a escolha salva com as chaves de antes continua carregando igual', () => {
    const salvas = ['produto', 'qtd', 'preco', 'total', 'sku', 'estoque', 'executante'];
    const storage = { getItem: () => JSON.stringify(salvas) };
    expect(carregarColunas(storage)).toEqual(salvas);
    expect(COLUNAS.map((c) => c.k)).toEqual(expect.arrayContaining(salvas));
  });
});
