import * as React from 'react';

export interface SegmentedOption {
  value: string;
  label?: React.ReactNode;
  icon?: React.ReactNode;
  count?: number;
  disabled?: boolean;
}

export interface SegmentedProps {
  /** 2–5 opções mutuamente exclusivas. */
  options: SegmentedOption[];
  value: string;
  onChange?: (value: string) => void;
  size?: 'sm' | 'md' | 'lg';
  /** Ocupa a largura do container, em colunas iguais. */
  full?: boolean;
  /** Só ícones — informe label em texto para acessibilidade. */
  iconOnly?: boolean;
  ariaLabel?: string;
}

/** Controle segmentado — visão, período, densidade (DS). */
export declare function Segmented(props: SegmentedProps): JSX.Element;
