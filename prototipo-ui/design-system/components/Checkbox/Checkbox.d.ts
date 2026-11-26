export interface CheckboxProps {
  checked?: boolean;
  /** Fires with the next boolean. */
  onChange?: (checked: boolean) => void;
  label?: string;
  disabled?: boolean;
  name?: string;
  /** Traço de "parte marcada" (cabeçalho de grade com seleção parcial). */
  indeterminate?: boolean;
  /** Nome acessível quando não há `label` visível. */
  ariaLabel?: string;
}

/** Labeled checkbox with accent fill (DS v4). */
export declare function Checkbox(props: CheckboxProps): JSX.Element;
