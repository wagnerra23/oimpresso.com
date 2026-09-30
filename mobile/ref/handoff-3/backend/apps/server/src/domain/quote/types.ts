// ──────────────────────────────────────────────────────────────
// domain/quote · tipos — ficha técnica de impressão.
// Fase 1 · Passo 6 — o núcleo que justifica o ERP (o fosso).
// ──────────────────────────────────────────────────────────────

export type Cores = "4/4" | "4/0" | "1/1" | "1/0";

/** Nº de chapas/passagens de cor por face. */
export const CHAPAS_POR_CORES: Record<Cores, number> = {
  "4/4": 8, "4/0": 4, "1/1": 2, "1/0": 1,
};

export interface FichaTecnica {
  larguraMm: number;       // peça fechada
  alturaMm: number;
  cores: Cores;
  papel: string;
  gramatura: number;
  acabamentos: string[];   // ex.: ["laminacao", "verniz", "corte-vinco", "dobra"]
  tiragem: number;         // quantidade de peças
  sangriaMm?: number;      // default 3mm
}

/** Material de impressão: folha (offset/digital) ou bobina (wide-format). */
export interface Suporte {
  tipo: "folha" | "bobina";
  larguraMm: number;
  alturaMm?: number;       // só folha (ex.: SRA3 320×450)
  margemMm: number;        // pinça/borda não imprimível
  // folha: custo por folha; bobina: custo por m²
  custoCents: number;
}

export interface ParametrosCusto {
  digital: boolean;            // true = sem chapa
  custoChapaCents: number;     // offset; ignorado se digital
  maquinaCentsPorMin: number;
  setupMin: number;
  velocidadeFolhasPorH: number;
  refugoPct: number;           // % de folhas perdidas (acerto + refugo)
  maoDeObraCentsPorMin: number;
  acabamentoCentsPorPeca: Record<string, number>;
  // NOTA: margemPct NÃO entra aqui — é política da empresa, vem do cadastro do produto
  // e é aplicada pelo quoteEngine separadamente (ver calcularOrcamento).
}

export interface OrcamentoBreakdown {
  pecasPorFolha: number;
  folhasNecessarias: number;
  metrosLineares?: number;        // só bobina
  m2Consumidos?: number;          // só bobina
  custoMaterialCents: number;
  custoChapaCents: number;
  custoMaquinaCents: number;
  custoAcabamentoCents: number;
  custoMaoObraCents: number;
  custoTotalCents: number;
  precoCents: number;
  precoUnitCents: number;         // por peça (mostra a regressiva)
  tempoEstimadoMin: number;       // alimenta o PCP (Fase 3)
}
