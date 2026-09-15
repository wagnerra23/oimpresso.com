import * as React from 'react';

export interface FilterChipProps {
  /** Filter field name, e.g. "Status". */
  label: string;
  /** Optional active value, e.g. "Aberta". */
  value?: React.ReactNode;
  /** Remove handler — renders the ✕ when provided. */
  onRemove?: () => void;
}

/** Active, removable filter chip (DS v4). */
export declare function FilterChip(props: FilterChipProps): JSX.Element;
