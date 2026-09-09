import * as React from 'react';

export interface BulkAction {
  label: string;
  icon?: React.ReactNode;
  /** 'danger' tints the hover red. */
  tone?: 'danger';
  onClick?: () => void;
}

export interface BulkBarProps {
  /** Number of selected rows (shown in the pill counter). */
  count: number;
  /** Noun after the count, e.g. "selecionadas". */
  label?: string;
  /** Batch actions. */
  actions?: BulkAction[];
  /** Clears the selection (renders the ✕). */
  onClose?: () => void;
}

/** Floating bulk-action bar for multi-row selection (DS v4). */
export declare function BulkBar(props: BulkBarProps): JSX.Element;
