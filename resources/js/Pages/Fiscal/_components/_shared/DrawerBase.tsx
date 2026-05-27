// DrawerBase.tsx — shell compartilhado dos drawers do módulo Fiscal.
//
// Encapsula a estrutura repetida em todos os drawers Fiscal (NotaDrawer,
// NotaDrawerV2, NFSeDrawer, EventosDrawer, SendToContabilDrawer):
//
//   - Backdrop (.fx-drawer-bg) com onClick=onClose
//   - <aside.fx-drawer role="dialog" aria-modal="true" aria-label="...">
//   - <header.fx-drawer-h> com botão X (fecha)
//   - <div.fx-drawer-body> (com padding default ou flush)
//   - <footer.fx-drawer-f> (opcional)
//   - ESC handler (window keydown) com closeOnEsc togglable
//   - **A11y (Onda 2.1):** focus trap + aria-modal=true + return focus
//     ao fechar (WAI-ARIA Dialog APG pattern).
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
//       Onda 2.1 — focus trap + aria-modal + return focus (P1 polimento).

import { useEffect, useLayoutEffect, useRef, useState, type ReactNode, type RefObject } from 'react';

const FOCUSABLE_SELECTOR =
  'button:not([disabled]), [href], input:not([disabled]), select:not([disabled]), ' +
  'textarea:not([disabled]), [tabindex]:not([tabindex="-1"])';

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
   * Quando false, focus trap também desliga (modal nested gerencia seu próprio).
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
  /**
   * Quando muda, força re-rodar focus management effect. Útil pra consumers
   * com `data` async (SendToContabilDrawer monta DrawerBase com data=null,
   * depois re-monta quando data chega) — passar dataReady={data} resolve
   * race do foco inicial não entrar no drawer.
   */
  dataReady?: unknown;
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
  dataReady,
}: DrawerBaseProps) {
  const asideRef = useRef<HTMLElement | null>(null);
  const previousFocusRef = useRef<HTMLElement | null>(null);
  const internalBodyRef = useRef<HTMLDivElement | null>(null);
  const [isScrolled, setIsScrolled] = useState(false);
  const effectiveBodyRef = bodyRef ?? internalBodyRef;

  // ESC handler — escuta apenas quando drawer aberto e closeOnEsc=true.
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

  // Focus management — Onda 2.1 P1 + 2.1.2.
  //   1. Ao abrir: salva elemento ativo prévio + foca primeiro focusable do drawer
  //   2. Ao fechar: restaura foco pro elemento que disparou (trigger original)
  //   3. Tab/Shift+Tab cyclam dentro do drawer (focus trap)
  // Quando closeOnEsc=false (modal nested aberto), trap desliga pro modal cuidar.
  //
  // useLayoutEffect (sync após DOM commit) em vez de useEffect (async) elimina
  // race quando consumer tem data async — SendToContabilDrawer monta DrawerBase
  // quando data chega, useLayoutEffect dispara IMEDIATAMENTE após aside no DOM
  // (antes do browser paint), antes de qualquer focus-restore concorrente.
  useLayoutEffect(() => {
    if (!open) return;

    // Salva elemento ativo antes de focar dentro do drawer
    previousFocusRef.current = document.activeElement as HTMLElement | null;

    // Foca primeiro focusable do drawer (ou o próprio aside como fallback).
    // Double-RAF é necessário pq alguns triggers (button chip do PageHeader)
    // mantêm foco após click via React commit tardio. Single RAF é cancelado
    // por esse commit; double-RAF espera 2 paints e garante que aplique após
    // toda a cascata de re-render.
    const aside = asideRef.current;
    if (aside) {
      // Tira foco do trigger imediatamente — evita race com React focus-restore
      previousFocusRef.current?.blur?.();
      // setTimeout 50ms é mais robusto que RAF: alguns triggers (button chip do
      // PageHeader em consumers com data async) têm cascata de re-renders que
      // ultrapassa 2 RAFs. 50ms é imperceptível pro usuário mas dá folga total.
      const timeoutId = window.setTimeout(() => {
        const firstFocusable = aside.querySelector<HTMLElement>(FOCUSABLE_SELECTOR);
        (firstFocusable ?? aside).focus({ preventScroll: true });
      }, 50);
      return () => {
        window.clearTimeout(timeoutId);
        // Cleanup ao fechar: restaura foco anterior (se ainda no DOM e visível)
        const prev = previousFocusRef.current;
        if (prev && document.body.contains(prev)) {
          prev.focus({ preventScroll: true });
        }
      };
    }
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [open, dataReady]);

  // Header is-scrolled — Onda 2.2.
  // Observa scroll do body interno; quando rola >20px, aplica classe
  // .is-scrolled no aside que ativa CSS canon (padding menor, h2 15px,
  // fx-drawer-key/small hidden, backdrop blur). Compactify automático
  // pros 5 drawers — antes só NotaDrawerV2 tinha (e tinha seu próprio
  // observer local, agora centralizado aqui).
  useEffect(() => {
    if (!open) {
      setIsScrolled(false);
      return;
    }
    const el = effectiveBodyRef.current;
    if (!el) return;
    const onScroll = () => setIsScrolled(el.scrollTop > 20);
    el.addEventListener('scroll', onScroll, { passive: true });
    onScroll(); // estado inicial caso já comece scrollado
    return () => el.removeEventListener('scroll', onScroll);
  }, [open, effectiveBodyRef]);

  // Focus trap — Tab/Shift+Tab cyclam dentro do drawer.
  // Separado do ESC handler porque trap só desliga em closeOnEsc=false
  // (mesma condição: modal nested toma controle).
  useEffect(() => {
    if (!open || !closeOnEsc) return;
    const aside = asideRef.current;
    if (!aside) return;

    const handler = (e: KeyboardEvent) => {
      if (e.key !== 'Tab') return;
      const focusables = Array.from(
        aside.querySelectorAll<HTMLElement>(FOCUSABLE_SELECTOR),
      ).filter((el) => !el.hasAttribute('aria-hidden'));
      const first = focusables[0];
      const last = focusables[focusables.length - 1];
      if (!first || !last) return;
      const active = document.activeElement as HTMLElement | null;

      if (e.shiftKey && (active === first || active === aside)) {
        e.preventDefault();
        last.focus({ preventScroll: true });
      } else if (!e.shiftKey && active === last) {
        e.preventDefault();
        first.focus({ preventScroll: true });
      }
    };
    window.addEventListener('keydown', handler);
    return () => window.removeEventListener('keydown', handler);
  }, [open, closeOnEsc]);

  if (!open) return null;

  const widthStyle = width !== 480 ? { width: `min(${width}px, 96vw)` } : undefined;
  const bodyStyle = bodyFlush ? { padding: 0 } : undefined;
  // Compose className: base + is-scrolled auto + extra do consumer
  const classes = ['fx-drawer'];
  if (isScrolled) classes.push('is-scrolled');
  if (extraAsideClassName) classes.push(extraAsideClassName);
  const asideClassName = classes.join(' ');

  return (
    <>
      <div className="fx-drawer-bg" onClick={onClose} aria-hidden="true" />
      <aside
        ref={asideRef}
        className={asideClassName}
        style={widthStyle}
        role="dialog"
        aria-modal="true"
        aria-label={ariaLabel}
        tabIndex={-1}
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

        <div className="fx-drawer-body" style={bodyStyle} ref={effectiveBodyRef}>
          {children}
        </div>

        {footer && <footer className="fx-drawer-f">{footer}</footer>}
      </aside>
    </>
  );
}
