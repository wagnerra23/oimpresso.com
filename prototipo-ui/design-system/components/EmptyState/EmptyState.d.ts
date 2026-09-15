import * as React from 'react';

export interface EmptyStateProps {
  /** Contextual variant — tints border/background/icon. */
  variant?: 'default' | 'first' | 'no-results' | 'no-perm' | 'offline' | 'done' | 'filtered' | 'error';
  /** Icon node (centered in the round plate). */
  icon?: React.ReactNode;
  /** Headline — say WHY it's empty. */
  title?: string;
  /** Supporting line — say WHAT to do. */
  description?: string;
  /** Specific CTA node (never a generic "OK"). */
  action?: React.ReactNode;
}

/** Contextual empty/zero/error state (DS v4) — always WHY + WHAT. */
export declare function EmptyState(props: EmptyStateProps): JSX.Element;
