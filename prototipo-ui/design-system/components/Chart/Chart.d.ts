export type ChartType = 'area' | 'line' | 'bar';
export type ChartDatum = number | { label?: string; value: number };

export interface ChartProps {
  /** 'area' (default) · 'line' · 'bar'. */
  type?: ChartType;
  /** Series — raw numbers or `{label, value}` for hover labels. */
  data: ChartDatum[];
  /** Pixel height (width is fluid). Default 140. */
  height?: number;
  /** Stroke/fill color (CSS). Default the accent. */
  color?: string;
  strokeWidth?: number;
  /** Bar charts: keep the last bar solid when not hovering. */
  highlightLast?: boolean;
  /** Format the tooltip value. */
  formatValue?: (v: number) => string;
}

/** Compact responsive chart — line/area/bar + hover tooltip (DS premium). */
export declare function Chart(props: ChartProps): JSX.Element;
