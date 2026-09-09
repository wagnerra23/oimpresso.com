export interface PaginationProps {
  /** Current page (1-based). */
  page: number;
  /** Total number of pages. */
  pageCount: number;
  /** Fires with the requested page. */
  onChange?: (page: number) => void;
  /** Total row count — with `pageSize`, renders the "N–M de T" meta. */
  total?: number;
  /** Rows per page. */
  pageSize?: number;
  /** Fires with a new page size — renders the "por página" <select> in the meta. */
  onPageSize?: (size: number) => void;
  /** Options for the per-page <select>. Default [10, 20, 50, 100]. */
  pageSizeOptions?: number[];
  /** Icon-only prev/next (drops the "Anterior/Próximo" labels) for tight toolbars. */
  /** Substantivo depois do total, ex. 'OS' → "1–10 de 43 OS". */
  totalLabel?: string;
  compact?: boolean;
  /** Previous-button label. Default "Anterior". */
  prevLabel?: string;
  /** Next-button label. Default "Próximo". */
  nextLabel?: string;
}

/** Page navigation: labeled prev/next + numbers + ellipsis + optional meta & per-page select (DS v6). */
export declare function Pagination(props: PaginationProps): JSX.Element;
