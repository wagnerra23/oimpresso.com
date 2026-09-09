export interface SwitchProps {
  checked?: boolean;
  /** Fires with the next boolean. */
  onChange?: (checked: boolean) => void;
  label?: string;
  /** Secondary line under the label. */
  sublabel?: string;
  disabled?: boolean;
  name?: string;
}

/** Boolean toggle switch with label/sublabel (DS v4). */
export declare function Switch(props: SwitchProps): JSX.Element;
