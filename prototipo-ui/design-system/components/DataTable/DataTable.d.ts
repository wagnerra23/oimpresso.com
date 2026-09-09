import * as React from 'react';

export interface DataTableColumn {
  key: string;
  label: string;
  align?: 'right';
  mono?: boolean;
  width?: string;
  sortable?: boolean;
}

export interface DataTableRow {
  id: string | number;
  state?: 'urgent' | 'selected' | 'archived';
  cells: Record<string, React.ReactNode | { primary: React.ReactNode; sub?: React.ReactNode }>;
}

export interface DataTableProps {
  columns: DataTableColumn[];
  rows: DataTableRow[];
  onRowClick?: (row: DataTableRow) => void;
  selectable?: boolean;
  /** Seleção controlada (API antiga). */
  selectedIds?: Array<string | number>;
  onToggleRow?: (id: string | number, row: DataTableRow) => void;
  onToggleAll?: (checked: boolean) => void;
  /** Ordenação controlada (API antiga). */
  sortKey?: string;
  sortDir?: 'asc' | 'desc';
  onSort?: (key: string) => void;
}

/** @deprecated Alias de DataGrid (pagination=false). Use DataGrid em telas novas. */
export declare function DataTable(props: DataTableProps): JSX.Element | null;
