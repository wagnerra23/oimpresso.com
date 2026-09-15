import * as React from 'react';

export interface ProofFrameProps {
  children?: React.ReactNode;
  /** Draw crop marks at the four corners (default true). */
  cropMarks?: boolean;
  /** Faint proof grid background (default true). */
  grid?: boolean;
  /** Inner padding in px (default 26). */
  padding?: number;
  /** Corner radius (CSS value). */
  radius?: string;
}

/** A card framed as a press proof sheet — crop marks + proof grid (DS print-craft). */
export declare function ProofFrame(props: ProofFrameProps): JSX.Element;
