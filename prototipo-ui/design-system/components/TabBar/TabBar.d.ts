import * as React from 'react';

export interface TabItem {
  /** Unique key (matches `active`). */
  key: string;
  label: string;
  /** Optional leading icon node (overrides the shared `icon` fallback). */
  icon?: React.ReactNode;
  /** Optional counter badge. */
  count?: number;
  /** Disables this tab only. */
  disabled?: boolean;
}

/**
 * O `<nav>` é o contrato: além das props abaixo, qualquer atributo extra
 * (`data-contract`, `data-*`, `aria-*`, `id`, `role`, `style`) é repassado
 * direto para o `<nav>`. Nenhum wrapper é necessário.
 */
export interface TabBarProps extends React.HTMLAttributes<HTMLElement> {
  tabs: TabItem[];
  /** Active tab key. */
  active: string;
  /** Fires with the clicked tab key. */
  onChange?: (key: string) => void;
  /** Somado a `ds-tabbar` — não substitui a classe interna. */
  className?: string;
  /** Sobrepõe o rótulo default "Sub-navegação". */
  ariaLabel?: string;
  /** Padding horizontal de cada aba, em px (default 14). */
  pad?: number;
  /** Altura/tipografia da barra (default 'md' = 36px/13px). */
  size?: 'sm' | 'md' | 'lg';
  /** Barra inteira inerte: opacidade reduzida, sem clique, `aria-disabled`. */
  off?: boolean;
  /** Ícone default para abas sem `icon` próprio. */
  icon?: React.ReactNode;
  /**
   * Recuo lateral aplicado no próprio `<nav>` (número = px, string = valor CSS).
   * Substitui o `<div>` de padding em volta da barra e mantém a borda inferior
   * sangrando de ponta a ponta.
   */
  inset?: number | string;
}

/** Module sub-tabs with counts — slot 2 of PT-01 (DS v4 moduletopnav). */
export declare function TabBar(props: TabBarProps): JSX.Element;
