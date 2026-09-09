import * as React from 'react';

export interface DataTableProColumn {
  key: string;
  label: string;
  align?: 'right';
  mono?: boolean;
  width?: string;
  sortable?: boolean;
  resizable?: boolean;
  sortValue?: (row: DataTableProRow) => string | number;
}

export interface DataTableProRow {
  id: string | number;
  state?: 'urgent' | 'archived';
  cells: Record<string, React.ReactNode | { primary: React.ReactNode; sub?: React.ReactNode }>;
}

export interface DataTableProProps {
  columns: DataTableProColumn[];
  rows: DataTableProRow[];
  /** Altura da área de rolagem (header fixo). Default 440. */
  height?: number;
  density?: 'comfortable' | 'compact';
  selectable?: boolean;
  onRowClick?: (row: DataTableProRow) => void;
  onSelectionChange?: (ids: Array<string | number>) => void;
  defaultSort?: { key: string; dir?: 'asc' | 'desc' };
}

/** @deprecated Alias de DataGrid (resizable, pagination=false). Use DataGrid em telas novas. */
export declare function DataTablePro(props: DataTableProProps): JSX.Element | null;
