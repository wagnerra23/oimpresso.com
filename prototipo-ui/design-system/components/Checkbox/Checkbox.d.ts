export interface CheckboxProps {
  checked?: boolean;
  /** Fires with the next boolean. */
  onChange?: (checked: boolean) => void;
  label?: string;
  disabled?: boolean;
  name?: string;
}

/** Labeled checkbox with accent fill (DS v4). */
export declare function Checkbox(props: CheckboxProps): JSX.Element;
