import * as React from 'react';

export interface ModalProps {
  open: boolean;
  onClose: () => void;
  title?: string;
  /** Body copy / content. */
  children?: React.ReactNode;
  /** Right-aligned action row (Cancel + confirm). */
  footer?: React.ReactNode;
  /** Max-width in px (default 420). */
  width?: number;
}

/** Centered confirmation/short-action dialog — PT-04 (DS v4). Not for detail (use Drawer). */
export declare function Modal(props: ModalProps): JSX.Element | null;
