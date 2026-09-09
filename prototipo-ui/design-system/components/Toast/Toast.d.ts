import * as React from 'react';

export interface ToastProps {
  /** Message content. */
  children: React.ReactNode;
  /** Recolors the pill; default is the dark neutral pill. */
  tone?: 'default' | 'ok' | 'warn' | 'danger';
  /** Optional leading icon node. */
  icon?: React.ReactNode;
  /** Optional kbd hint, e.g. "⌘Z" for undo. */
  kbd?: string;
}

/** Fleeting confirmation toast — bottom-center dark pill (DS v4). */
export declare function Toast(props: ToastProps): JSX.Element;
