import * as React from 'react';

export interface DropdownMenuItem {
  id: string;
  label: React.ReactNode;
  /** Ícone à esquerda (inline SVG). */
  icon?: React.ReactNode;
  /** Badge de atalho à direita. */
  kbd?: string;
  /** 'danger' pinta o item em tom destrutivo. */
  tone?: 'danger';
  disabled?: boolean;
  /** Renderiza uma linha separadora (ignora os demais campos). */
  separator?: boolean;
  /** Disparado ao selecionar; o menu fecha em seguida. */
  onSelect?: () => void;
}

export interface DropdownMenuProps {
  /** Gatilho: um node, ou uma render-fn que recebe { open, onClick }. Omitido = botão "Ações" padrão. */
  trigger?: React.ReactNode | ((s: { open: boolean; onClick: () => void }) => React.ReactNode);
  /** Alinhamento do painel ao gatilho. Default 'start'. */
  align?: 'start' | 'end';
  /** Largura do painel em px. Default 220. */
  width?: number;
  items: DropdownMenuItem[];
}

/** Menu de ações ancorado — esc / clique-fora / navegação por teclado. */
export declare function DropdownMenu(props: DropdownMenuProps): JSX.Element;
