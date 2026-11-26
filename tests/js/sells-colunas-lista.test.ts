/**
 * Lista de vendas — botão "Colunas" (decisão [W] 2026-11-25, item 08): a pessoa escolhe e ordena as
 * colunas que a tabela já desenha (item 10: sem colunas novas). Quem nunca mexe vê o conjunto da
 * visão, como antes. Caixinha e Venda são fixas no começo; Comissão só com comissão ligada.
 */
import { describe, expect, it } from 'vitest';
import {
  alternar,
  colunasGravadas,
  colunasPadrao,
  foraDaLista,
  mover,
} from '@/Pages/Sells/_components/colunasLista';
import { COLUMNS_OPERACIONAL } from '@/Pages/Sells/_components/SellsTabelaUnificada';

describe('Lista de vendas · colunas escolhidas', () => {
  it('sem escolha gravada, a tabela fica igual à de antes', () => {
    expect(colunasGravadas('', 'operacional', true)).toEqual(COLUMNS_OPERACIONAL);
    expect(colunasGravadas('isto não é json', 'operacional', true)).toEqual(COLUMNS_OPERACIONAL);
  });

  it('a escolha gravada vale, com as fixas no começo', () => {
    expect(colunasGravadas('["total","client"]', 'operacional', true))
      .toEqual(['check', 'invoice', 'total', 'client']);
  });

  it('id desconhecido ou repetido é descartado; lista vazia volta ao padrão', () => {
    expect(colunasGravadas('["total","sumiu","total"]', 'financeira', true))
      .toEqual(['check', 'invoice', 'total']);
    expect(colunasGravadas('["sumiu"]', 'financeira', true)).toEqual(colunasPadrao('financeira', true));
  });

  it('sem comissão na empresa, a coluna Comissão não aparece nem pode ser ligada', () => {
    expect(colunasGravadas('["commission","total"]', 'financeira', false))
      .toEqual(['check', 'invoice', 'total']);
    expect(colunasPadrao('financeira', false)).not.toContain('commission');
    expect(foraDaLista(['check', 'invoice'], false)).not.toContain('commission');
  });

  it('fixa não desliga nem sai do lugar', () => {
    const a = ['check', 'invoice', 'date', 'total'] as const;
    expect(alternar([...a], 'invoice')).toEqual(a);
    expect(mover([...a], 'invoice', 1)).toEqual(a);
    expect(mover([...a], 'date', -1)).toEqual(a);
  });

  it('liga no fim, desliga e reordena', () => {
    const a: Parameters<typeof alternar>[0] = ['check', 'invoice', 'date', 'total'];
    expect(alternar(a, 'status')).toEqual(['check', 'invoice', 'date', 'total', 'status']);
    expect(alternar(a, 'date')).toEqual(['check', 'invoice', 'total']);
    expect(mover(a, 'total', -1)).toEqual(['check', 'invoice', 'total', 'date']);
    expect(mover(a, 'total', 1)).toEqual(a);
  });
});
