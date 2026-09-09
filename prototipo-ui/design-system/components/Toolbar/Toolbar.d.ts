import * as React from 'react';

export interface ToolbarProps {
  /** Zona esquerda — busca, filtros, ações primárias. */
  left?: React.ReactNode;
  /** Zona central — ocupa o espaço livre. */
  center?: React.ReactNode;
  /** Zona direita — densidade, exportar, kebab. */
  right?: React.ReactNode;
  children?: React.ReactNode;
  sticky?: boolean;
  dense?: boolean;
  tone?: 'surface' | 'muted' | 'transparent';
  bordered?: boolean;
}

export interface ToolbarButtonProps {
  icon?: React.ReactNode;
  children?: React.ReactNode;
  active?: boolean;
  disabled?: boolean;
  tone?: 'default' | 'danger';
  onClick?: () => void;
  title?: string;
  /** Só ícone (28×28) — informe title para acessibilidade. */
  iconOnly?: boolean;
}

export interface ToolbarSearchProps {
  value?: string;
  onChange?: (value: string) => void;
  placeholder?: string;
  width?: number | string;
  /** Atalho exibido à direita, ex. '/'. */
  kbd?: string;
}

/** Barra de ferramentas de três zonas (DS). */
export declare function Toolbar(props: ToolbarProps): JSX.Element;
export declare function ToolbarButton(props: ToolbarButtonProps): JSX.Element;
export declare function ToolbarSearch(props: ToolbarSearchProps): JSX.Element;
export declare function ToolbarDivider(): JSX.Element;
export declare function ToolbarSpacer(): JSX.Element;
