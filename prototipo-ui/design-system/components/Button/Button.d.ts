import * as React from 'react';

export interface ButtonProps {
  children?: React.ReactNode;
  /** primary = filled roxo · ghost = outline · danger = destructive outline. */
  variant?: 'primary' | 'ghost' | 'danger';
  size?: 'sm' | 'default' | 'lg';
  /** Square icon-only button. */
  icon?: boolean;
  /** Trailing kbd hint, e.g. "⌘S". */
  kbd?: string;
  disabled?: boolean;
  type?: 'button' | 'submit' | 'reset';
  onClick?: () => void;
  style?: React.CSSProperties;
}

/** Action button — variants, sizes, kbd, icon-only (DS v4). */
export declare function Button(props: ButtonProps): JSX.Element;
