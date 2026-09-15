import * as React from 'react';

export interface TimelineChange {
  field: string;
  from?: React.ReactNode;
  to?: React.ReactNode;
}

export interface TimelineEntry {
  id: string | number;
  /** Horário exibido em mono, ex. '14:32'. */
  time: string;
  /** Cabeçalho do grupo quando groupByDay. */
  day?: string;
  actor?: string;
  action: React.ReactNode;
  detail?: React.ReactNode;
  tone?: 'default' | 'accent' | 'success' | 'warning' | 'danger';
  icon?: React.ReactNode;
  /** Pares de/para para trilha de auditoria. */
  changes?: TimelineChange[];
  meta?: Array<{ label: string; value: React.ReactNode }>;
}

export interface TimelineProps {
  entries: TimelineEntry[];
  dense?: boolean;
  /** Agrupa entradas consecutivas pelo campo day. */
  groupByDay?: boolean;
  emptyLabel?: React.ReactNode;
}

/** Trilha de auditoria / histórico de eventos (DS). */
export declare function Timeline(props: TimelineProps): JSX.Element;
