import * as React from 'react';

export interface AlertProps {
  /** Tom semântico. Default 'info'. */
  tone?: 'info' | 'success' | 'warn' | 'danger';
  /** Título em negrito (sentence case). */
  title?: React.ReactNode;
  /** Descrição / corpo. */
  children?: React.ReactNode;
  /** Ícone à esquerda (sobrescreve o ícone padrão do tom). */
  icon?: React.ReactNode;
  /** Slot de ação (botão/link) abaixo da descrição. */
  action?: React.ReactNode;
  /** Se presente, renderiza o botão de fechar. */
  onClose?: () => void;
}

/** Banner inline de aviso no fluxo da página (fundo tintado no tom semântico). */
export declare function Alert(props: AlertProps): JSX.Element;
