import * as React from 'react';

interface FieldBase {
  /** Uppercase field label. */
  label?: string;
  /** Help text under the control (hidden when error is shown). */
  help?: string;
  /** Error message — presence marks the field invalid (red border + ring). */
  error?: string;
  name?: string;
  disabled?: boolean;
}

export interface InputProps extends FieldBase {
  value?: string;
  defaultValue?: string;
  placeholder?: string;
  type?: string;
  readOnly?: boolean;
  id?: string;
  autoFocus?: boolean;
  /** Leading icon node (16px-ish svg, inherits `currentColor` = muted text). */
  icon?: React.ReactNode;
  /** Trailing kbd hint, e.g. "/" or "⌘K". Visual only — pair with `focusKey` to make it work. */
  kbd?: string;
  /** Global key binding that focuses+selects this field: "/", "mod+k", "shift+f"… */
  focusKey?: string;
  /** Ref to the underlying `<input>` (for programmatic focus). */
  inputRef?: React.Ref<HTMLInputElement>;
  onChange?: (e: React.ChangeEvent<HTMLInputElement>) => void;
  onKeyDown?: (e: React.KeyboardEvent<HTMLInputElement>) => void;
}

/** Search preset: magnifier icon + `/` hint & shortcut, both overridable. */
export interface SearchInputProps extends InputProps {}
export interface TextareaProps extends FieldBase {
  value?: string;
  defaultValue?: string;
  placeholder?: string;
  rows?: number;
  readOnly?: boolean;
  onChange?: (e: React.ChangeEvent<HTMLTextAreaElement>) => void;
}
export interface SelectProps extends FieldBase {
  value?: string;
  defaultValue?: string;
  /** Options as `[{value,label}]` (or use `children` <option>s). */
  options?: Array<{ value: string; label: string } | string>;
  children?: React.ReactNode;
  onChange?: (e: React.ChangeEvent<HTMLSelectElement>) => void;
}

/** Text input with label/help/error chrome (DS v4). */
export declare function Input(props: InputProps): JSX.Element;
/** Multiline input with label/help/error chrome (DS v4). */
export declare function Textarea(props: TextareaProps): JSX.Element;
/** Native select with label/help/error chrome + chevron (DS v4). */
export declare function Select(props: SelectProps): JSX.Element;
/** Search input — icon + kbd hint + keyboard shortcut (DS v6). */
export declare function SearchInput(props: SearchInputProps): JSX.Element;
