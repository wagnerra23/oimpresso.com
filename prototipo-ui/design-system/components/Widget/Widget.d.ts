import * as React from 'react';

export interface WidgetProps {
  title?: React.ReactNode;
  /** Linha de apoio sob o título (contexto, período, fonte do dado). */
  note?: React.ReactNode;
  /** Nó ao lado do título — StatusBadge, contador, TagChip. */
  badge?: React.ReactNode;
  /** Canto superior direito — Kebab, Segmented, botão de ação. */
  actions?: React.ReactNode;
  footer?: React.ReactNode;
  tone?: 'default' | 'muted' | 'accent' | 'warning' | 'danger';
  /** Padding interno em px. Default 14. */
  pad?: number;
  height?: number | string;
  /** Corpo com rolagem própria (exige height). */
  scroll?: boolean;
  /** Remove o padding do corpo — para tabelas/listas coladas na borda. */
  flush?: boolean;
  children?: React.ReactNode;
}

/** Moldura de painel com título + nota, ações e rodapé (DS). */
export declare function Widget(props: WidgetProps): JSX.Element;
