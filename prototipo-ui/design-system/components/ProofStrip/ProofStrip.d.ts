export type ProofStripKind = 'density' | 'cmyk';

export interface ProofStripProps {
  /** 'density' = grayscale step wedge · 'cmyk' = process-ink swatches. */
  kind?: ProofStripKind;
  /** Number of cells for the density wedge. */
  steps?: number;
  /** Swatch height in px. */
  height?: number;
  /** Swatch width in px. */
  swatch?: number;
}

/** Press control strip — density wedge or CMYK swatches (DS print-craft). */
export declare function ProofStrip(props: ProofStripProps): JSX.Element;
