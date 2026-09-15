import * as React from 'react';

export interface KebabItem {
  id?: string;
  label?: React.ReactNode;
  icon?: React.ReactNode;
  /** Atalho exibido à direita, ex. '⌘E'. */
  kbd?: string;
  tone?: 'danger';
  disabled?: boolean;
  /** Linha divisória (ignora os demais campos). */
  separator?: boolean;
  onSelect?: () => void;
}

export interface KebabProps {
  items: KebabItem[];
  /** Lado de ancoragem do menu. Default 'end'. */
  align?: 'start' | 'end';
  width?: number;
  size?: 'sm' | 'md';
  /** Rótulo acessível do gatilho. Default 'Mais ações'. */
  label?: string;
  /** Três pontos verticais (default) ou horizontais. */
  orientation?: 'vertical' | 'horizontal';
  disabled?: boolean;
}

/** Menu de ações compacto para linhas, cards e widgets (DS). */
export declare function Kebab(props: KebabProps): JSX.Element;
