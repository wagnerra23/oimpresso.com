import * as React from 'react';

export interface PresenterModeProps {
  open?: boolean;
  onClose?: () => void;
  /** Substitui window.print() (ex.: exportar PDF pelo host). */
  onPrint?: () => void;
  title?: React.ReactNode;
  subtitle?: React.ReactNode;
  /** Quantidade de folhas. Default 1. */
  pages?: number;
  /** Conteúdo da folha — nó único ou (indice) => nó. */
  children?: React.ReactNode | ((page: number) => React.ReactNode);
  paper?: 'A4' | 'letter';
  orientation?: 'portrait' | 'landscape';
  /** Zoom inicial do palco (0.4–2). Default 0.9. */
  zoom?: number;
  showPrint?: boolean;
  /** Rodapé com os atalhos. Default true. */
  hint?: boolean;
}

/** Modo apresentação/impressão — palco escuro, folhas em papel real, print embutido (DS). */
export declare function PresenterMode(props: PresenterModeProps): JSX.Element | null;
