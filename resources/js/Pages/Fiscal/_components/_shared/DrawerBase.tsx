// DrawerBase.tsx — shell compartilhado dos drawers do módulo Fiscal.
//
// Encapsula a estrutura repetida em todos os drawers Fiscal (NotaDrawer,
// NotaDrawerV2, NFSeDrawer, EventosDrawer, SendToContabilDrawer):
//
//   - Backdrop (.fx-drawer-bg) com onClick=onClose
//   - <aside.fx-drawer role="dialog" aria-label="...">
//   - <header.fx-drawer-h> com botão X (fecha)
//   - <div.fx-drawer-body> (com padding default ou flush)
//   - <footer.fx-drawer-f> (opcional)
//   - ESC handler (window keydown) com closeOnEsc togglable
//
// NÃO encapsula:
//   - Modais nested (NotaDrawer mantém os 3 dele — Cancel/CCe/Retransmit).
//     Solução pra ESC stack: passar closeOnEsc={!cancelOpen && !cceOpen && !retransmitOpen}
//   - Conteúdo do header (eyebrow/title/key code), body (seções) e footer (botões)
//
// Tokens CSS canon: resources/css/fiscal-cockpit.css (.fx-drawer*, .fx-drawer-bg).
// Largura default 480px; props width permite override responsivo via min(Npx, 96vw).
//
// Refs: refactor onda 1 — extrai shell duplicado em 5 drawers
//       (-145 LOC líquidas + consistência ESC/a11y).

import { useEffect, type ReactNode, type RefObject } from 'react';

export interface DrawerBaseProps {
  /** Controla se drawer está aberto. Quando false, retorna null. */
  open: boolean;
  /** Callback ao fechar (ESC / backdrop click / botão X). */
  onClose: () => void;
  /** Label acessível obrigatório (role=dialog). Ex: "Detalhe NF-e 8425". */
  ariaLabel: string;
  /** Conteúdo do header (eyebrow + h2 + code key). Botão X é renderizado automaticamente. */
  header: ReactNode;
  /** Footer opcional. Recebe wrapper .fx-drawer-f automaticamente. */
  footer?: ReactNode;
  /** Body do drawer. Renderizado dentro de .fx-drawer-body. */
  children: ReactNode;
  /**
   * Largura do drawer em pixels. Default 480.
   * Override usa style={{ width: `min(${width}px, 96vw)` }}.
   * Valores canon: 480 (NotaDrawer/V2/NFSe), 640 (EventosDrawer), 760 (SendToContabilDrawer).
   */
  width?: number;
  /**
   * Quando true, body recebe padding: 0 (útil pra tabelas edge-to-edge).
   * Usado por EventosDrawer.
   */
  bodyFlush?: boolean;
  /**
   * Controla se ESC fecha o drawer. Default true.
   * NotaDrawer desliga durante modal interno aberto (ESC stack).
   */
  closeOnEsc?: boolean;
  /**
   * Ref opcional pro elemento .fx-drawer-body (útil pra observar scroll).
   * NotaDrawerV2 usa pra alternar header is-scrolled.
   */
  bodyRef?: RefObject<HTMLDivElement | null>;
  /**
   * Classes adicionais pro <aside>. Ex: " is-scrolled" no NotaDrawerV2.
   * Concatenado com "fx-drawer".
   */
  extraAsideClassName?: string;
}

export default function DrawerBase({
  open,
  onClose,
  ariaLabel,
  header,
  footer,
  children,
  width = 480,
  bodyFlush = false,
  closeOnEsc = true,
  bodyRef,
  extraAsideClassName,
}: DrawerBaseProps) {
  useEffect(() => {
    if (!open || !closeOnEsc) return;
    const handler = (e: KeyboardEvent) => {
      if (e.key === 'Escape') {
        e.preventDefault();
        onClose();
      }
    };
    window.addEventListener('keydown', handler);
    return () => window.removeEventListener('keydown', handler);
  }, [open, onClose, closeOnEsc]);

  if (!open) return null;

  const widthStyle = width !== 480 ? { width: `min(${width}px, 96vw)` } : undefined;
  const bodyStyle = bodyFlush ? { padding: 0 } : undefined;
  const asideClassName = extraAsideClassName
    ? `fx-drawer ${extraAsideClassName}`
    : 'fx-drawer';

  return (
    <>
      <div className="fx-drawer-bg" onClick={onClose} aria-hidden="true" />
      <aside
        className={asideClassName}
        style={widthStyle}
        role="dialog"
        aria-label={ariaLabel}
      >
        <header className="fx-drawer-h">
          {header}
          <button
            type="button"
            className="fx-drawer-x"
            onClick={onClose}
            aria-label="Fechar (ESC)"
          >
            ×
          </button>
        </header>

        <div className="fx-drawer-body" style={bodyStyle} ref={bodyRef}>
          {children}
        </div>

        {footer && <footer className="fx-drawer-f">{footer}</footer>}
      </aside>
    </>
  );
}
