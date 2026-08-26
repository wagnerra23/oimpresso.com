import * as React from 'react';

export interface PageHeaderStat {
  /** The emphasized number/value. */
  value: React.ReactNode;
  /** Trailing label, e.g. "abertas". */
  label?: string;
  /** Tints the value. */
  tone?: 'danger' | 'warn';
}

export interface PageHeaderProps {
  /** Page title (22/600). */
  title: string;
  /** Toned stat segments joined with " · " (alternative to subtitle). */
  stats?: PageHeaderStat[];
  /** Free-form subtitle node (rendered before stats). */
  subtitle?: React.ReactNode;
  /** Right-aligned actions (buttons). */
  actions?: React.ReactNode;
}

/** Flat index/page header — slot 1 of the PT-01 list pattern (DS v4 canon). */
export declare function PageHeader(props: PageHeaderProps): JSX.Element;
