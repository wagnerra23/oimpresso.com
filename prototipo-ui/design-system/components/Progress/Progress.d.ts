import * as React from 'react';

export interface ProgressProps {
  /** Valor atual (0..max). */
  value?: number;
  /** Máximo. Default 100. */
  max?: number;
  /** Tom semântico. Default 'accent'. */
  tone?: 'accent' | 'success' | 'warn' | 'danger';
  /** Linha ('bar') ou anel SVG ('ring'). Default 'bar'. */
  variant?: 'bar' | 'ring';
  /** Rótulo (à esquerda na barra / abaixo do valor no anel). */
  label?: React.ReactNode;
  /** Mostra o percentual/valor formatado. */
  showValue?: boolean;
  /** Diâmetro do anel (px) ou altura da barra (px). */
  size?: number;
  /** Formatação custom do valor exibido. */
  formatValue?: (v: number) => string;
}

/** Indicador de progresso determinístico — barra ou anel. */
export declare function Progress(props: ProgressProps): JSX.Element;
