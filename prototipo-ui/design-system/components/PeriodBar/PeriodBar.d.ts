import * as React from 'react';

export interface PeriodPreset {
  /** Identificador estável — usado como `value.preset`. */
  id: string;
  /** Rótulo curto no segmented (ex.: 'Dia'). */
  label: string;
  /** Calcula o intervalo do preset. Default: janelas rolantes relativas a hoje. */
  range: () => { from: Date; to: Date };
}

export interface PeriodValue {
  from: Date | string | null;
  to: Date | string | null;
  /** id do preset ativo, ou 'custom' quando o usuário edita um campo. */
  preset?: string | null;
}

export interface PeriodBarProps {
  /** Valor controlado. Omita para uso não-controlado (estado interno). */
  value?: PeriodValue | null;
  /** Disparado a cada mudança (preset ou campo). */
  onChange?: (next: PeriodValue) => void;
  /**
   * Presets do segmented. Default: Dia (hoje) · Semana (últimos 7 dias) ·
   * Mês (últimos 30 dias). Cada preset pode trazer seu próprio `range()`.
   */
  presets?: PeriodPreset[];
  /** Rótulo uppercase acima do segmented. Default 'Período'. */
  label?: React.ReactNode;
  disabled?: boolean;
}

/**
 * Barra de período para telas de consulta: segmented de presets +
 * campos De/Até (DatePicker PT-BR) sempre visíveis. Editar um campo comuta
 * o preset para 'custom'.
 */
export declare function PeriodBar(props: PeriodBarProps): JSX.Element;
