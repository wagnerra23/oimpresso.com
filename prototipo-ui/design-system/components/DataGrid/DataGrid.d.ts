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
  /** `selected` pinta a linha como selecionada sem passar pela seleção por checkbox. */
  state?: 'urgent' | 'archived' | 'selected';
  cells: Record<string, React.ReactNode | { primary: React.ReactNode; sub?: React.ReactNode }>;
}

export interface DataGridProps {
  columns: DataGridColumn[];
  rows: DataGridRow[];
  /**
   * Nome acessível da tabela, num `<caption>` visualmente oculto. Sem ele, duas
   * tabelas na mesma página são indistinguíveis para quem navega por tabela — e
   * nenhuma regra de axe cobre isso. Opcional aqui, obrigatório no alias `DataTable`.
   */
  caption?: string;
  /** Rodapé de paginação. Default `true`; o alias `DataTable` passa `false`. */
  pagination?: boolean;
  /** Colunas redimensionáveis por arrasto (o alias `DataTablePro` liga). */
  resizable?: boolean;
  /** Linhas por página. Default 10. */
  pageSize?: number;
  pageSizeOptions?: number[];
  /** Informe para controlar o tamanho de página por fora. */
  onPageSizeChange?: (size: number) => void;
  /** Página controlada (1-based). Omita para paginação interna. */
  page?: number;
  defaultPage?: number;
  onPageChange?: (page: number) => void;
  density?: 'compact' | 'relaxed' | 'comfortable';
  selectable?: boolean;
  zebra?: boolean;
  onRowClick?: (row: DataGridRow) => void;
  /** Seleção INTERNA — dispara com os ids atuais. */
  onSelectionChange?: (ids: Array<string | number>) => void;
  /** Seleção CONTROLADA por fora (API antiga do DataTable). Passe os três juntos. */
  selectedIds?: Array<string | number>;
  onToggleRow?: (id: string | number, row: DataGridRow) => void;
  onToggleAll?: (checked: boolean) => void;
  /** Ordenação interna — ignorada quando `onSort` está presente. */
  defaultSort?: { key: string; dir?: 'asc' | 'desc' };
  /** Ordenação CONTROLADA por fora (API antiga do DataTable). */
  sortKey?: string;
  sortDir?: 'asc' | 'desc';
  onSort?: (key: string) => void;
  /** Altura máxima da área de rolagem (header fica fixo). Default 420. */
  maxHeight?: number | string;
  /** Altura fixa — vence `maxHeight` (o alias `DataTablePro` usa). */
  height?: number | string;
  emptyLabel?: React.ReactNode;
  /** Substantivo do contador do rodapé. Default 'registros'. */
  totalLabel?: string;
}

/** Grade densa com paginação embutida — header fixo, ordenação, seleção (DS). */
export declare function DataGrid(props: DataGridProps): JSX.Element;
