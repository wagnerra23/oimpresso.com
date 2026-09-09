import * as React from 'react';

export interface TooltipProps {
  /** Conteúdo do balão (texto ou node). */
  content: React.ReactNode;
  /** Lado de ancoragem relativo ao filho. Default 'top'. */
  side?: 'top' | 'bottom' | 'left' | 'right';
  /** Badge de atalho de teclado, ex. "⌘K". */
  kbd?: string;
  /** Atraso em ms antes de aparecer. Default 120. */
  delay?: number;
  /** Elemento-alvo que dispara a dica no hover/foco. */
  children: React.ReactNode;
}

/** Dica contextual acessível (hover + foco) com seta. */
export declare function Tooltip(props: TooltipProps): JSX.Element;
