// ──────────────────────────────────────────────────────────────
// domain/production · plano de consumo — quantas folhas cada item
// da OS consome, pela MESMA imposição do orçamento. Garante que o
// que foi orçado e o que é baixado do estoque batem (sem divergência).
// Função PURA → testável e reaproveitável.
// ──────────────────────────────────────────────────────────────
import { pecasPorFolha } from "../quote/imposition";

export interface ItemFicha {
  materialId: string;       // substrato consumido (ex.: Couché 150g)
  larguraMm: number;
  alturaMm: number;
  tiragem: number;
  sangriaMm?: number;
}

export interface SuporteFolha {
  larguraMm: number;
  alturaMm: number;
  margemMm: number;
}

/** Folhas necessárias para um item (peças/folha + refugo). */
export function folhasParaItem(ficha: ItemFicha, suporte: SuporteFolha, refugoPct: number): number {
  const ppf = pecasPorFolha(
    suporte.larguraMm, suporte.alturaMm,
    ficha.larguraMm, ficha.alturaMm, ficha.sangriaMm ?? 3, suporte.margemMm,
  );
  if (ppf <= 0) return 0;
  const folhasUteis = Math.ceil(ficha.tiragem / ppf);
  return Math.round(folhasUteis * (1 + refugoPct / 100));
}

export interface ConsumoMaterial {
  materialId: string;
  folhas: number;
}

/**
 * Agrega o consumo por material para todos os itens da OS.
 * (Vários itens no mesmo material somam folhas → 1 baixa por material.)
 */
export function planoDeConsumo(
  itens: ItemFicha[], suporte: SuporteFolha, refugoPct: number,
): ConsumoMaterial[] {
  const acc = new Map<string, number>();
  for (const item of itens) {
    const folhas = folhasParaItem(item, suporte, refugoPct);
    acc.set(item.materialId, (acc.get(item.materialId) ?? 0) + folhas);
  }
  return [...acc.entries()].map(([materialId, folhas]) => ({ materialId, folhas }));
}
