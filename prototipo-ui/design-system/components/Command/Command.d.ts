import * as React from 'react';

export interface CommandItem {
  id: string;
  label: string;
  /** Right-aligned secondary text. */
  hint?: string;
  /** Trailing keyboard shortcut badge, e.g. "⌘N". */
  kbd?: string;
  /** Leading icon node (inline SVG). */
  icon?: React.ReactNode;
  /** Fired on select (click or ↵). The palette closes afterwards. */
  onSelect?: () => void;
}

export interface CommandGroup {
  label: string;
  items: CommandItem[];
}

export interface CommandProps {
  open: boolean;
  onClose: () => void;
  placeholder?: string;
  /** Grouped, filterable results. */
  groups: CommandGroup[];
}

/** ⌘K command palette — live filter + full keyboard nav (DS premium). */
export declare function Command(props: CommandProps): JSX.Element | null;
