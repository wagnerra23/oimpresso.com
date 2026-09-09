export interface RadioOption {
  value: string;
  label: string;
}

export interface RadioGroupProps {
  /** Options as `[{value,label}]` (or plain strings). */
  options: Array<RadioOption | string>;
  /** Selected value. */
  value?: string;
  /** Fires with the chosen value. */
  onChange?: (value: string) => void;
  /** Shared input name (required to group radios). */
  name: string;
  disabled?: boolean;
  /** Stack 'column' (default) or 'row'. */
  direction?: 'column' | 'row';
}

/** Single-choice radio group (DS v4). */
export declare function RadioGroup(props: RadioGroupProps): JSX.Element;
