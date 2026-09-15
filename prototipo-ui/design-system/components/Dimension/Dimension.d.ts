import * as React from 'react';

export interface DimensionProps {
  /** The measurement label, e.g. "3.000 mm". */
  value: React.ReactNode;
  /** 'h' fills the container width; 'v' fills its height (needs a stretch parent). */
  orientation?: 'h' | 'v';
  /** Line + caps color. */
  color?: string;
  /** Background of the centered label chip — match the surface it sits on. */
  surface?: string;
  /** Track thickness in px (default 22). */
  size?: number;
}

/** Technical measure line (cota) with end caps + centered mono label (DS print-craft). */
export declare function Dimension(props: DimensionProps): JSX.Element;
