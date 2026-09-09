import * as React from 'react';

export interface DrawerProps {
  /** Controls visibility. */
  open: boolean;
  /** Close handler (scrim click + ✕). */
  onClose: () => void;
  title?: string;
  subtitle?: string;
  /** Status node shown at the header's left (e.g. a StatusBadge). */
  badge?: React.ReactNode;
  /** Panel max-width in px (default 480). */
  width?: number;
  /** Body content — compose DrawerSection children. */
  children?: React.ReactNode;
  /** Sticky footer action row. */
  footer?: React.ReactNode;
}

export interface DrawerSectionProps {
  /** Uppercase section heading. */
  title?: string;
  children?: React.ReactNode;
}

/** Right-side detail/form panel — PT-02 (DS v4). */
export declare function Drawer(props: DrawerProps): JSX.Element | null;
/** A titled section inside a Drawer body. */
export declare function DrawerSection(props: DrawerSectionProps): JSX.Element;
