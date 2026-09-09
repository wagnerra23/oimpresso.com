import * as React from 'react';

export interface KpiFilterCardProps {
  label: React.ReactNode;
  value: React.ReactNode;
  sub?: React.ReactNode;
  icon?: React.ReactNode;
  tone?: 'primary' | 'amber' | 'rose' | 'emerald' | 'violet';
  selected?: boolean;
  onClick?: () => void;
}

/** @deprecated Alias de KpiCard variant="filter". */
export declare function KpiFilterCard(props: KpiFilterCardProps): JSX.Element | null;
