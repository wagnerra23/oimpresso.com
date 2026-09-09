import * as React from 'react';

export interface DatePickerProps {
  /** Data selecionada (Date, string ISO, ou null). */
  value?: Date | string | null;
  /** Disparado ao escolher um dia (ou null ao limpar). */
  onChange?: (d: Date | null) => void;
  /** Rótulo uppercase acima do campo. */
  label?: React.ReactNode;
  /** Placeholder quando vazio. Default 'dd/mm/aaaa'. */
  placeholder?: string;
  /** Limite inferior selecionável. */
  min?: Date | string;
  /** Limite superior selecionável. */
  max?: Date | string;
  disabled?: boolean;
}

/** Campo de data com calendário PT-BR (dd/mm/aaaa). */
export declare function DatePicker(props: DatePickerProps): JSX.Element;
