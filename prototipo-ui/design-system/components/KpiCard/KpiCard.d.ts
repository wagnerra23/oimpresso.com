export type KpiTone = 'default' | 'success' | 'warning' | 'danger' | 'info';

export interface KpiCardProps {
  /** Uppercase label, e.g. "Faturamento". */
  label: string;
  /** Main value (string or number); rendered with tabular figures. */
  value: string | number;
  /** Small unit suffix after the value, e.g. "h", "/mês". */
  unit?: string;
  /** Optional supporting line below the value. */
  description?: string;
  /** Semantic tone — tints border + background (ignored when hero). */
  tone?: KpiTone;
  /** Dark navy "feature" plate — the Vendas/Financeiro hero KPI. */
  hero?: boolean;
  /** Optional delta number; positive = success arrow, negative = destructive. */
  delta?: number;
  /** Optional label after the delta, e.g. "vs ontem". */
  deltaLabel?: string;
  /** Series of numbers → inline sparkline (looks best on hero). */
  spark?: number[];
  /** 0..1 → thin progress/goal bar under the value. */
  progress?: number;
}

/** Semantic KPI tile (DS v6) — tinted card or dark hero plate with sparkline. */
export declare function KpiCard(props: KpiCardProps): JSX.Element;
