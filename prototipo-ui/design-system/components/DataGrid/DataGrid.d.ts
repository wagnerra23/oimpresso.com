import * as React from 'react';

export interface DataGridColumn {
  key: string;
  label: string;
  align?: 'right';
  mono?: boolean;
  /** Largura CSS fixa, ex. '90px'. */
  width?: string;
  sortable?: boolean;
  sortValue?: (row: DataGridRow) => string | number;
}

export interface DataGridRow {
  id: string | number;
  state?: 'urgent' | 'archived';
  cells: Record<string, React.ReactNode | { primary: React.ReactNode; sub?: React.ReactNode }>;
}

export interface DataGridProps {
  columns: DataGridColumn[];
  rows: DataGridRow[];
  /** Linhas por página. Default 10. */
  pageSize?: number;
  pageSizeOptions?: number[];
  /** Informe para controlar o tamanho de página por fora. */
  onPageSizeChange?: (size: number) => void;
  /** Página controlada (1-based). Omita para paginação interna. */
  page?: number;
  defaultPage?: number;
  onPageChange?: (page: number) => void;
  density?: 'compact' | 'relaxed';
  selectable?: boolean;
  zebra?: boolean;
  onRowClick?: (row: DataGridRow) => void;
  onSelectionChange?: (ids: Array<string | number>) => void;
  defaultSort?: { key: string; dir?: 'asc' | 'desc' };
  /** Altura máxima da área de rolagem (header fica fixo). Default 420. */
  maxHeight?: number | string;
  emptyLabel?: React.ReactNode;
  /** Substantivo do contador do rodapé. Default 'registros'. */
  totalLabel?: string;
}

/** Grade densa com paginação embutida — header fixo, ordenação, seleção (DS). */
export declare function DataGrid(props: DataGridProps): JSX.Element;
