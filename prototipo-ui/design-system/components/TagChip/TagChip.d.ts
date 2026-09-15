export type TagCategory =
  | 'varejo' | 'atacado' | 'corporativo' | 'evento' | 'parceiro'
  | 'agencia' | 'governo' | 'vip' | 'reincidente' | (string & {});

export interface TagChipProps {
  /** Category label (case-insensitive); known categories get a fixed color. */
  label: TagCategory;
  /** Show an ✕ to remove (filter-style chip). */
  removable?: boolean;
  /** Click handler for the ✕. */
  onRemove?: () => void;
}

/** Small lowercase category chip with a fixed semantic palette (CRM/Clientes). */
export declare function TagChip(props: TagChipProps): JSX.Element;
