// US-VEST-031: a sugestão da curva de venda nas células da grade da compra.
// Fora do componente por causa de react-refresh/only-export-components.

import type { GradeCell, GradeCol, GradeRow } from './GradeMatrixInput';

export interface LinhaDaCurva {
  variation_id: number;
  sugerida: number;
}
export interface RespostaDaCurva {
  base: 'produto' | 'categoria' | null;
  motivo: string | null;
  dias: number;
  linhas: LinhaDaCurva[];
}
export interface SugestaoNaGrade {
  initialQty: Record<string, number>;
  cells: GradeCell[];
  /** Peças sugeridas para variação sem célula na grade aberta (a soma não bateria). */
  pecasFora: number;
}

export function textoDoMotivo(motivo: string | null, dias: number): string {
  if (motivo === 'sem_venda_no_produto') return `Este produto não vendeu nos últimos ${dias} dias e não tem categoria para comparar.`;
  if (motivo === 'sem_venda_no_produto_nem_na_categoria') return `Este produto e a categoria dele não venderam nos últimos ${dias} dias.`;
  return 'Não há sugestão para este produto.';
}

export function sugestaoParaGrade(linhas: LinhaDaCurva[], rows: GradeRow[], cols: GradeCol[], mapa: Record<string, number>): SugestaoNaGrade {
  const sugerida = new Map(linhas.map((l) => [l.variation_id, l.sugerida]));
  const initialQty: Record<string, number> = {};
  const cells: GradeCell[] = [];
  const naGrade = new Set<number>();
  rows.forEach((r) => cols.forEach((c) => {
    const key = `${r.id}__${c.id}`;
    const variationId = mapa[key];
    if (variationId == null) return;
    naGrade.add(variationId);
    const qty = sugerida.get(variationId) ?? 0;
    if (qty > 0) {
      initialQty[key] = qty;
      cells.push({ rowId: r.id, colId: c.id, variationId, qty });
    }
  }));
  const pecasFora = linhas.filter((l) => !naGrade.has(l.variation_id)).reduce((s, l) => s + l.sugerida, 0);
  return { initialQty, cells, pecasFora };
}
